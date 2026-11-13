<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Entry;
use App\Models\ExpenseClaim;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Udlæg: alle medlemmer kan indsende en kvittering for et udlæg og vælge, om pengene skal
 * sendes via MobilePay eller til en bankkonto. Kassereren bogfører udbetalingen (kvitteringen
 * bliver bilag) eller afviser med en begrundelse. Medlemmet får besked i begge tilfælde.
 */
class ExpenseClaimController extends Controller
{
    /** Medlemmets egne udlæg + forslag til MobilePay-nummer. */
    public function mine(Request $r)
    {
        $u = $r->user();

        return [
            'suggested_phone' => self::normalizePhone($u->phone),
            'claims' => ExpenseClaim::where('user_id', $u->id)->latest()->limit(50)->get()->map(fn ($c) => $this->row($c)),
        ];
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'description' => 'required|string|max:1000',
            'amount' => 'required|integer|min:100|max:5000000', // øre: 1 kr – 50.000 kr
            'payout_method' => 'required|in:mobilepay,bank',
            'mobilepay_phone' => 'required_if:payout_method,mobilepay|nullable|string|max:20',
            'bank_reg' => ['required_if:payout_method,bank', 'nullable', 'regex:/^\d{4}$/'],
            'bank_account' => ['required_if:payout_method,bank', 'nullable', 'regex:/^\d{1,10}$/'],
            'file' => 'required|file|mimes:jpg,jpeg,png,heic,webp,pdf|max:15360',
        ], [
            'bank_reg.regex' => 'Reg. nr. skal være 4 cifre.',
            'bank_account.regex' => 'Kontonummer skal være op til 10 cifre.',
            'file.required' => 'Vedhæft et billede af kvitteringen.',
            'amount.min' => 'Beløbet skal være mindst 1 kr.',
        ], ['description' => 'beskrivelse', 'amount' => 'beløb', 'mobilepay_phone' => 'MobilePay-nummer', 'bank_reg' => 'reg. nr.', 'bank_account' => 'kontonummer', 'file' => 'kvittering']);

        $phone = null;
        if ($d['payout_method'] === 'mobilepay') {
            $phone = self::normalizePhone($d['mobilepay_phone']);
            abort_unless($phone, 422, 'MobilePay-nummeret skal være et dansk mobilnummer på 8 cifre.');
        }

        $f = $r->file('file');
        $claim = ExpenseClaim::create([
            'user_id' => $r->user()->id,
            'description' => $d['description'],
            'amount' => $d['amount'],
            'payout_method' => $d['payout_method'],
            'mobilepay_phone' => $phone,
            'bank_reg' => $d['payout_method'] === 'bank' ? $d['bank_reg'] : null,
            'bank_account' => $d['payout_method'] === 'bank' ? str_pad($d['bank_account'], 10, '0', STR_PAD_LEFT) : null,
            'file_path' => $f->store('udlaeg'),
            'file_name' => $f->getClientOriginalName() ?: 'kvittering',
            'file_mime' => $f->getMimeType() ?: 'application/octet-stream',
            'requires_approval' => $r->user()->may('finance.write'),
        ]);
        Audit::log('expense_claim.submitted', $claim, ['amount' => $claim->amount]);

        // Kassererens eget udlæg går først til revisoren; andre udlæg går til kassereren
        $to = self::withRole($claim->requires_approval ? 'auditor' : 'treasurer')->where('id', '!=', $r->user()->id)->get();
        Notification::send($to, new ClubMessage(
            ($claim->requires_approval ? 'Udlæg til godkendelse: ' : 'Nyt udlæg: ').Money::fmt($claim->amount),
            $r->user()->name.' har indsendt en kvittering: '.str($claim->description)->limit(80).'.'
                .($claim->requires_approval ? ' Kassererens egne udlæg skal godkendes af revisoren.' : ''),
            '/okonomi/udlaeg'
        ));

        return $this->row($claim);
    }

    /** Kvitteringen (billede/PDF). Kræver signeret link, som kun ejeren og økonomiadgang får. */
    public function file(ExpenseClaim $claim)
    {
        abort_unless(Storage::exists($claim->file_path), 404);

        return response(Storage::get($claim->file_path), 200, [
            'Content-Type' => $claim->file_mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($claim->file_name).'"',
            'Cache-Control' => 'private, max-age=1800',
        ]);
    }

    /* ---------- Kasserer ---------- */

    /** Udgiftskontoen fra det senest bogførte udlæg – foreslås, når næste udlæg bogføres. */
    public function lastAccount(Request $r)
    {
        abort_unless($r->user()->may('finance.read'), 403);

        return ['account_id' => Entry::whereIn('id', ExpenseClaim::where('status', 'paid')->whereNotNull('entry_id')->select('entry_id'))->latest('id')->value('account_id')];
    }

    public function index(Request $r)
    {
        abort_unless($r->user()->may('finance.read'), 403);
        $status = $r->query('status', 'submitted');

        return ExpenseClaim::with('user', 'handler', 'approver')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy($status === 'submitted' ? 'created_at' : 'handled_at', $status === 'submitted' ? 'asc' : 'desc')
            ->limit(200)->get()->map(fn ($c) => $this->row($c, true));
    }

    /** Bogfør udlægget som udbetalt: udgiftspostering med kvitteringen som bilag. */
    public function pay(Request $r, ExpenseClaim $claim)
    {
        abort_unless($r->user()->may('finance.write'), 403, 'Du har ikke adgang til at bogføre udlæg.');
        abort_unless($claim->status === 'submitted', 422, 'Udlægget er allerede behandlet.');
        abort_if($claim->awaitingApproval(), 422, 'Kassererens eget udlæg skal godkendes af revisoren, før det kan bogføres.');
        $d = $r->validate([
            'account_id' => 'required|exists:accounts,id',
            'entry_date' => 'required|date',
            'amount' => 'required|integer|min:1',
            'text' => 'nullable|string|max:200',
        ]);
        $acc = Account::findOrFail($d['account_id']);
        abort_unless($acc->type === 'expense', 422, 'Vælg en udgiftskonto.');

        DB::transaction(function () use ($r, $d, $acc, $claim) {
            $entry = Entry::create([
                'entry_date' => $d['entry_date'],
                'text' => $d['text'] ?: 'Udlæg '.$claim->user->name.': '.str($claim->description)->limit(120),
                'account_id' => $acc->id, 'amount' => -abs($d['amount']),
                'attachment_path' => $claim->file_path, 'attachment_name' => $claim->file_name,
                'created_by' => $r->user()->id,
            ]);
            $claim->update(['status' => 'paid', 'handled_by' => $r->user()->id, 'handled_at' => now(), 'entry_id' => $entry->id, 'amount' => abs($d['amount'])]);
            Audit::log('expense_claim.paid', $claim, ['entry_id' => $entry->id, 'amount' => $d['amount']]);
        });

        $claim->user->notify(new ClubMessage(
            'Udlæg udbetalt: '.Money::fmt($claim->amount),
            'Dit udlæg ('.str($claim->description)->limit(60).') er udbetalt til '.$claim->payoutLabel().'.',
            '/profil/kvitteringer'
        ));

        return $this->row($claim->fresh(['user', 'handler', 'approver']), true);
    }

    /** Revisoren godkender kassererens eget udlæg. Derefter kan det bogføres som andre udlæg. */
    public function approve(Request $r, ExpenseClaim $claim)
    {
        $u = $r->user();
        abort_unless($u->may('claims.approve'), 403, 'Du har ikke adgang til at godkende kassererens udlæg.');
        abort_unless($claim->awaitingApproval(), 422, 'Udlægget venter ikke på godkendelse.');
        abort_if($claim->user_id === $u->id, 422, 'Du kan ikke godkende dit eget udlæg.');
        $claim->update(['approved_by' => $u->id, 'approved_at' => now()]);
        Audit::log('expense_claim.approved', $claim);
        $claim->user->notify(new ClubMessage(
            'Udlæg godkendt af revisor',
            'Dit udlæg på '.Money::fmt($claim->amount).' ('.str($claim->description)->limit(60).') er godkendt af '.$u->name.' og kan nu bogføres og udbetales.',
            '/okonomi/udlaeg'
        ));

        return $this->row($claim->fresh(['user', 'handler', 'approver']), true);
    }

    /** Kassereren afviser andres udlæg; revisoren afviser kassererens egne. */
    public function reject(Request $r, ExpenseClaim $claim)
    {
        $u = $r->user();
        abort_unless($claim->status === 'submitted', 422, 'Udlægget er allerede behandlet.');
        abort_if($claim->user_id === $u->id, 422, 'Du kan ikke afvise dit eget udlæg.');
        abort_unless($claim->requires_approval ? $u->may('claims.approve') : $u->may('finance.write'), 403,
            $claim->requires_approval ? 'Kassererens udlæg afvises af revisoren.' : 'Kun kassereren kan afvise udlæg.');
        $reason = $r->validate(['reason' => 'required|string|max:300'])['reason'];
        $claim->update(['status' => 'rejected', 'handled_by' => $r->user()->id, 'handled_at' => now(), 'reject_reason' => $reason]);
        Audit::log('expense_claim.rejected', $claim, ['reason' => $reason]);
        $claim->user->notify(new ClubMessage(
            'Udlæg afvist',
            'Dit udlæg på '.Money::fmt($claim->amount).' ('.str($claim->description)->limit(60).') er afvist. Begrundelse: '.$reason,
            '/profil/kvitteringer'
        ));

        return $this->row($claim->fresh(['user', 'handler', 'approver']), true);
    }

    private function row(ExpenseClaim $c, bool $staff = false): array
    {
        return [
            'id' => $c->id,
            'description' => $c->description,
            'amount' => $c->amount,
            'amount_fmt' => Money::fmt($c->amount),
            'payout_method' => $c->payout_method,
            'payout_label' => $c->payoutLabel(),
            'mobilepay_phone' => $c->mobilepay_phone,
            'bank_reg' => $c->bank_reg,
            'bank_account' => $c->bank_account,
            'status' => $c->status,
            'requires_approval' => $c->requires_approval,
            'awaiting_approval' => $c->awaitingApproval(),
            'approved_at' => $c->approved_at?->toIso8601String(),
            'reject_reason' => $c->reject_reason,
            'created_at' => $c->created_at->toIso8601String(),
            'handled_at' => $c->handled_at?->toIso8601String(),
            'file_name' => $c->file_name,
            'is_pdf' => $c->file_mime === 'application/pdf',
            'file_url' => URL::temporarySignedRoute('expense.file', now()->addMinutes(60), ['claim' => $c->id]),
        ] + ($staff ? [
            'member' => $c->user->name,
            'member_email' => $c->user->email,
            'handled_by' => $c->handler?->name,
            'approved_by' => $c->approver?->name,
            'is_mine' => $c->user_id === auth()->id(),
            'entry_id' => $c->entry_id,
        ] : []);
    }

    private static function withRole(string $role)
    {
        return User::whereIn('id', RoleAssignment::where('role', $role)->where('starts_on', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()))->select('user_id'));
    }

    /** Dansk mobilnummer: 8 cifre (tillader +45 og mellemrum). Ellers null. */
    public static function normalizePhone(?string $p): ?string
    {
        $d = preg_replace('/\D/', '', (string) $p);
        if (strlen($d) === 10 && str_starts_with($d, '45')) $d = substr($d, 2);
        if (strlen($d) === 12 && str_starts_with($d, '0045')) $d = substr($d, 4);

        return strlen($d) === 8 ? $d : null;
    }
}
