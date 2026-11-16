<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Billeder til hjemmesiden: roteres efter kameraets EXIF og gemmes som WebP i to størrelser
 * (højst 2000 px bred + en udgave på 900 px til mobil). Genkodningen fjerner al metadata (fx GPS-position).
 * Ligger i public/hjemmeside/uploads (ikke i git); klubbens egne billeder fra konverteringen i public/hjemmeside/billeder.
 */
class SiteImages
{
    public const DIRS = ['billeder', 'uploads'];

    public static function store(UploadedFile $file): string
    {
        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($src, 422, 'Billedet kunne ikke læses. Brug JPG, PNG eller WebP.');
        $orientation = 1;
        if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'])) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        }
        $src = match ($orientation) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };

        $rel = 'hjemmeside/uploads/'.now()->format('Y/m').'/'.Str::lower(Str::random(16));
        @mkdir(public_path(dirname($rel)), 0775, true);
        self::save($src, 2000, public_path($rel.'.webp'));
        self::save($src, 900, public_path($rel.'-900.webp'));

        return '/'.$rel.'.webp';
    }

    private static function save(\GdImage $src, int $max, string $path): void
    {
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, $max / $w);
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true); // gennemsigtige PNG'er (fx logoer) bevares
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($dst, $path, 80);
    }

    /** Billeder, man kan vælge imellem (nyeste uploads først) */
    public static function library(): array
    {
        $out = [];
        foreach (array_reverse(self::DIRS) as $dir) {
            $base = public_path('hjemmeside/'.$dir);
            if (! is_dir($base)) continue;
            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $f) {
                $name = $f->getFilename();
                if (! str_ends_with($name, '.webp') || preg_match('/-\d+\.webp$/', $name) || $name === 'favicon.webp') continue;
                $rel = '/hjemmeside/'.$dir.'/'.str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
                $files[] = ['src' => $rel, 'thumb' => is_file(substr($f->getPathname(), 0, -5).'-900.webp') ? substr($rel, 0, -5).'-900.webp' : (is_file(substr($f->getPathname(), 0, -5).'-600.webp') ? substr($rel, 0, -5).'-600.webp' : $rel),
                    'uploaded' => $dir === 'uploads', 't' => $f->getMTime()];
            }
            usort($files, fn ($a, $b) => $dir === 'uploads' ? $b['t'] <=> $a['t'] : strcmp($a['src'], $b['src']));
            array_push($out, ...$files);
        }

        return array_map(fn ($f) => array_diff_key($f, ['t' => 1]), $out);
    }
}
