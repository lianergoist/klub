<?php

namespace App\Support;

class Csv
{
    /** Semikolon + BOM, så Excel på dansk åbner filen korrekt. */
    public static function download(string $name, array $head, iterable $rows)
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head, ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
