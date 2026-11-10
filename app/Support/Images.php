<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Billeder til opslagstavlen: roteres efter kameraets EXIF, skaleres til max 1600 px
 * og gemmes som ny JPEG. Genkodningen fjerner al metadata (fx GPS-position).
 */
class Images
{
    public static function store(UploadedFile $file, string $dir = 'feed', int $max = 1600): array
    {
        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($src, 422, 'Billedet kunne ikke læses. Brug JPG, PNG eller WebP.');

        $orientation = 1;
        if (function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'])) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        }
        $src = match ($orientation) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };

        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // gennemsigtig PNG -> hvid baggrund
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start(); imagejpeg($dst, null, 82); $jpg = ob_get_clean();
        $path = $dir.'/'.now()->format('Y/m').'/'.Str::uuid().'.jpg';
        Storage::put($path, $jpg);

        return ['path' => $path, 'width' => $nw, 'height' => $nh];
    }
}
