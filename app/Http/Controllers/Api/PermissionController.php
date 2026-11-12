<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\Audit;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Indstillinger → Rettigheder (kun den, der har "roles.manage" – altid administrator).
 */
class PermissionController extends Controller
{
    private function guard(Request $r): void
    {
        abort_unless($r->user()->may('roles.manage'), 403, 'Kun administrator kan ændre rettigheder.');
    }

    public function index(Request $r)
    {
        $this->guard($r);
        $today = now()->toDateString();
        $grants = UserPermission::with('user', 'grantedBy')->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->orderBy('starts_on')->get()->groupBy('permission');

        return [
            'roles' => collect(Permissions::ROLES)->mapWithKeys(fn ($k) => [$k => User::ROLE_LABELS[$k]]),
            'permissions' => collect(Permissions::CATALOG)->map(fn ($c, $key) => [
                'key' => $key, 'group' => $c[0], 'label' => $c[1], 'description' => $c[2],
                'roles' => Permissions::rolesFor($key), 'default' => $c[3], 'locked' => Permissions::locked($key),
                'users' => ($grants[$key] ?? collect())->map(fn ($g) => [
                    'id' => $g->id, 'user_id' => $g->user_id, 'name' => $g->user->name, 'note' => $g->note,
                    'starts_on' => $g->starts_on->toDateString(), 'ends_on' => $g->ends_on?->toDateString(),
                ])->values(),
            ])->values(),
        ];
    }

    public function update(Request $r, string $permission)
    {
        $this->guard($r);
        abort_unless(Permissions::exists($permission), 404);
        abort_if(Permissions::locked($permission), 422, 'Denne rettighed er altid administratorens og kan ikke ændres.');
        $d = $r->validate(['roles' => 'present|array', 'roles.*' => ['string', Rule::in(Permissions::ROLES)]]);
        $before = Permissions::rolesFor($permission);
        Permissions::setRoles($permission, $d['roles']);
        Audit::log('permission.roles', null, ['permission' => $permission, 'before' => $before, 'after' => Permissions::rolesFor($permission)]);

        return ['roles' => Permissions::rolesFor($permission)];
    }

    public function grant(Request $r, User $member)
    {
        $this->guard($r);
        $d = $r->validate([
            'permission' => ['required', Rule::in(array_keys(Permissions::CATALOG))],
            'starts_on' => 'required|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on', 'note' => 'nullable|string|max:255',
        ]);
        abort_if(Permissions::locked($d['permission']), 422, 'Denne rettighed følger rollen Administrator. Tildel rollen i stedet.');
        $p = $member->userPermissions()->create($d + ['granted_by' => $r->user()->id]);
        Audit::log('permission.granted', $member, $d);

        return ['id' => $p->id];
    }

    public function end(Request $r, UserPermission $grant)
    {
        $this->guard($r);
        $d = $r->validate(['ends_on' => 'required|date']);
        // Slutter den, før den er begyndt, fjernes den helt
        $d['ends_on'] < $grant->starts_on->toDateString() ? $grant->delete() : $grant->update($d);
        Audit::log('permission.ended', $grant->user, ['permission' => $grant->permission] + $d);

        return ['ok' => true];
    }
}
