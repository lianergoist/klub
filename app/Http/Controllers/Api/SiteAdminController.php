<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Package;
use App\Models\SitePage;
use App\Models\User;
use App\Support\Audit;
use App\Support\SiteBlocks;
use App\Support\SiteImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Indstillinger → Hjemmeside: editoren til klubbens offentlige sider.
 * Rettigheden "Redigere hjemmesiden" (site.edit) giver adgang til indholdet; administratoren kan desuden ændre layout,
 * slette blokke og rette sider markeret admin_only (Kalender). Hver udgivelse gemmes i historikken (site_page_versions).
 */
class SiteAdminController extends Controller
{
    public const KEEP_VERSIONS = 50;

    private function guard(Request $r): bool
    {
        abort_unless($r->user()->may('site.edit'), 403);

        return $r->user()->hasRole('admin');
    }

    private function editable(Request $r, int $id): array
    {
        $admin = $this->guard($r);
        $page = SitePage::findOrFail($id);
        abort_if($page->admin_only && ! $admin, 403, 'Kun administratoren kan rette denne side.');

        return [$page, $admin];
    }

    public function index(Request $r)
    {
        $admin = $this->guard($r);
        $pages = SitePage::orderBy('position')->get();
        $names = User::whereIn('id', $pages->pluck('updated_by')->filter())->pluck('name', 'id');

        return [
            'admin' => $admin,
            'pages' => $pages->map(fn (SitePage $p) => [
                'id' => $p->id, 'slug' => $p->slug, 'title' => $p->title, 'path' => $p->path(), 'admin_only' => $p->admin_only,
                'locked' => $p->admin_only && ! $admin,
                'updated_at' => $p->updated_at?->toIso8601String(), 'by' => $names[$p->updated_by] ?? null,
            ]),
        ];
    }

    public function show(Request $r, int $id)
    {
        [$page, $admin] = $this->editable($r, $id);

        return [
            // Alle felter med (også dem, der mangler i ældre blokke), så editoren altid har dem
            'page' => ['id' => $page->id, 'slug' => $page->slug, 'title' => $page->title, 'path' => $page->path(), 'blocks' => SiteBlocks::clean($page->blocks, [], true, false),
                'updated_at' => $page->updated_at?->toIso8601String()],
            'admin' => $admin,
            'options' => $this->options(),
        ];
    }

    /** Det, knapper og priser kan pege på */
    private function options(): array
    {
        $packages = Package::whereNull('archived_at')->orderBy('kind')->orderBy('position')->orderBy('id')->get();

        return [
            'pages' => SitePage::orderBy('position')->get(['slug', 'title'])->map(fn ($p) => ['value' => 'page:/'.$p->slug, 'label' => $p->slug === '' ? 'Forsiden' : $p->title])
                ->concat([
                    ['value' => 'signup', 'label' => 'Meld dig ind (indmeldelse)'],
                    ['value' => 'page:/klippekort', 'label' => 'Køb klippekort'],
                    ['value' => 'page:/prov', 'label' => 'Prøv det af (alle tilbud til ikke-medlemmer)'],
                    ['value' => 'page:/login', 'label' => 'Log ind'],
                ])->values(),
            'offers' => $packages->where('self_service', true)->where('kind', 'offer')->map(fn ($p) => ['value' => 'offer:'.$p->id, 'label' => $p->name])->values(),
            'activities' => Activity::where('on_website', true)->where('status', 'active')->whereNull('cancelled_at')->whereNull('archived_at')
                ->where('ends_at', '>', now())->orderBy('starts_at')->limit(80)->get(['id', 'title', 'starts_at'])
                ->map(fn ($a) => ['value' => 'activity:'.$a->id, 'label' => $a->title.' ('.$a->starts_at->locale('da')->isoFormat('D. MMM').')'])->values(),
            'prices' => $packages->where('self_service', true)->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'price' => \App\Support\Site::kr($p->price())])->values(),
        ];
    }

    /** Forhåndsvisning af blokke, der ikke er udgivet: den rigtige side med markering af blokkene */
    public function preview(Request $r, int $id)
    {
        [$page, $admin] = $this->editable($r, $id);
        $blocks = SiteBlocks::clean((array) $r->input('blocks', []), $page->blocks, $admin, false);
        $page->blocks = $blocks;

        return response()->json(['html' => app(\App\Http\Controllers\SiteController::class)->render($page, true)]);
    }

    public function update(Request $r, int $id)
    {
        [$page, $admin] = $this->editable($r, $id);
        $r->validate(['blocks' => 'required|array', 'updated_at' => 'nullable|string']);
        // En anden har udgivet siden, siden den blev åbnet
        if ($r->input('updated_at') && $page->updated_at && $r->input('updated_at') !== $page->updated_at->toIso8601String()) {
            $who = User::find($page->updated_by)?->name ?? 'en anden';
            abort(409, "Siden er udgivet af $who, efter du åbnede den. Hent den nyeste udgave (dine ændringer går tabt) eller kopier dine tekster først.");
        }
        $blocks = SiteBlocks::clean($r->input('blocks'), $page->blocks, $admin);
        $before = count($page->blocks);

        DB::transaction(function () use ($page, $blocks, $r) {
            // Første udgivelse fra editoren: gem også udgaven fra før, så man kan komme tilbage til den
            if (! DB::table('site_page_versions')->where('site_page_id', $page->id)->exists()) {
                DB::table('site_page_versions')->insert(['site_page_id' => $page->id, 'blocks' => json_encode($page->blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_by' => $page->updated_by, 'created_at' => $page->updated_at ?? now()]);
            }
            $page->update(['blocks' => $blocks, 'updated_by' => $r->user()->id]);
            DB::table('site_page_versions')->insert(['site_page_id' => $page->id, 'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by' => $r->user()->id, 'created_at' => now()]);
            $keep = DB::table('site_page_versions')->where('site_page_id', $page->id)->orderByDesc('id')->limit(self::KEEP_VERSIONS)->pluck('id');
            DB::table('site_page_versions')->where('site_page_id', $page->id)->whereNotIn('id', $keep)->delete();
        });
        Audit::log('site.page_published', null, array_filter(['side' => $page->title, 'blokke' => $before !== count($blocks) ? "$before → ".count($blocks) : null]));

        return $this->show($r, $id);
    }

    public function versions(Request $r, int $id)
    {
        [$page] = $this->editable($r, $id);
        $rows = DB::table('site_page_versions')->where('site_page_id', $page->id)->orderByDesc('id')->get(['id', 'created_by', 'created_at']);
        $names = User::whereIn('id', $rows->pluck('created_by')->filter())->pluck('name', 'id');

        return ['versions' => $rows->map(fn ($v) => ['id' => $v->id, 'by' => $names[$v->created_by] ?? null,
            'created_at' => \Illuminate\Support\Carbon::parse($v->created_at)->toIso8601String()])];
    }

    public function version(Request $r, int $id, int $versionId)
    {
        [$page] = $this->editable($r, $id);
        $v = DB::table('site_page_versions')->where('site_page_id', $page->id)->where('id', $versionId)->first();
        abort_unless($v, 404, 'Udgaven findes ikke længere.');

        return ['blocks' => json_decode($v->blocks, true)];
    }

    public function images(Request $r)
    {
        $this->guard($r);

        return ['images' => SiteImages::library()];
    }

    public function upload(Request $r)
    {
        $this->guard($r);
        $r->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:15360'], [
            'image.mimes' => 'Billedet skal være JPG, PNG eller WebP.', 'image.max' => 'Billedet må højst fylde 15 MB.',
        ]);
        $src = SiteImages::store($r->file('image'));
        Audit::log('site.image_uploaded', null, ['billede' => $src]);

        return ['src' => $src, 'thumb' => substr($src, 0, -5).'-900.webp'];
    }
}
