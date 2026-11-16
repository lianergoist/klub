<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Klubbens design: de værdier, der må ændres i SurfHorsens-temaet (det tema, Automatisk bruger).
 * Kun farver, logo, skrift og hjørner – aldrig layout – så siderne virker på mobil og pc uanset valg.
 * Afledte farver (kanter, dæmpet tekst, knaptekst) beregnes i resources/js/design.js.
 */
class Design
{
    public const COLORS = ['bg', 'surface', 'ink', 'heading', 'accent', 'brand', 'orange'];
    public const FONTS = ['poppins', 'system'];

    public static function rules(): array
    {
        $r = ['data' => 'required|array', 'data.radius' => 'required|integer|min:0|max:20', 'data.font' => 'required|in:'.implode(',', self::FONTS),
            'data.logo' => ['nullable', 'string', 'regex:#^design/logo-[0-9a-f-]{36}\.png$#']];
        foreach (['light', 'dark'] as $m) {
            foreach (self::COLORS as $c) $r["data.$m.$c"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $r;
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

    public static function published(): ?object
    {
        return DB::table('design_versions')->where('status', 'published')->orderByDesc('id')->first();
    }

    /** Det, alle enheder henter (offentligt, også før login). */
    public static function current(): array
    {
        return Cache::rememberForever('design_current', function () {
            $p = self::published();
            if (! $p) return ['version' => null, 'data' => null];
            $d = json_decode($p->data, true);

            return ['version' => $p->id, 'data' => $d, 'logo_url' => $d['logo'] ? self::logoUrl($d['logo']) : null];
        });
    }

    public static function forget(): void { Cache::forget('design_current'); }

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
