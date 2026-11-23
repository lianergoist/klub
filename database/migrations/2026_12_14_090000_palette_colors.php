<?php

use App\Support\Design;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SurfHorsens' designguide: egne temaer og aktivitetskategorier må kun bruge klubbens farver.
 * Ældre farver flyttes til den nærmeste tilladte; skrifterne Poppins/enhedens egen bliver Futura/Arial.
 */
return new class extends Migration
{
    // Demokategoriernes gamle farver → en paletfarve, der ligner (resten: nærmeste paletfarve)
    private const CATEGORY = ['#0e7490' => '#2599be', '#b45309' => '#ff9832', '#15803d' => '#64a3a2', '#7c3aed' => '#7d598b', '#be185d' => '#e668a3', '#778899' => '#575756'];

    public function up(): void
    {
        foreach (DB::table('categories')->get(['id', 'color']) as $c) {
            $old = strtolower((string) $c->color);
            if (in_array($old, Design::PALETTE, true)) continue;
            $new = self::CATEGORY[$old] ?? (preg_match('/^#[0-9a-f]{6}$/', $old) ? Design::nearest($old, Design::PALETTE) : '#2599be');
            DB::table('categories')->where('id', $c->id)->update(['color' => $new]);
        }
        DB::statement("ALTER TABLE categories ALTER color SET DEFAULT '#2599be'");

        foreach (DB::table('design_themes')->get(['id', 'data']) as $t) {
            $d = json_decode($t->data, true);
            foreach (['light', 'dark'] as $m) {
                foreach (Design::COLORS as $k) {
                    $v = strtolower($d[$m][$k] ?? '#575756');
                    $d[$m][$k] = Design::allowed($v) ? $v : Design::nearest($v);
                }
            }
            $d['font'] = ($d['font'] ?? '') === 'system' ? 'arial' : (in_array($d['font'] ?? '', Design::FONTS, true) ? $d['font'] : 'futura');
            DB::table('design_themes')->where('id', $t->id)->update(['data' => json_encode($d)]);
        }
        Design::forget();
    }

    public function down(): void {}
};
