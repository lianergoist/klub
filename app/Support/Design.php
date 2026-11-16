<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Egne farvetemaer: farver (lys og mørk udgave), logo, skrift og hjørner. Aldrig layout – så siderne virker
 * på mobil og pc uanset valg. De indbyggede temaer i app.css kan ikke rettes; man gemmer en kopi som eget tema.
 * Afledte farver (kanter, dæmpet tekst, knaptekst) beregnes i resources/js/design.js.
 */
class Design
{
    public const COLORS = ['bg', 'surface', 'ink', 'heading', 'accent', 'brand', 'orange'];
    public const FONTS = ['poppins', 'system'];
    public const STATUSES = ['available', 'development'];
    /** Navnene på de indbyggede temaer kan ikke bruges igen */
    public const BUILT_IN = ['Automatisk', 'SurfHorsens', 'Blå', 'Grøn', 'Orange', 'Mørk'];

    public static function rules(?int $ignoreId = null): array
    {
        $r = [
            'name' => ['required', 'string', 'max:40', 'not_in:'.implode(',', self::BUILT_IN),
                \Illuminate\Validation\Rule::unique('design_themes', 'name')->ignore($ignoreId)],
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'data' => 'required|array', 'data.radius' => 'required|integer|min:0|max:20', 'data.font' => 'required|in:'.implode(',', self::FONTS),
            'data.logo' => ['nullable', 'string', 'regex:#^design/logo-[0-9a-f-]{36}\.png$#'],
        ];
        foreach (['light', 'dark'] as $m) {
            foreach (self::COLORS as $c) $r["data.$m.$c"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $r;
    }

    public static function messages(): array
    {
        return ['name.unique' => 'Der findes allerede et tema med det navn.', 'name.not_in' => 'Navnet bruges af et indbygget tema. Vælg et andet.'];
    }

    /** Kun de kendte nøgler gemmes. */
    public static function clean(array $d): array
    {
        $out = ['radius' => (int) $d['radius'], 'font' => $d['font'], 'logo' => $d['logo'] ?? null];
        foreach (['light', 'dark'] as $m) {
            foreach (self::COLORS as $c) $out[$m][$c] = strtolower($d[$m][$c]);
        }
        if ($out['logo'] && ! Storage::exists($out['logo'])) $out['logo'] = null;

        return $out;
    }

    public static function row(object $t): array
    {
        $d = json_decode($t->data, true);

        return ['id' => $t->id, 'key' => 'egen-'.$t->id, 'name' => $t->name, 'status' => $t->status, 'data' => $d,
            'logo_url' => $d['logo'] ? self::logoUrl($d['logo']) : null];
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

    public static function logoUrl(string $path): string
    {
        return url('/api/design/logo/'.basename($path));
    }

    /** Logoet genkodes som PNG (bevarer gennemsigtighed, fjerner metadata) og skaleres til max 800 px. */
    public static function storeLogo(UploadedFile $file): string
    {
        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($src, 422, 'Logoet kunne ikke læses. Brug PNG, JPG eller WebP.');
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, 800 / max($w, $h));
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start(); imagepng($dst, null, 8); $png = ob_get_clean();
        $path = 'design/logo-'.Str::uuid().'.png';
        Storage::put($path, $png);

        return $path;
    }
}
