<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Sæt/skift/fjern den fælles adgangskode. Alle enheder skal indtaste den nye kode bagefter. */
class SiteCode extends Command
{
    protected $signature = 'site:code {code? : Ny kode (udelades = generér en)} {--off : Fjern adgangskoden}';

    protected $description = 'Sæt eller skift den fælles adgangskode til sitet';

    public function handle(): int
    {
        $env = base_path('.env');
        $content = file_get_contents($env);
        $value = '';
        if (! $this->option('off')) {
            $code = $this->argument('code') ?: $this->generate();
            $value = "'".password_hash($code, PASSWORD_DEFAULT)."'";
        }
        $line = 'SITE_ACCESS_HASH='.$value;
        $content = preg_match('/^SITE_ACCESS_HASH=.*$/m', $content)
            ? preg_replace('/^SITE_ACCESS_HASH=.*$/m', str_replace('$', '\$', $line), $content)
            : rtrim($content)."\n".$line."\n";
        file_put_contents($env, $content);
        $this->call('config:cache');

        $this->option('off') ? $this->warn('Adgangskoden er fjernet – sitet er åbent.') : $this->info("Ny adgangskode: $code");

        return self::SUCCESS;
    }

    private function generate(): string
    {
        $words = ['bølge', 'brise', 'finne', 'bom', 'mast', 'sejl', 'luv', 'læ', 'kuling', 'strand', 'foil', 'wing'];

        return $words[random_int(0, 11)].'-'.$words[random_int(0, 11)].'-'.random_int(100, 999);
    }
}
