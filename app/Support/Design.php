<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Egne farvetemaer: farver (lys og mørk udgave), skrift og hjørner (logoet er altid klubbens). Aldrig layout – så siderne virker
 * på mobil og pc uanset valg. De indbyggede temaer i app.css kan ikke rettes; man gemmer en kopi som eget tema.
 * Afledte farver (kanter, dæmpet tekst, knaptekst) beregnes i resources/js/design.js.
 */
class Design
{
    public const COLORS = ['bg', 'surface', 'ink', 'heading', 'accent', 'brand', 'orange'];
    /** SurfHorsens' designguide: de eneste tilladte farver (digital, 100 %). Samme liste i resources/js/design.js (PALETTE). */
    public const PALETTE = ['#f8c760', '#f18975', '#f5b8d9', '#e668a3', '#2599be', '#78cadb', '#ff9832', '#7d598b', '#fffef0', '#acd3d2', '#64a3a2', '#575756'];
    /** Lyseblå og mint en anelse mod creme (knapper/links på grå i mørk udgave), som DARK_TONES i design.js */
    public const DARK_TONES = ['#9ed9e1', '#b1d6d4'];
    /** Designguiden: Futura (ellers Jost, der er tegnet efter Futura), eller Arial */
    public const FONTS = ['futura', 'arial', 'kombi'];
    public const STATUSES = ['available', 'development'];
    /** Navnene på de indbyggede temaer kan ikke bruges igen */
    public const BUILT_IN = ['Automatisk', 'SurfHorsens', 'SurfHorsens (Arial)', 'SurfHorsens kombi', 'SurfHorsens (mørk)', 'Hav', 'Hav (mørk)', 'Mint', 'Mint (mørk)', 'Solnedgang', 'Solnedgang (mørk)', 'Mørk'];

    public static function rules(?int $ignoreId = null): array
    {
        $r = [
            'name' => ['required', 'string', 'max:40', 'not_in:'.implode(',', self::BUILT_IN),
                \Illuminate\Validation\Rule::unique('design_themes', 'name')->ignore($ignoreId)],
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'data' => 'required|array', 'data.radius' => 'required|integer|min:0|max:20', 'data.font' => 'required|in:'.implode(',', self::FONTS),
        ];
        foreach (['light', 'dark'] as $m) {
            foreach (self::COLORS as $c) $r["data.$m.$c"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/', fn ($a, $v, $fail) => self::allowed($v) || $fail('Farven er ikke en af klubbens farver.')];
        }

        return $r;
    }

    public static function messages(): array
    {
        return ['name.unique' => 'Der findes allerede et tema med det navn.', 'name.not_in' => 'Navnet bruges af et indbygget tema. Vælg et andet.'];
    }

    /** Lys toning: 35 % paletfarve på creme (som TINTS i design.js) – til baggrunde */
    public static function tints(): array
    {
        $creme = self::rgb('#fffef0');
        $out = [];
        foreach (self::PALETTE as $h) {
            if ($h === '#fffef0') continue;
            $c = self::rgb($h);
            $out[] = '#'.implode('', array_map(fn ($i) => sprintf('%02x', (int) round($creme[$i] + ($c[$i] - $creme[$i]) * 0.35)), [0, 1, 2]));
        }

        return $out;
    }

    /** Farver, egne temaer må bruge: paletten og de lyse toninger */
    public static function allowed(?string $hex): bool
    {
        return in_array(strtolower((string) $hex), [...self::PALETTE, ...self::tints(), ...self::DARK_TONES], true);
    }

    /** Nærmeste tilladte farve (bruges, når ældre farver skal flyttes over på paletten) */
    public static function nearest(string $hex, ?array $among = null): string
    {
        $x = self::rgb($hex);
        $best = null; $bd = INF;
        foreach ($among ?? [...self::PALETTE, ...self::tints()] as $h) {
            $c = self::rgb($h);
            $d = ($x[0] - $c[0]) ** 2 * 2 + ($x[1] - $c[1]) ** 2 * 4 + ($x[2] - $c[2]) ** 2 * 3;
            if ($d < $bd) { $bd = $d; $best = $h; }
        }

        return $best;
    }

    private static function rgb(string $h): array
    {
        return [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))];
    }

    /** Kun de kendte nøgler gemmes. */
    public static function clean(array $d): array
    {
        $out = ['radius' => (int) $d['radius'], 'font' => $d['font']];
        foreach (['light', 'dark'] as $m) {
            foreach (self::COLORS as $c) $out[$m][$c] = strtolower($d[$m][$c]);
        }

        return $out;
    }

    public static function row(object $t): array
    {
        $d = json_decode($t->data, true);

        return ['id' => $t->id, 'key' => 'egen-'.$t->id, 'name' => $t->name, 'status' => $t->status, 'data' => $d];
    }

    /** Temaer, medlemmerne kan vælge (offentligt, også før login). */
    public static function available(): array
    {
        return Cache::rememberForever('design_themes', fn () => DB::table('design_themes')->where('status', 'available')->orderBy('name')->get()
            ->map(fn ($t) => self::row($t))->all());
    }

    /** Alle temaer, også dem under udvikling (til dem, der må rette design). */
    public static function all(): array
    {
        return DB::table('design_themes')->orderBy('name')->get()->map(fn ($t) => self::row($t))->all();
    }

    public static function forget(): void { Cache::forget('design_themes'); }
}
