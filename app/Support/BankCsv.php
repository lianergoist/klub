<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Læser kontoudtog som CSV fra netbanken – robust over for tegnsæt (UTF-8, Windows-1252, UTF-16), skilletegn
 * (; , tabulator), overskrifter og ekstra kolonner. Gætter, hvilke kolonner der er dato, tekst og beløb;
 * brugeren kan vælge andre.
 */
class BankCsv
{
    /** @return array{columns: string[], rows: array<int, string[]>, header: bool, encoding: string, delimiter: string} */
    public static function read(string $raw): array
    {
        [$text, $encoding] = self::decode($raw);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $delimiter = self::delimiter($text);

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        $rows = [];
        while (($cols = fgetcsv($fh, null, $delimiter, '"', '')) !== false) {
            $cols = array_map(fn ($c) => trim((string) $c), $cols);
            if (count(array_filter($cols, fn ($c) => $c !== '')) === 0) continue;
            $rows[] = $cols;
        }
        fclose($fh);

        $width = $rows ? max(array_map('count', $rows)) : 0;
        $rows = array_map(fn ($r) => array_pad($r, $width, ''), $rows);

        // Første linje er en overskrift, hvis ingen af dens felter er en dato eller et beløb
        $header = $rows && ! collect($rows[0])->contains(fn ($c) => self::date($c) || ($c !== '' && self::amount($c) !== null));
        $columns = $header ? array_map(fn ($c, $i) => $c !== '' ? $c : 'Kolonne '.($i + 1), $rows[0], array_keys($rows[0]))
            : array_map(fn ($i) => 'Kolonne '.($i + 1), range(0, max(0, $width - 1)));
        if ($header) array_shift($rows);

        return ['columns' => $width ? $columns : [], 'rows' => $rows, 'header' => $header, 'encoding' => $encoding, 'delimiter' => $delimiter];
    }

    private static function decode(string $raw): array
    {
        if (str_starts_with($raw, "\xFF\xFE")) return [mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE'), 'UTF-16'];
        if (str_starts_with($raw, "\xFE\xFF")) return [mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE'), 'UTF-16'];
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (mb_check_encoding($raw, 'UTF-8')) return [$raw, 'UTF-8'];

        // Danske banker og Excel gemmer ofte i Windows-1252 (æøå som enkelte bytes)
        return [mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'), 'Windows-1252'];
    }

    /** Det skilletegn, der giver flest og mest ens antal kolonner i de første linjer. */
    private static function delimiter(string $text): string
    {
        $lines = array_slice(array_values(array_filter(explode("\n", $text), fn ($l) => trim($l) !== '')), 0, 15);
        $best = ';'; $score = -1;
        foreach ([';', "\t", ',', '|'] as $d) {
            $counts = array_map(fn ($l) => count(str_getcsv($l, $d, '"', '')), $lines);
            if (! $counts || max($counts) < 2) continue;
            $common = array_count_values($counts);
            arsort($common);
            $width = array_key_first($common);
            $s = $common[$width] * 10 + $width;
            if ($s > $score) { $score = $s; $best = $d; }
        }

        return $best;
    }

    public static function date(string $s): ?Carbon
    {
        $s = trim(preg_replace('/[T ]\d{1,2}:\d{2}(:\d{2})?.*$/', '', trim($s)));
        if (! preg_match('#^(\d{1,4})[-./](\d{1,2})[-./](\d{1,4})$#', $s, $m)) return null;
        [$a, $b, $c] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        [$y, $mo, $d] = strlen($m[1]) === 4 ? [$a, $b, $c] : [$c < 100 ? 2000 + $c : $c, $b, $a];
        if (! checkdate($mo, $d, $y) || $y < 1990 || $y > 2100) return null;

        return Carbon::create($y, $mo, $d)->startOfDay();
    }

    /** Beløb i øre: "1.234,56", "-1234.56", "1 234,56 kr.", "1234,56-", "(125,00)". null hvis det ikke er et beløb. */
    public static function amount(string $s): ?int
    {
        $s = str_replace(["\u{00A0}", "\u{202F}", ' ', "'"], '', trim($s));
        $s = preg_replace('/(kr\.?|dkk)$/i', '', preg_replace('/^(kr\.?|dkk)/i', '', $s));
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $s, $m)) { $s = $m[1]; $neg = true; }
        if (str_ends_with($s, '-')) { $s = substr($s, 0, -1); $neg = true; }
        if (str_starts_with($s, '+')) $s = substr($s, 1);
        if (str_starts_with($s, '-')) { $s = substr($s, 1); $neg = ! $neg; }
        if (! preg_match('/^\d[\d.,]*$/', $s)) return null;

        $comma = strrpos($s, ','); $dot = strrpos($s, '.');
        if ($comma !== false && $dot !== false) {
            $dec = max($comma, $dot);
            $s = str_replace(['.', ','], '', substr($s, 0, $dec)).'.'.substr($s, $dec + 1);
        } elseif ($comma !== false) {
            $s = substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($dot !== false && (substr_count($s, '.') > 1 || preg_match('/^\d{1,3}\.\d{3}$/', $s))) {
            $s = str_replace('.', '', $s); // tusindtalsseparator
        }
        if (! is_numeric($s)) return null;
        $v = (int) round((float) $s * 100);

        return $neg ? -$v : $v;
    }

    /** Gæt kolonnerne for dato, tekst og beløb ud fra overskrifter og indhold. */
    public static function guess(array $columns, array $rows): array
    {
        $sample = array_slice($rows, 0, 30);
        $n = max(1, count($sample));
        $share = fn (int $i, callable $f) => count(array_filter($sample, fn ($r) => $f($r[$i] ?? ''))) / $n;
        $head = fn (int $i) => mb_strtolower($columns[$i] ?? '');
        $idx = array_keys($columns);

        $find = function (array $words, array $avoid, callable $ok) use ($idx, $head) {
            foreach ($words as $w) {
                foreach ($idx as $i) {
                    $h = $head($i);
                    if (str_contains($h, $w) && ! collect($avoid)->contains(fn ($a) => str_contains($h, $a)) && $ok($i)) return $i;
                }
            }

            return null;
        };

        $isDate = fn ($i) => $share($i, fn ($c) => self::date($c) !== null) >= 0.6;
        $isAmount = fn ($i) => $share($i, fn ($c) => $c !== '' && self::amount($c) !== null) >= 0.6;

        $date = $find(['bogføringsdato', 'bogført', 'dato', 'date'], ['valør', 'rente', 'value'], $isDate)
            ?? collect($idx)->first($isDate);
        $amount = $find(['beløb', 'belob', 'amount', 'bevægelse'], ['saldo', 'balance'], fn ($i) => $i !== $date && $isAmount($i))
            ?? collect($idx)->first(fn ($i) => $i !== $date && $isAmount($i) && ! str_contains($head($i), 'saldo') && ! str_contains($head($i), 'balance'))
            ?? collect($idx)->first(fn ($i) => $i !== $date && $isAmount($i));
        $text = $find(['tekst', 'text', 'beskrivelse', 'posteringstekst', 'modtager', 'description'], [], fn ($i) => $i !== $date && $i !== $amount)
            ?? collect($idx)->filter(fn ($i) => $i !== $date && $i !== $amount)
                ->sortByDesc(fn ($i) => array_sum(array_map(fn ($r) => self::amount($r[$i] ?? '') === null ? mb_strlen($r[$i] ?? '') : 0, $sample)))
                ->first();

        return ['date' => $date, 'text' => $text, 'amount' => $amount];
    }
}
