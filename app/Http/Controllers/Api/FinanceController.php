<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\Invoice;
use App\Models\MembershipType;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Money;
use App\Support\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FinanceController extends Controller
{
    public const STATUS = ['open' => 'Opkrævet', 'paid' => 'Betalt', 'overdue' => 'Forfalden', 'cancelled' => 'Annulleret', 'refunded' => 'Refunderet'];

    public static function accountId(string $number): int
    {
        return Account::where('number', $number)->value('id');
    }

    public static function invoiceRow(Invoice $i): array
    {
        $st = $i->displayStatus();
        $refunded = -$i->payments->where('amount', '<', 0)->sum('amount');

        return [
            'id' => $i->id, 'kind' => $i->kind, 'description' => $i->description,
            'amount' => $i->amount, 'amount_fmt' => Money::fmt($i->amount),
            'refunded_fmt' => $refunded ? Money::fmt($refunded) : null, 'refundable' => $i->amount - $refunded,
            'status' => $st, 'status_label' => self::STATUS[$st] ?? $st,
            'due_on' => $i->due_on->toDateString(), 'paid_at' => $i->paid_at?->toIso8601String(),
            'payer' => $i->payer?->name, 'payer_id' => $i->payer_id, 'member' => $i->member?->name, 'period' => $i->period,
            'method' => ($m = $i->payments->firstWhere('amount', '>', 0)?->method) ? Payments::methodLabel($m) : null,
            'member_id_pending' => $i->kind === 'membership' && $i->member?->status === 'pending',
            'is_upgrade' => ($i->payload['action'] ?? null) === 'family_upgrade',
            'upgrade_members' => ($i->payload['action'] ?? null) === 'family_upgrade' ? array_column($i->payload['members'] ?? [], 'name') : null,
        ];
    }

    private function read(Request $r): void { abort_unless($r->user()->canReadFinance(), 403); }
    private function write(Request $r): void { abort_unless($r->user()->canWriteFinance(), 403, 'Kun kassereren kan ændre i regnskabet.'); }

    /* ---------- Betalinger ---------- */

    public function invoices(Request $r)
    {
        $this->read($r);
        $q = Invoice::with('payer', 'member', 'payments')->latest('id');
        if ($k = $r->query('kind')) $q->where('kind', $k);
        if ($s = $r->query('q')) $q->where(fn ($q) => $q->where('description', 'like', "%$s%")->orWhereHas('payer', fn ($q) => $q->where('name', 'like', "%$s%")));
        $rows = $q->limit(500)->get()->map(fn ($i) => self::invoiceRow($i));
        if ($st = $r->query('status')) $rows = $rows->where('status', $st)->values();

        $all = Invoice::all();

        return [
            'rows' => $rows,
            'totals' => [
                'open' => Money::fmt($all->filter(fn ($i) => $i->displayStatus() === 'open')->sum('amount')),
                'overdue' => Money::fmt($all->filter(fn ($i) => $i->displayStatus() === 'overdue')->sum('amount')),
                'paid' => Money::fmt($all->where('status', 'paid')->sum('amount')),
                'overdue_count' => $all->filter(fn ($i) => $i->displayStatus() === 'overdue')->count(),
            ],
        ];
    }

    /** Medlemmet betaler sin egen regning. I PoC'en simuleret MobilePay. */
    public function pay(Request $r, Invoice $invoice)
    {
        abort_unless($invoice->payer_id === $r->user()->id, 403);
        $method = $r->validate(['method' => 'required|in:mobilepay,card'])['method'];
        Payments::markPaid($invoice, $method, $r->user());

        return ['ok' => true, 'activity_id' => $invoice->activity_id];
    }

    public function invoice(Request $r, Invoice $invoice)
    {
        abort_unless($invoice->payer_id === $r->user()->id || $r->user()->canReadFinance(), 403);

        return self::invoiceRow($invoice->load('payer', 'member', 'payments'));
    }

    public function manualPayment(Request $r, Invoice $invoice)
    {
        $this->write($r);
        $d = $r->validate(['method' => 'required|in:cash,bank', 'reference' => 'nullable|string|max:100']);
        Payments::markPaid($invoice, $d['method'], $r->user(), $d['reference'] ?? null);

        return ['ok' => true];
    }

    public function refund(Request $r, Invoice $invoice)
    {
        $this->write($r);
        $d = $r->validate(['amount' => 'required|integer|min:1', 'reason' => 'required|string|max:200']);
        Payments::refund($invoice, $d['amount'], $r->user(), $d['reason']);

        return ['ok' => true];
    }

    public function remind(Request $r, Invoice $invoice)
    {
        $this->write($r);
        $invoice->payer->notify(new ClubMessage('Påmindelse: '.$invoice->description, 'Vi mangler betaling på '.Money::fmt($invoice->amount).', forfaldt '.$invoice->due_on->format('d.m.Y').'.', '/profil/regninger'));
        Audit::log('invoice.reminded', $invoice);

        return ['ok' => true];
    }

    /** Opret kontingentopkrævning for et år til alle aktive/passive medlemmer (familie = én regning). */
    public function generateMembership(Request $r)
    {
        $this->write($r);
        $d = $r->validate(['year' => 'required|integer|min:2020|max:2100', 'prorate' => 'boolean']);
        $year = (string) $d['year'];
        $account = self::accountId('1000');
        $created = 0;
        $sum = 0;

        DB::transaction(function () use ($d, $year, $account, &$created, &$sum) {
            $members = User::with('membershipType', 'household')->whereIn('status', ['active', 'passive'])->whereNotNull('membership_type_id')->get();
            foreach ($members as $m) {
                $type = $m->membershipType;
                $isFamily = $type->code === 'family';
                $payer = $m->payer();
                // Familiekontingent: kun én regning pr. husstand (til betaleren)
                if ($isFamily && $payer->id !== $m->id) continue;
                if ($m->household && $m->household->payer?->membershipType?->code === 'family' && $payer->id !== $m->id) continue;

                $exists = Invoice::where('kind', 'membership')->where('period', $year)
                    ->where($isFamily ? fn ($q) => $q->whereNull('member_id')->where('payer_id', $payer->id) : fn ($q) => $q->where('member_id', $m->id))->exists();
                if ($exists) continue;

                $amount = $type->annual_fee;
                $note = '';
                if (($d['prorate'] ?? false) && $m->joined_on && $m->joined_on->year == $year) {
                    $months = 12 - $m->joined_on->month + 1;
                    $amount = (int) round($amount * $months / 12);
                    $note = " ($months/12)";
                }
                Invoice::create([
                    'payer_id' => $payer->id, 'member_id' => $isFamily ? null : $m->id, 'kind' => 'membership',
                    'description' => "Kontingent $year – ".$type->name.($isFamily ? ' ('.$m->household->name.')' : ' – '.$m->name).$note,
                    'amount' => $amount, 'due_on' => now()->addDays(14), 'period' => $year, 'account_id' => $account,
                ]);
                $payer->notify(new ClubMessage("Kontingent $year", 'Der er oprettet en opkrævning på '.Money::fmt($amount).' med betaling senest '.now()->addDays(14)->format('d.m.Y').'.', '/profil/regninger'));
                $created++;
                $sum += $amount;
            }
        });
        Audit::log('membership.generated', null, ['year' => $year, 'count' => $created]);

        return ['created' => $created, 'total' => Money::fmt($sum)];
    }

    /* ---------- Regnskab ---------- */

    public function accounts(Request $r)
    {
        $this->read($r);

        return Account::orderBy('number')->get(['id', 'number', 'name', 'type']);
    }

    public function overview(Request $r)
    {
        $this->read($r);
        $year = (int) $r->query('year', now()->year);
        $sums = Entry::whereYear('entry_date', $year)->groupBy('account_id')->select('account_id', DB::raw('SUM(amount) s'))->pluck('s', 'account_id');
        $budgets = Budget::where('year', $year)->pluck('amount', 'account_id');

        $rows = Account::whereIn('type', ['income', 'expense'])->orderBy('number')->get()->map(function ($a) use ($sums, $budgets) {
            $actual = (int) ($sums[$a->id] ?? 0);
            $actual = $a->type === 'expense' ? -$actual : $actual;
            $budget = (int) ($budgets[$a->id] ?? 0);

            return ['id' => $a->id, 'number' => $a->number, 'name' => $a->name, 'type' => $a->type, 'actual' => $actual, 'budget' => $budget];
        });

        $income = $rows->where('type', 'income')->sum('actual');
        $expense = $rows->where('type', 'expense')->sum('actual');

        $monthly = Entry::whereYear('entry_date', $year)->join('accounts', 'accounts.id', '=', 'entries.account_id')->whereIn('accounts.type', ['income', 'expense'])
            ->selectRaw('MONTH(entry_date) m, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) inc, SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) exp')
            ->groupBy('m')->get()->keyBy('m');

        return [
            'year' => $year,
            'rows' => $rows,
            'income' => $income, 'expense' => $expense, 'result' => $income - $expense,
            'budget_result' => $rows->where('type', 'income')->sum('budget') - $rows->where('type', 'expense')->sum('budget'),
            'balance' => (int) Entry::where('entry_date', '<=', Carbon::create($year)->endOfYear())->sum('amount'),
            'receivables' => (int) Invoice::where('status', 'open')->sum('amount'),
            'monthly' => collect(range(1, 12))->map(fn ($m) => ['m' => $m, 'income' => (int) ($monthly[$m]->inc ?? 0), 'expense' => (int) ($monthly[$m]->exp ?? 0)]),
            'activities' => Entry::whereYear('entry_date', $year)->whereNotNull('activity_id')->groupBy('activity_id')
                ->selectRaw('activity_id, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) inc, SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) exp')->get()
                ->map(fn ($x) => ['id' => $x->activity_id, 'title' => Activity::find($x->activity_id)?->title, 'income' => (int) $x->inc, 'expense' => (int) $x->exp]),
        ];
    }

    public function saveBudget(Request $r)
    {
        $this->write($r);
        $d = $r->validate(['year' => 'required|integer', 'items' => 'array', 'items.*.account_id' => 'exists:accounts,id', 'items.*.budget' => 'integer']);
        foreach ($d['items'] as $i) {
            Budget::updateOrCreate(['year' => $d['year'], 'account_id' => $i['account_id']], ['amount' => $i['budget']]);
        }
        Audit::log('budget.saved', null, ['year' => $d['year']]);

        return ['ok' => true];
    }

    private function entryQuery(Request $r)
    {
        $q = Entry::with('account', 'creator', 'activity', 'reversedBy', 'payment')->orderByDesc('entry_date')->orderByDesc('id');
        $q->whereYear('entry_date', (int) $r->query('year', now()->year));
        if ($a = $r->query('account_id')) $q->where('account_id', $a);
        if ($s = $r->query('q')) $q->where('text', 'like', "%$s%");

        return $q;
    }

    public function entries(Request $r)
    {
        $this->read($r);

        return $this->entryQuery($r)->limit(1000)->get()->map(fn ($e) => [
            'id' => $e->id, 'date' => $e->entry_date->toDateString(), 'text' => $e->text,
            'account' => $e->account->number.' '.$e->account->name, 'amount' => $e->amount,
            'activity' => $e->activity?->title, 'auto' => (bool) $e->payment_id,
            'attachment' => (bool) $e->attachment_path, 'attachment_name' => $e->attachment_name,
            'by' => $e->creator?->name ?? 'System', 'created_at' => $e->created_at->toIso8601String(),
            'reverses_id' => $e->reverses_id, 'reversed' => (bool) $e->reversedBy,
        ]);
    }

    public function exportEntries(Request $r)
    {
        $this->read($r);

        return Csv::download('posteringer-'.$r->query('year', now()->year).'.csv',
            ['Nr', 'Dato', 'Tekst', 'Konto', 'Beløb', 'Aktivitet', 'Bilag', 'Indtastet af', 'Modpostering af'],
            $this->entryQuery($r)->get()->map(fn ($e) => [
                $e->id, $e->entry_date->format('d-m-Y'), $e->text, $e->account->number.' '.$e->account->name,
                number_format($e->amount / 100, 2, ',', ''), $e->activity?->title, $e->attachment_name, $e->creator?->name ?? 'System', $e->reverses_id,
            ]));
    }

    public function storeEntry(Request $r)
    {
        $this->write($r);
        $d = $r->validate([
            'entry_date' => 'required|date', 'text' => 'required|string|max:200', 'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|integer|not_in:0', 'activity_id' => 'nullable|exists:activities,id',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,heic|max:10240',
        ]);
        $acc = Account::find($d['account_id']);
        // Brugeren indtaster et positivt beløb; fortegn følger kontotypen
        $amount = abs($d['amount']) * ($acc->type === 'expense' ? -1 : 1);
        $entry = new Entry(['entry_date' => $d['entry_date'], 'text' => $d['text'], 'account_id' => $acc->id, 'amount' => $amount, 'activity_id' => $d['activity_id'] ?? null, 'created_by' => $r->user()->id]);
        if ($f = $r->file('attachment')) {
            $entry->attachment_path = $f->store('bilag');
            $entry->attachment_name = $f->getClientOriginalName();
        }
        $entry->save();
        Audit::log('entry.created', $entry, ['amount' => $amount, 'text' => $d['text']]);

        return ['id' => $entry->id];
    }

    /** Rettelser sker ved modpostering – intet slettes. */
    public function reverseEntry(Request $r, Entry $entry)
    {
        $this->write($r);
        abort_if($entry->reversedBy || $entry->reverses_id, 422, 'Posteringen er allerede udlignet.');
        $reason = $r->validate(['reason' => 'required|string|max:200'])['reason'];
        $rev = Entry::create([
            'entry_date' => now()->toDateString(), 'text' => 'Modpostering af #'.$entry->id.': '.$reason,
            'account_id' => $entry->account_id, 'amount' => -$entry->amount, 'activity_id' => $entry->activity_id,
            'reverses_id' => $entry->id, 'created_by' => $r->user()->id,
        ]);
        Audit::log('entry.reversed', $entry, ['reversal_id' => $rev->id, 'reason' => $reason]);

        return ['id' => $rev->id];
    }

    public function attachment(Request $r, Entry $entry)
    {
        $this->read($r);
        abort_unless($entry->attachment_path && Storage::exists($entry->attachment_path), 404);

        return Storage::download($entry->attachment_path, $entry->attachment_name);
    }

    /** Bankafstemning: upload CSV fra banken (dato;tekst;beløb) og match mod posteringer på beløb og dato (±3 dage). */
    public function reconcile(Request $r)
    {
        $this->read($r);
        $r->validate(['file' => 'required|file|max:2048']);
        $lines = preg_split('/\r?\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $r->file('file')->get())));
        $used = [];
        $out = [];
        foreach ($lines as $i => $line) {
            $cols = str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', '');
            if (count($cols) < 3) continue;
            try {
                $date = Carbon::createFromFormat(str_contains($cols[0], '-') && strlen(explode('-', $cols[0])[0]) === 4 ? 'Y-m-d' : 'd-m-Y', str_replace('.', '-', trim($cols[0])));
            } catch (\Throwable) {
                continue; // overskrift o.l.
            }
            $amount = (int) round((float) str_replace(',', '.', str_replace('.', '', trim($cols[2]))) * 100);
            $match = Entry::where('amount', $amount)->whereBetween('entry_date', [$date->copy()->subDays(3), $date->copy()->addDays(3)])
                ->whereNotIn('id', $used)->orderByRaw('ABS(DATEDIFF(entry_date, ?))', [$date->toDateString()])->first();
            if ($match) $used[] = $match->id;
            $out[] = ['date' => $date->toDateString(), 'text' => $cols[1], 'amount' => $amount,
                'match' => $match ? ['id' => $match->id, 'date' => $match->entry_date->toDateString(), 'text' => $match->text] : null];
        }
        $from = collect($out)->min('date');
        $to = collect($out)->max('date');
        $unmatchedEntries = $from ? Entry::whereBetween('entry_date', [$from, $to])->whereNotIn('id', $used)->get()
            ->map(fn ($e) => ['id' => $e->id, 'date' => $e->entry_date->toDateString(), 'text' => $e->text, 'amount' => $e->amount]) : [];

        return ['lines' => $out, 'unmatched_entries' => $unmatchedEntries];
    }

    public function auditLog(Request $r)
    {
        $this->read($r);

        return AuditLog::with('user')->latest('id')->limit(300)->get()->map(fn ($l) => [
            'id' => $l->id, 'at' => $l->created_at->toIso8601String(), 'by' => $l->user?->name ?? 'System',
            'action' => $l->action, 'subject' => $l->subject_type ? $l->subject_type.' #'.$l->subject_id : null, 'changes' => $l->changes,
        ]);
    }

    public function membershipTypes(Request $r)
    {
        return MembershipType::all()->map(fn ($t) => $t->only('id', 'name', 'code', 'annual_fee', 'max_age') + [
            'fee_fmt' => Money::fmt($t->annual_fee),
            'prorated' => \App\Support\Memberships::prorate($t->annual_fee),
            'prorated_fmt' => Money::fmt(\App\Support\Memberships::prorate($t->annual_fee)),
            'months_left' => \App\Support\Memberships::monthsLeft(),
        ]);
    }
}
