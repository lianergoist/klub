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
 * Indstillinger → Design. Man arbejder i én fælles kladde, ser den i forhåndsvisningen og udgiver den.
 * Hver udgivelse gemmes som en version, der kan hentes tilbage i kladden.
 */
class DesignController extends Controller
{
    /** Offentlig: det udgivne design (null = standarddesignet i app.css). */
    public function show()
    {
        return response()->json(Design::current())->header('Cache-Control', 'public, max-age=60');
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
        $draft = DB::table('design_versions')->where('status', 'draft')->first();
        $pub = Design::published();
        $names = User::whereIn('id', DB::table('design_versions')->whereNotNull('created_by')->pluck('created_by'))->pluck('name', 'id');
        $row = fn ($v) => $v ? ['id' => $v->id, 'data' => json_decode($v->data, true), 'by' => $names[$v->created_by] ?? null,
            'at' => ($v->published_at ?? $v->updated_at) ? \Illuminate\Support\Carbon::parse($v->published_at ?? $v->updated_at)->toIso8601String() : null] : null;
        $withLogo = function ($v) { if ($v && $v['data']['logo']) $v['logo_url'] = Design::logoUrl($v['data']['logo']); return $v; };

        return [
            'draft' => $withLogo($row($draft)),
            'published' => $withLogo($row($pub)),
            'history' => DB::table('design_versions')->where('status', 'published')->orderByDesc('id')->limit(20)->get()->map(fn ($v) => $withLogo($row($v))),
        ];
    }

    public function saveDraft(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $data = Design::clean($r->validate(Design::rules())['data']);
        $id = DB::table('design_versions')->where('status', 'draft')->value('id');
        $row = ['data' => json_encode($data), 'created_by' => $r->user()->id, 'updated_at' => now()];
        $id ? DB::table('design_versions')->where('id', $id)->update($row)
            : DB::table('design_versions')->insert($row + ['status' => 'draft', 'created_at' => now()]);

        return ['ok' => true, 'data' => $data];
    }

    /** Fortryd kladden (den næste visning tager udgangspunkt i det udgivne design). */
    public function discardDraft(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        DB::table('design_versions')->where('status', 'draft')->delete();

        return ['ok' => true];
    }

    public function uploadLogo(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $r->validate(['logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:5120']);
        $path = Design::storeLogo($r->file('logo'));

        return ['logo' => $path, 'logo_url' => Design::logoUrl($path)];
    }

    public function publish(Request $r)
    {
        abort_unless($r->user()->may('design.manage'), 403);
        $data = Design::clean($r->validate(Design::rules())['data']);
        $old = Design::published();
        $id = DB::table('design_versions')->insertGetId(['status' => 'published', 'data' => json_encode($data), 'created_by' => $r->user()->id,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('design_versions')->where('status', 'draft')->delete();
        Design::forget();
        Audit::log('design.published', null, ['fra' => $old ? 'version '.$old->id : 'standarddesign', 'til' => 'version '.$id]);

        return Design::current();
    }
}
