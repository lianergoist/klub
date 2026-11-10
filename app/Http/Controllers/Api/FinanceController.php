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
use App\Models\Payment;
use App\Models\Payout;
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

    /** Opkræv kontingent for en træningssæson (1. maj – 30. april). year = sæsonens startår. */
    public function generateMembership(Request $r)
    {
        $this->write($r);
        $d = $r->validate(['year' => 'required|integer|min:2020|max:2100']);

        return \App\Support\Memberships::generateSeason((int) $d['year']);
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

    /**
     * Bankafstemning: upload CSV fra banken (dato;tekst;beløb).
     * 1) Linjer der matcher en MobilePay-udbetaling (udbyderens id i teksten, eller samme nettobeløb ±3 dage) afstemmes mod
     *    hele udbetalingen – dvs. alle betalinger i den og gebyrposteringen.
     * 2) Øvrige linjer matches mod enkeltposteringer (samme beløb, ±3 dage).
     * Posteringer der hører til MobilePay-/kortbetalinger afstemmes kun via udbetalinger.
     */
    public function reconcile(Request $r)
    {
        $this->read($r);
        $r->validate(['file' => 'required|file|max:2048']);
        $persist = $r->user()->canWriteFinance();
        $lines = preg_split('/\r?\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $r->file('file')->get())));

        // Posteringer der afstemmes via udbetalinger (ikke enkeltvis)
        $viaPayout = Entry::whereIn('payment_id', Payment::whereIn('method', \App\Support\MobilePay\Settlements::METHODS)->select('id'))
            ->pluck('id')->merge(Payout::whereNotNull('fee_entry_id')->pluck('fee_entry_id'))->all();

        $usedEntries = []; $usedPayouts = []; $out = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', '');
            if (count($cols) < 3) continue;
            try {
                $date = Carbon::createFromFormat(str_contains($cols[0], '-') && strlen(explode('-', $cols[0])[0]) === 4 ? 'Y-m-d' : 'd-m-Y', str_replace('.', '-', trim($cols[0])));
            } catch (\Throwable) {
                continue; // overskrift o.l.
            }
            $text = trim($cols[1]);
            $amount = (int) round((float) str_replace(',', '.', str_replace('.', '', trim($cols[2]))) * 100);
            $row = ['date' => $date->toDateString(), 'text' => $text, 'amount' => $amount, 'match' => null];

            $payout = Payout::whereNotIn('id', $usedPayouts)->get()->first(fn ($p) => str_contains($text, $p->provider_payout_id))
                ?? Payout::whereNotIn('id', $usedPayouts)->where('net', $amount)
                    ->whereBetween('payout_date', [$date->copy()->subDays(3), $date->copy()->addDays(3)])
                    ->orderByRaw('ABS(DATEDIFF(payout_date, ?))', [$date->toDateString()])->first();
            if ($payout && $payout->net === $amount) {
                $usedPayouts[] = $payout->id;
                if ($persist && ! $payout->bank_matched_at) $payout->update(['bank_matched_at' => now(), 'bank_text' => $text]);
                $row['match'] = ['type' => 'payout', 'id' => $payout->id, 'date' => $payout->payout_date->toDateString(),
                    'text' => 'MobilePay-udbetaling '.$payout->provider_payout_id.' – '.$payout->payment_count.($payout->payment_count === 1 ? ' betaling' : ' betalinger').', gebyrer '.Money::fmt($payout->fees)];
            } else {
                $m = Entry::where('amount', $amount)->whereBetween('entry_date', [$date->copy()->subDays(3), $date->copy()->addDays(3)])
                    ->whereNotIn('id', array_merge($usedEntries, $viaPayout))->orderByRaw('ABS(DATEDIFF(entry_date, ?))', [$date->toDateString()])->first();
                if ($m) {
                    $usedEntries[] = $m->id;
                    $row['match'] = ['type' => 'entry', 'id' => $m->id, 'date' => $m->entry_date->toDateString(), 'text' => $m->text];
                }
            }
            $out[] = $row;
        }

        $from = collect($out)->min('date');
        $to = collect($out)->max('date');
        $unmatchedEntries = $from ? Entry::whereBetween('entry_date', [$from, $to])->whereNotIn('id', array_merge($usedEntries, $viaPayout))
            ->where('account_id', '!=', self::accountId('9000'))->get()
            ->map(fn ($e) => ['id' => $e->id, 'date' => $e->entry_date->toDateString(), 'text' => $e->text, 'amount' => $e->amount]) : [];
        $unmatchedPayouts = $from ? Payout::whereBetween('payout_date', [$from, $to])->whereNotIn('id', $usedPayouts)->get()
            ->map(fn ($p) => ['id' => $p->id, 'date' => $p->payout_date->toDateString(), 'ref' => $p->provider_payout_id, 'net' => $p->net, 'count' => $p->payment_count]) : [];

        return ['lines' => $out, 'unmatched_entries' => $unmatchedEntries, 'unmatched_payouts' => $unmatchedPayouts, 'saved' => $persist];
    }

    /* ---------- MobilePay-udbetalinger ---------- */

    public function payouts(Request $r)
    {
        $this->read($r);
        $year = (int) $r->query('year', now()->year);
        $rows = Payout::whereYear('payout_date', $year)->orderByDesc('payout_date')->get();
        $awaiting = Payment::whereNull('payout_id')->whereIn('method', \App\Support\MobilePay\Settlements::METHODS);

        return [
            'payouts' => $rows->map(fn ($p) => [
                'id' => $p->id, 'ref' => $p->provider_payout_id, 'date' => $p->payout_date->toDateString(),
                'count' => $p->payment_count, 'gross' => $p->gross, 'fees' => $p->fees, 'net' => $p->net,
                'matched' => (bool) $p->bank_matched_at, 'bank_text' => $p->bank_text,
            ]),
            'totals' => ['gross' => $rows->sum('gross'), 'fees' => $rows->sum('fees'), 'net' => $rows->sum('net'),
                'unmatched' => $rows->whereNull('bank_matched_at')->count()],
            'awaiting' => ['count' => (clone $awaiting)->count(), 'amount' => (int) (clone $awaiting)->sum('amount')],
            'fee_percent' => \App\Support\MobilePay\SimulatedReport::FEE_PERCENT,
        ];
    }

    public function payout(Request $r, Payout $payout)
    {
        $this->read($r);

        return $payout->payments()->with('invoice.payer')->orderBy('created_at')->get()->map(fn ($p) => [
            'id' => $p->id, 'reference' => $p->reference, 'at' => $p->created_at->toIso8601String(),
            'description' => $p->invoice?->description, 'payer' => $p->invoice?->payer?->name,
            'amount' => $p->amount, 'fee' => $p->fee, 'method' => Payments::methodLabel($p->method),
        ]);
    }

    public function fetchPayouts(Request $r)
    {
        $this->write($r);

        return \App\Support\MobilePay\Settlements::fetchAndImport($r->user()->id);
    }

    public function auditLog(Request $r)
    {
        $this->read($r);

        return AuditLog::with('user')->latest('id')->limit(300)->get()->map(fn ($l) => [
            'id' => $l->id, 'at' => $l->created_at->toIso8601String(), 'by' => $l->user?->name ?? 'System',
            'action' => $l->action, 'subject' => $l->subject_type ? $l->subject_type.' #'.$l->subject_id : null, 'changes' => $l->changes,
        ]);
    }

    /** Til indmeldelse og visning: medlemstyper og aktuelle priser. */
    public function membershipTypes(Request $r)
    {
        $half = \App\Support\Season::isHalf();
        $P = fn ($c) => \App\Support\Prices::get($c);

        return [
            'season' => \App\Support\Season::label(),
            'season_end' => \App\Support\Season::end()->toDateString(),
            'half' => $half,
            'single' => ['price' => $P($half ? 'membership.single_half' : 'membership.single'), 'full' => $P('membership.single')],
            'passive' => ['price' => $P('membership.passive')],
            'family3' => $P('membership.family3'),
            'family4' => $P('membership.family4'),
        ];
    }

    /** Pristabel med "gælder fra" (læsning for alle i bestyrelse/økonomi). */
    public function prices(Request $r)
    {
        abort_unless($r->user()->canReadFinance() || $r->user()->isBoard(), 403);

        return \App\Support\Prices::table();
    }
}
