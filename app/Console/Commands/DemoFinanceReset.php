<?php

namespace App\Console\Commands;

use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KUN DEMO: nulstiller økonomien til tilstanden lige efter seneste fulde nulstilling (migrate:fresh --seed),
 * uden at røre medlemmer, aktiviteter, opslag, udstyr m.m.
 *
 *   php artisan demo:nulstil-okonomi          lægger øjebliksbilledet tilbage
 *   php artisan demo:nulstil-okonomi --gem    gemmer et nyt øjebliksbillede (gøres automatisk af seederen)
 *
 * Økonomi = regninger, betalinger, posteringer, MobilePay-udbetalinger, budget, udlæg samt køb af medlemskaber
 * og tilkøb (de hænger sammen med regningerne). Priser og kontoplan er opsætning og røres ikke.
 */
class DemoFinanceReset extends Command
{
    protected $signature = 'demo:nulstil-okonomi {--gem : Gem nuværende økonomi som udgangspunkt} {--force : Spørg ikke}';

    protected $description = 'Demo: nulstil kun økonomidata til tilstanden efter seneste fulde nulstilling';

    public const TABLES = ['invoices', 'payments', 'payouts', 'entries', 'budgets', 'expense_claims', 'purchases', 'purchase_user', 'product_orders'];

    private const DIR = 'app/private/demo';

    public static function baselinePath(): string
    {
        return storage_path(self::DIR.'/okonomi-udgangspunkt.json');
    }

    public function handle(): int
    {
        if (! config('app.demo')) {
            $this->error('Kun tilladt i demo-tilstand (APP_DEMO=true).');

            return self::FAILURE;
        }
        if ($this->option('gem')) {
            self::save(self::baselinePath());
            $this->info('Udgangspunkt gemt: '.self::baselinePath());

            return self::SUCCESS;
        }

        if (! is_file(self::baselinePath())) {
            $this->error('Der er intet udgangspunkt. Kør en fuld nulstilling (migrate:fresh --seed) eller demo:nulstil-okonomi --gem.');

            return self::FAILURE;
        }
        $base = json_decode(file_get_contents(self::baselinePath()), true);
        $migration = DB::table('migrations')->max('migration');
        if (($base['migration'] ?? null) !== $migration) {
            $this->error("Databasen er migreret siden udgangspunktet blev gemt ({$base['migration']} → $migration). Kør en fuld nulstilling i stedet.");

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Nulstil al økonomi til '.$base['saved_at'].'? Alt bogført, købt og betalt siden fjernes.', true)) {
            return self::FAILURE;
        }

        // Sikkerhedskopi af den nuværende økonomi
        $backup = storage_path(self::DIR.'/okonomi-foer-nulstilling-'.now()->format('Ymd-His').'.json');
        self::save($backup);

        $removedRegs = 0;
        DB::transaction(function () use ($base, &$removedRegs) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (self::TABLES as $t) DB::table($t)->delete();
                foreach (self::TABLES as $t) {
                    $cols = Schema::getColumnListing($t);
                    foreach (array_chunk($base['tables'][$t] ?? [], 200) as $chunk) {
                        DB::table($t)->insert(array_map(fn ($row) => array_intersect_key($row, array_flip($cols)), $chunk));
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            // Tilmeldinger, der blev betalt (regning eller klip) efter udgangspunktet, fjernes – deres betaling findes ikke længere
            $removedRegs = DB::table('registrations')->whereNotNull('invoice_id')->whereNotExists(fn ($q) => $q->from('invoices')->whereColumn('invoices.id', 'registrations.invoice_id'))->delete()
                + DB::table('registrations')->whereNotNull('purchase_id')->whereNotExists(fn ($q) => $q->from('purchases')->whereColumn('purchases.id', 'registrations.purchase_id'))->delete();
        });
        Cache::flush();
        Audit::log('finance.demo_reset', null, ['baseline' => $base['saved_at'], 'backup' => basename($backup), 'registrations_removed' => $removedRegs]);

        $this->info('Økonomien er nulstillet til '.$base['saved_at'].'.');
        foreach (self::TABLES as $t) $this->line(sprintf('  %-16s %5d rækker', $t, count($base['tables'][$t] ?? [])));
        if ($removedRegs) $this->line("  $removedRegs betalte tilmeldinger fra efter udgangspunktet er fjernet.");
        $this->line('Sikkerhedskopi af økonomien før nulstillingen: '.$backup);

        return self::SUCCESS;
    }

    public static function save(string $path): void
    {
        @mkdir(dirname($path), 0775, true);
        $data = ['saved_at' => now()->format('Y-m-d H:i'), 'migration' => DB::table('migrations')->max('migration'), 'tables' => []];
        foreach (self::TABLES as $t) $data['tables'][$t] = DB::table($t)->orderBy(Schema::hasColumn($t, 'id') ? 'id' : 'purchase_id')->get()->map(fn ($r) => (array) $r)->all();
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
