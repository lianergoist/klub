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
 *
 * Personer, der er kommet til efter udgangspunktet (rigtige indmeldelser, gæster), beholder deres økonomi:
 * regninger, betalinger, posteringer, køb, tilkøb og udlæg følger med over i den nulstillede økonomi (se keep()).
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
        $keep = self::keep($base);
        $kept = array_sum(array_map('count', $keep['tables']));
        if ($kept) $this->line('Bevares for '.count($keep['users']).' nye personer ('.implode(', ', $keep['names']).'): '.$kept.' rækker.');
        if (! $this->option('force') && ! $this->confirm('Nulstil al økonomi til '.$base['saved_at'].'? Alt andet bogført, købt og betalt siden fjernes.', true)) {
            return self::FAILURE;
        }

        // Sikkerhedskopi af den nuværende økonomi
        $backup = storage_path(self::DIR.'/okonomi-foer-nulstilling-'.now()->format('Ymd-His').'.json');
        self::save($backup);

        $removedRegs = 0;
        DB::transaction(function () use ($base, $keep, &$removedRegs) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (self::TABLES as $t) DB::table($t)->delete();
                foreach (self::TABLES as $t) {
                    $cols = Schema::getColumnListing($t);
                    foreach (array_chunk($base['tables'][$t] ?? [], 200) as $chunk) {
                        // Samme felter i alle rækker: felter, der er kommet til efter udgangspunktet, bliver tomme
                        DB::table($t)->insert(array_map(fn ($row) => array_intersect_key($row, array_flip($cols)) + array_fill_keys($cols, null), $chunk));
                    }
                }
                // De nye personers økonomi lægges oven i (findes rækken i udgangspunktet, vinder den nuværende udgave)
                DB::table('purchase_user')->whereIn('purchase_id', array_column($keep['tables']['purchases'], 'id'))->delete();
                foreach ($keep['tables'] as $t => $rows) {
                    $key = $t === 'purchase_user' ? null : 'id';
                    foreach ($rows as $row) {
                        if ($key) DB::table($t)->where($key, $row[$key])->delete();
                        DB::table($t)->insert($row);
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
        Audit::log('finance.demo_reset', null, ['baseline' => $base['saved_at'], 'backup' => basename($backup), 'registrations_removed' => $removedRegs, 'kept_for' => $keep['names']]);

        $this->info('Økonomien er nulstillet til '.$base['saved_at'].'.');
        foreach (self::TABLES as $t) $this->line(sprintf('  %-16s %5d rækker', $t, DB::table($t)->count()));
        if ($kept) $this->line('  Heraf bevaret for nye personer: '.collect($keep['tables'])->map(fn ($r, $t) => count($r).' '.$t)->filter(fn ($x) => ! str_starts_with($x, '0 '))->implode(', ').'.');
        if ($removedRegs) $this->line("  $removedRegs betalte tilmeldinger fra efter udgangspunktet er fjernet.");
        $this->line('Sikkerhedskopi af økonomien før nulstillingen: '.$backup);

        return self::SUCCESS;
    }

    public static function save(string $path): void
    {
        @mkdir(dirname($path), 0775, true);
        $data = ['saved_at' => now()->format('Y-m-d H:i'), 'migration' => DB::table('migrations')->max('migration'),
            'max_user_id' => (int) DB::table('users')->max('id'), 'tables' => []];
        foreach (self::TABLES as $t) $data['tables'][$t] = DB::table($t)->orderBy(Schema::hasColumn($t, 'id') ? 'id' : 'purchase_id')->get()->map(fn ($r) => (array) $r)->all();
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Økonomien for personer, der er kommet til efter udgangspunktet (id over udgangspunktets højeste, eller oprettet
     * efter det for ældre udgangspunkter uden max_user_id). Rækkerne hentes fra den nuværende database.
     * @return array{users: int[], names: string[], tables: array<string, array[]>}
     */
    public static function keep(array $base): array
    {
        $users = DB::table('users')
            ->when(isset($base['max_user_id']), fn ($q) => $q->where('id', '>', $base['max_user_id']), fn ($q) => $q->where('created_at', '>', $base['saved_at']))
            ->get(['id', 'name']);
        $ids = $users->pluck('id')->all();
        $out = ['users' => $ids, 'names' => $users->pluck('name')->all(), 'tables' => array_fill_keys(self::TABLES, [])];
        if (! $ids) return $out;
        $rows = fn ($q) => $q->get()->map(fn ($r) => (array) $r)->all();

        $purchases = DB::table('purchases')->where(fn ($q) => $q->whereIn('payer_id', $ids)
            ->orWhereExists(fn ($q) => $q->from('purchase_user')->whereColumn('purchase_user.purchase_id', 'purchases.id')->whereIn('purchase_user.user_id', $ids)))->get();
        // Et skift af medlemskab, der erstattede et ældre køb: det ældre køb tages med i sin nuværende udgave (afsluttet/annulleret)
        $replaced = DB::table('purchases')->whereIn('id', $purchases->pluck('replaces_id')->filter())->get();
        $purchases = $purchases->merge($replaced)->unique('id');
        $invoiceIds = DB::table('invoices')->where(fn ($q) => $q->whereIn('payer_id', $ids)->orWhereIn('member_id', $ids))->pluck('id')
            ->merge($purchases->pluck('invoice_id')->filter())->unique()->values()->all();
        $payments = $rows(DB::table('payments')->whereIn('invoice_id', $invoiceIds));
        $baselinePayouts = array_column($base['tables']['payouts'] ?? [], 'id');
        foreach ($payments as &$p) if ($p['payout_id'] && ! in_array($p['payout_id'], $baselinePayouts)) $p['payout_id'] = null; // udbetalingen hentes igen
        unset($p);
        $claims = $rows(DB::table('expense_claims')->whereIn('user_id', $ids));
        $entryIds = DB::table('entries')->whereIn('payment_id', array_column($payments, 'id'))->pluck('id')
            ->merge(array_filter(array_column($claims, 'entry_id')))->all();
        $entryIds = array_merge($entryIds, DB::table('entries')->whereIn('reverses_id', $entryIds)->pluck('id')->all()); // modposteringer

        $out['tables'] = array_merge($out['tables'], [
            'invoices' => $rows(DB::table('invoices')->whereIn('id', $invoiceIds)),
            'payments' => $payments,
            'entries' => $rows(DB::table('entries')->whereIn('id', array_unique($entryIds))),
            'expense_claims' => $claims,
            'purchases' => $purchases->map(fn ($r) => (array) $r)->values()->all(),
            'purchase_user' => $rows(DB::table('purchase_user')->whereIn('purchase_id', $purchases->pluck('id'))),
            'product_orders' => $rows(DB::table('product_orders')->where(fn ($q) => $q->whereIn('invoice_id', $invoiceIds)->orWhereIn('user_id', $ids))),
        ]);

        return $out;
    }
}
