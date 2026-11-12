<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Klubbens priser med "gælder fra"-dato. En ny pris lægges ind som en ny række med en senere dato;
 * den gamle bevares, så man altid kan se, hvad der gjaldt hvornår.
 */
class Prices
{
    public const LABELS = [
        'membership.passive' => 'Støttemedlem, fuld sæson',
        'membership.single' => 'Enkelt medlem, fuld sæson',
        'membership.single_half' => 'Enkelt medlem, halv sæson (fra 1. august)',
        'membership.family3' => 'Familiekontingent, 3 personer i husstanden',
        'membership.family4' => 'Familiekontingent, 4 eller flere i husstanden',
        'rental.single' => 'Udstyrsleje i sæsonen, enkelt medlem',
        'rental.family' => 'Udstyrsleje i sæsonen, familie (op til 3 personer)',
        'rental.extra' => 'Udstyrsleje, pr. ekstra person ud over 3',
        'rental.day' => 'Dagsleje af board og sejl',
        'storage.rack' => 'Opbevaring: plads i boardrack, pr. sæson',
        'storage.locker' => 'Opbevaring: skab i container, pr. sæson',
        'key_deposit' => 'Depositum for nøglebrik',
    ];

    /** Grupper til visning */
    public const GROUPS = []; // tilbud til ikke-medlemmer er nu pakker (kind = offer) med egen pris

    private static array $cache = [];

    public static function get(string $code, $date = null): int
    {
        $d = \Illuminate\Support\Carbon::parse($date ?? now())->toDateString();
        $key = $code.'@'.$d;
        if (! array_key_exists($key, self::$cache)) {
            $v = DB::table('prices')->where('code', $code)->where('valid_from', '<=', $d)->orderByDesc('valid_from')->value('amount');
            abort_if($v === null, 500, "Prisen '$code' er ikke fastsat.");
            self::$cache[$key] = (int) $v;
        }

        return self::$cache[$key];
    }

    /**
     * Kontingent for en husstand med $n aktive (ikke-støtte) medlemmer.
     * 1–2 personer: enkeltkontingent pr. person (halv sæson fra 1. august). 3: familie3. 4+: familie4.
     */
    public static function membership(int $n, $date = null): int
    {
        if ($n <= 0) return 0;
        if ($n <= 2) return $n * self::get(Season::isHalf($date) ? 'membership.single_half' : 'membership.single', $date);

        return self::get($n === 3 ? 'membership.family3' : 'membership.family4', $date);
    }

    /** Udstyrsleje for $n personer i samme husstand. 1–2: enkeltpris pr. person. 3: familiepris. 4+: familiepris + ekstra pr. person. */
    public static function rental(int $n, $date = null): int
    {
        if ($n <= 0) return 0;
        if ($n <= 2) return $n * self::get('rental.single', $date);

        return self::get('rental.family', $date) + max(0, $n - 3) * self::get('rental.extra', $date);
    }

    /** Alle priser med historik, grupperet (til visning og redigering). */
    public static function table(): array
    {
        $rows = DB::table('prices')->leftJoin('users', 'users.id', '=', 'prices.created_by')
            ->select('prices.*', 'users.name as by')->orderByDesc('valid_from')->get();
        $today = now()->toDateString();
        $row = fn ($r) => ['id' => $r->id, 'amount' => (int) $r->amount, 'amount_fmt' => Money::fmt($r->amount),
            'valid_from' => $r->valid_from, 'by' => $r->by, 'created_at' => $r->created_at];
        $out = [];
        foreach (self::GROUPS as $group => $codes) {
            $items = [];
            foreach ($codes as $code) {
                $hist = $rows->where('code', $code)->values();
                $current = $hist->first(fn ($r) => $r->valid_from <= $today);
                $items[] = [
                    'code' => $code, 'label' => self::LABELS[$code],
                    'current' => $current ? $row($current) : null,
                    'upcoming' => $hist->filter(fn ($r) => $r->valid_from > $today)->sortBy('valid_from')->map($row)->values(),
                    'previous' => $hist->filter(fn ($r) => $r->valid_from <= $today && $r->id !== $current?->id)->map($row)->values(),
                ];
            }
            $out[] = ['group' => $group, 'items' => $items];
        }

        return $out;
    }

    /** Ny pris med virkning fra en dato (i dag eller senere). Samme kode og dato overskriver en kommende pris. */
    public static function set(string $code, int $amount, string $validFrom, ?int $userId): void
    {
        DB::table('prices')->updateOrInsert(['code' => $code, 'valid_from' => $validFrom],
            ['amount' => $amount, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        self::flush();
    }

    public static function flush(): void { self::$cache = []; }
}
