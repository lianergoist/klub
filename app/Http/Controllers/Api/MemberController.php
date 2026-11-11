<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    private function query(Request $r)
    {
        $year = (string) now()->year;
        $q = User::with('roleAssignments', 'household')->orderBy('name');

        if ($s = $r->query('q')) {
            $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"));
        }
        if ($st = $r->query('status')) {
            $q->where('status', $st);
        } else {
            $q->where('status', '!=', 'guest'); // gæster vises kun, når man vælger dem
        }
        if ($age = $r->query('age')) {
            $cut = now()->subYears(18)->toDateString();
            $age === 'adult' ? $q->where('birthdate', '<=', $cut) : $q->where('birthdate', '>', $cut);
        }
        if ($role = $r->query('role')) {
            $q->whereHas('roleAssignments', fn ($q) => $q->where('role', $role)->where('starts_on', '<=', now())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())));
        }
        if ($r->query('unpaid')) {
            $q->whereIn('status', ['active', 'passive']);
            $paidIds = User::all()->filter(fn ($u) => $u->hasValidMembership())->pluck('id');
            $q->whereNotIn('id', $paidIds);
        }

        return $q;
    }

    public function index(Request $r)
    {
        abort_unless($r->user()->canSeeMembers(), 403);

        return $this->query($r)->get()->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'age' => $u->age(), 'status' => $u->status, 'status_label' => User::STATUS_LABELS[$u->status],
            'type' => $u->currentMembership()?->package->name, 'roles' => array_values(array_diff($u->roles(), ['member'])),
            'household' => $u->household?->name, 'is_payer' => $u->household?->payer_id === $u->id,
            'paid' => $u->hasValidMembership(),
        ]);
    }

    public function export(Request $r)
    {
        abort_unless($r->user()->canSeeMembers(), 403);
        Audit::log('members.exported');
        $rows = $this->query($r)->get()->map(fn ($u) => [
            $u->name, $u->email, $u->phone, $u->address, $u->postal_code, $u->city, $u->birthdate?->toDateString(),
            User::STATUS_LABELS[$u->status], $u->currentMembership()?->package->name, $u->hasValidMembership() ? 'Betalt' : 'Ikke betalt',
            implode(', ', array_map(fn ($x) => User::ROLE_LABELS[$x], $u->roles())),
        ]);

        return Csv::download('medlemsliste.csv', ['Navn', 'E-mail', 'Telefon', 'Adresse', 'Postnr', 'By', 'Fødselsdato', 'Status', 'Medlemstype', 'Kontingent '.\App\Support\Season::label(), 'Roller'], $rows);
    }

    public function show(Request $r, User $member)
    {
        abort_unless($r->user()->canSeeMembers(), 403);
        $member->load('roleAssignments.grantedBy', 'household.members');
        Audit::log('member.viewed', $member);

        return [
            'profile' => ProfileController::profile($member) + ['consent_at' => $member->consent_at?->toIso8601String()],
            'household' => $member->household ? [
                'name' => $member->household->name,
                'payer' => $member->household->payer?->name,
                'members' => $member->household->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'age' => $m->age()]),
            ] : null,
            'roles' => $member->roleAssignments->sortByDesc('starts_on')->map(fn ($ra) => [
                'id' => $ra->id, 'role' => $ra->role, 'label' => User::ROLE_LABELS[$ra->role],
                'starts_on' => $ra->starts_on->toDateString(), 'ends_on' => $ra->ends_on?->toDateString(), 'granted_by' => $ra->grantedBy?->name,
            ])->values(),
            'registrations' => $member->registrations()->with('activity')->latest()->limit(20)->get()->map(fn ($reg) => [
                'activity_id' => $reg->activity_id, 'title' => $reg->activity->title, 'starts_at' => $reg->activity->starts_at->toIso8601String(),
                'status' => $reg->status, 'attended' => $reg->attended,
            ]),
            'invoices' => $r->user()->hasRole('treasurer', 'board', 'admin') ? Invoice::where('payer_id', $member->id)->orWhere('member_id', $member->id)->latest()->get()->map(fn ($i) => FinanceController::invoiceRow($i)) : [],
            'membership_valid' => $member->hasValidMembership(),
            'purchases' => $member->purchases()->with('package', 'invoice')->latest('valid_from')->limit(20)->get()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->package->name, 'kind' => $p->package->kind, 'valid_from' => $p->valid_from->toDateString(),
                'valid_to' => $p->valid_to?->toDateString(), 'status' => $p->cancelled_at ? 'cancelled' : ($p->invoice ? $p->invoice->displayStatus() : 'paid'),
            ]),
            'log' => \App\Models\AuditLog::with('user')->where('subject_type', 'User')->where('subject_id', $member->id)->latest('id')->limit(30)->get()
                ->map(fn ($l) => ['action' => $l->action, 'by' => $l->user?->name, 'at' => $l->created_at->toIso8601String()]),
        ];
    }

    public function approve(Request $r, User $member)
    {
        abort_unless($r->user()->hasRole('board', 'treasurer', 'admin'), 403);
        abort_unless($member->status === 'pending', 422, 'Medlemmet er allerede godkendt.');
        $member->update(['status' => 'active', 'joined_on' => now()]);
        Audit::log('member.approved', $member);
        $member->payer()->notify(new ClubMessage('Velkommen i klubben!', $member->name.' er nu godkendt som medlem. Kontingentet bliver opkrævet snarest.', '/profil'));

        return ['ok' => true];
    }

    public function update(Request $r, User $member)
    {
        abort_unless($r->user()->hasRole('admin'), 403);
        $d = $r->validate([
            'name' => 'required|string|max:120', 'email' => 'nullable|email|unique:users,email,'.$member->id,
            'phone' => 'nullable|string', 'address' => 'nullable|string', 'postal_code' => 'nullable|string', 'city' => 'nullable|string',
            'birthdate' => 'nullable|date', 'status' => 'required|in:pending,active,passive,resigned',
            'emergency_name' => 'nullable|string', 'emergency_phone' => 'nullable|string',
        ]);
        $before = $member->only(array_keys($d));
        if ($d['status'] === 'resigned' && $member->status !== 'resigned') {
            $d['resigned_on'] = now();
        }
        $member->update($d);
        Audit::log('member.updated', $member, ['before' => $before, 'after' => $member->only(array_keys($before))]);

        return ['ok' => true];
    }

    public function addRole(Request $r, User $member)
    {
        abort_unless($r->user()->hasRole('admin'), 403, 'Kun administrator kan tildele roller.');
        $d = $r->validate(['role' => 'required|in:trainer,board,treasurer,auditor,admin', 'starts_on' => 'required|date']);
        $ra = $member->roleAssignments()->create($d + ['granted_by' => $r->user()->id]);
        Audit::log('role.granted', $member, $d);

        return ['id' => $ra->id];
    }

    public function endRole(Request $r, RoleAssignment $role)
    {
        abort_unless($r->user()->hasRole('admin'), 403, 'Kun administrator kan fjerne roller.');
        $d = $r->validate(['ends_on' => 'required|date']);
        $role->update($d);
        Audit::log('role.ended', $role->user, ['role' => $role->role] + $d);

        return ['ok' => true];
    }
}
