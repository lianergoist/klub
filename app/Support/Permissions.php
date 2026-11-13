<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Alle handlinger, der kræver en rolle. Admin styrer under Indstillinger → Rettigheder, hvilke roller hver
 * handling kræver, og kan give enkelte medlemmer en rettighed (fx en kasserer-opgave) uden hele rollen.
 *
 * Nye handlinger tilføjes her med en standard; standarden gælder, indtil admin ændrer den.
 * "Medlem" kan ikke vælges som rolle: almindelige medlemmers adgang styres af medlemskabet, ikke her.
 */
class Permissions
{
    public const ROLES = ['trainer', 'board', 'treasurer', 'auditor', 'admin'];

    /** key => [gruppe, navn, forklaring, standardroller, låst] */
    public const CATALOG = [
        'activities.manage' => ['Aktiviteter', 'Oprette og rette aktiviteter',
            'Oprette, rette og aflyse aktiviteter, vælge trænere, registrere fremmøde og tilmelde/afmelde deltagere efter fristen.', ['trainer', 'board', 'admin']],
        'activities.take' => ['Aktiviteter', 'Være træner på aktiviteter',
            'Står på listen over trænere, kan melde sig som træner på en aktivitet og angive egen utilgængelighed.', ['trainer']],
        'trainers.portal' => ['Aktiviteter', 'Se trænerportalen',
            'Trænerportalen med "Mangler træner" og tilgængelighed for alle trænere.', ['trainer', 'board', 'admin']],
        'categories.manage' => ['Aktiviteter', 'Oprette og rette aktivitetskategorier',
            'Kategorier som SUP og windsurf, som medlemmer filtrerer og får notifikationer efter.', ['admin']],

        'equipment.manage' => ['Udstyr', 'Vedligeholde udstyrslisten',
            'Tilføje og rette udstyr, sætte stand (OK, skal serviceres, defekt) og arkivere kasseret udstyr.', ['trainer', 'board', 'admin']],
        'equipment.types' => ['Udstyr', 'Oprette og rette underkategorier af udstyr',
            'Underkategorier som sejl, master og boards under hver aktivitetskategori.', ['admin']],
        'equipment.book' => ['Udstyr', 'Booke udstyr',
            'Booke bestemte stykker udstyr i et tidsrum. Medlemmer med betalt udstyrsleje eller dagsleje kan altid booke.', ['trainer', 'board', 'admin']],
        'equipment.decide' => ['Udstyr', 'Svare på ønsker om udstyr på alle aktiviteter',
            'Godkende eller afvise ønsker om at låne udstyr, som en aktivitet har booket – også når man ikke selv er træner på aktiviteten.', ['board', 'admin']],
        'equipment.cancel_overdue' => ['Udstyr', 'Annullere en booking, når fristen for at flytte den er overskredet',
            'Har man bedt et medlem flytte en booking, som ens aktivitet skal bruge, og medlemmet ikke har svaret inden fristen (24 timer), kan man annullere bookingen. Gælder kun aktiviteter, hvor man er træner/tovholder eller selv har bedt om flytningen.', ['trainer', 'board', 'admin']],
        'equipment.cancel' => ['Udstyr', 'Annullere andres bookinger af udstyr',
            'Annullere andre medlemmers bookinger når som helst – også når de har sagt nej til at flytte.', ['board', 'admin']],

        'members.view' => ['Medlemmer', 'Se medlemskartoteket',
            'Se og eksportere alle medlemmers oplysninger, roller og regninger.', ['board', 'treasurer', 'admin']],
        'members.approve' => ['Medlemmer', 'Godkende nye medlemmer',
            'Godkende indmeldelser, der venter.', ['board', 'treasurer', 'admin']],
        'members.edit' => ['Medlemmer', 'Rette medlemsoplysninger',
            'Rette navn, kontaktoplysninger, status m.m. på andre medlemmer.', ['admin']],
        'roles.manage' => ['Medlemmer', 'Tildele roller og rettigheder',
            'Tildele og afslutte roller, give enkelte medlemmer rettigheder og ændre denne oversigt. Er altid administratorens, så ingen kan låse sig selv ude.', ['admin'], true],

        'feed.moderate' => ['Kommunikation', 'Moderere opslagstavlen',
            'Skjule og slette opslag og kommentarer og afvise anmeldelser.', ['trainer', 'board', 'admin']],
        'messages.staff' => ['Kommunikation', 'Skrive til alle medlemmer',
            'Kan skrive til alle voksne medlemmer – også dem, der har fravalgt beskeder – og kan ikke blokeres.', ['trainer', 'board', 'admin']],

        'finance.read' => ['Økonomi', 'Se regnskabet',
            'Læseadgang til regnskab, regninger, udlæg, budget og rapporter.', ['treasurer', 'auditor', 'board']],
        'finance.write' => ['Økonomi', 'Bogføre og rette i regnskabet',
            'Bogføre, modpostere, registrere betalinger, udbetale udlæg og lægge budget. Egne udlæg skal godkendes af en med "Godkende kassererens udlæg".', ['treasurer']],
        'claims.approve' => ['Økonomi', 'Godkende kassererens udlæg',
            'Godkende eller afvise udlæg, som en med bogføringsadgang selv har indsendt.', ['auditor']],
        'prices.edit' => ['Økonomi', 'Ændre priser',
            'Lægge nye priser ind på pakker og ikke-medlemspriser med virkning fra en dato.', ['board', 'treasurer', 'admin']],
        'packages.manage' => ['Økonomi', 'Oprette og rette medlemskaber og tilkøb',
            'Oprette, rette og arkivere pakker (medlemskaber, tilkøb, klippekort) og personkategorier.', ['board', 'admin']],
    ];

    public static function exists(string $p): bool { return isset(self::CATALOG[$p]); }

    public static function locked(string $p): bool { return (bool) (self::CATALOG[$p][4] ?? false); }

    /** Roller, der giver rettigheden (gemt opsætning, ellers standard). */
    public static function rolesFor(string $p): array
    {
        if (self::locked($p)) return self::CATALOG[$p][3];

        return self::map()[$p] ?? self::CATALOG[$p][3];
    }

    /** Gemte afvigelser fra standarden: permission => roller. */
    public static function map(): array
    {
        return Cache::rememberForever('permission_roles', fn () => DB::table('permission_roles')->pluck('roles', 'permission')
            ->map(fn ($r) => json_decode($r, true))->all());
    }

    public static function setRoles(string $p, array $roles): void
    {
        $roles = array_values(array_intersect(self::ROLES, $roles));
        DB::table('permission_roles')->updateOrInsert(['permission' => $p],
            ['roles' => json_encode($roles), 'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()]);
        Cache::forget('permission_roles');
    }

    public static function allows(User $u, string $p): bool
    {
        abort_unless(self::exists($p), 500, "Ukendt rettighed: $p");
        if (array_intersect(self::rolesFor($p), $u->roles())) return true;

        return in_array($p, $u->grantedPermissions(), true);
    }

    /** Subquery med id på alle, der har rettigheden i dag (via rolle eller personligt). */
    public static function userIds(string $p)
    {
        $today = now()->toDateString();
        $active = fn ($q) => $q->where('starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today));

        return RoleAssignment::whereIn('role', self::rolesFor($p) ?: ['-'])->where($active)->select('user_id')
            ->union(DB::table('user_permissions')->where('permission', $p)->where($active)->select('user_id'));
    }

    /** Til frontenden: alle rettigheder som { activities_manage: true, ... } */
    public static function flags(User $u): array
    {
        $out = [];
        foreach (array_keys(self::CATALOG) as $p) $out[str_replace('.', '_', $p)] = self::allows($u, $p);

        return $out;
    }

    public static function label(string $p): string { return self::CATALOG[$p][1] ?? $p; }
}
