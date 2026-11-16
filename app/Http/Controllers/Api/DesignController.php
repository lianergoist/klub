<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Design;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Indstillinger → Design: egne farvetemaer. Man åbner et tema (en kopi, hvis det er indbygget), retter og gemmer det –
 * eller gemmer det under et nyt navn. Status styrer, om medlemmerne kan vælge det, eller om det stadig er under udvikling.
 */
class DesignController extends Controller
{
    /** Offentlig: temaer, man kan vælge. Dem, der må rette design, får også temaer under udvikling med. */
    public function show()
    {
        $u = auth('sanctum')->user();
        $themes = $u?->may('design.manage') ? Design::all() : Design::available();

        return response()->json(['themes' => $themes])->header('Cache-Control', 'no-cache');
    }

    public function logo(string $file)
    {
        abort_unless(preg_match('/^logo-[0-9a-f-]{36}\.png$/', $file), 404);
        $path = 'design/'.$file;
        abort_unless(Storage::exists($path), 404);

        // Filnavnet ændres ved hver upload, så filen kan caches længe
        return response(Storage::get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public function admin(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $rows = DB::table('design_themes')->orderBy('name')->get();
        $names = User::whereIn('id', $rows->pluck('updated_by')->filter())->pluck('name', 'id');

        return ['themes' => $rows->map(fn ($t) => Design::row($t) + ['by' => $names[$t->updated_by] ?? null,
            'updated_at' => \Illuminate\Support\Carbon::parse($t->updated_at)->toIso8601String()])];
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $v = $r->validate(Design::rules(), Design::messages());
        $id = DB::table('design_themes')->insertGetId(['name' => trim($v['name']), 'status' => $v['status'], 'data' => json_encode(Design::clean($v['data'])),
            'created_by' => $r->user()->id, 'updated_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Design::forget();
        Audit::log('design.theme_created', null, ['tema' => trim($v['name']), 'status' => $v['status']]);

        return Design::row(DB::table('design_themes')->find($id));
    }

    public function update(Request $r, int $id)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $old = DB::table('design_themes')->find($id);
        abort_unless($old, 404, 'Temaet findes ikke længere.');
        $v = $r->validate(Design::rules($id), Design::messages());
        DB::table('design_themes')->where('id', $id)->update(['name' => trim($v['name']), 'status' => $v['status'], 'data' => json_encode(Design::clean($v['data'])),
            'updated_by' => $r->user()->id, 'updated_at' => now()]);
        Design::forget();
        Audit::log('design.theme_updated', null, array_filter(['tema' => trim($v['name']), 'navn_før' => $old->name !== trim($v['name']) ? $old->name : null,
            'status' => $old->status !== $v['status'] ? $old->status.' → '.$v['status'] : null]));

        return Design::row(DB::table('design_themes')->find($id));
    }

    public function destroy(Request $r, int $id)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $t = DB::table('design_themes')->find($id);
        abort_unless($t, 404, 'Temaet findes ikke længere.');
        DB::table('design_themes')->where('id', $id)->delete();
        Design::forget();
        Audit::log('design.theme_deleted', null, ['tema' => $t->name]);

        return ['ok' => true];
    }

    public function uploadLogo(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $r->validate(['logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:5120']);
        $path = Design::storeLogo($r->file('logo'));

        return ['logo' => $path, 'logo_url' => Design::logoUrl($path)];
    }
}
