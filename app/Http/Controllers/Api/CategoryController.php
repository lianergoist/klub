<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kategorier slettes aldrig: de arkiveres, så gamle aktiviteter beholder dem.
 * Et nyt navn gælder også for alle, der følger kategorien, og for tidligere aktiviteter.
 * En obligatorisk kategori (mandatory) kan medlemmer ikke fravælge notifikationer om.
 */
class CategoryController extends Controller
{
    public function index(Request $r)
    {
        $all = Category::orderBy('name')->get();
        $punch = \App\Support\Punchcards::categoryIds(); // kategorier, der findes klippekort til
        if (! $r->user()->may('categories.manage')) {
            return $all->whereNull('archived_at')->values()->map(fn ($c) => $c->only('id', 'name', 'color') + ['mandatory' => (bool) $c->mandatory, 'punchcard' => in_array($c->id, $punch)]);
        }

        // Medlemmer følger som standard alle kategorier, medmindre de har slået notifikationer fra
        $members = User::whereIn('status', ['active', 'passive'])->count();
        $optedOut = DB::table('category_user')->where('notify', false)->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id');
        $activities = DB::table('activity_category')->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id');

        return $all->map(fn ($c) => self::row($c) + ['punchcard' => in_array($c->id, $punch),
            'followers' => $members - ($c->mandatory ? 0 : ($optedOut[$c->id] ?? 0)),
            'activities' => (int) ($activities[$c->id] ?? 0),
        ])->values();
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->may('categories.manage'), 403);
        $c = Category::create($r->validate(['name' => 'required|string|max:50|unique:categories,name', 'color' => 'nullable|string|size:7', 'mandatory' => 'boolean']));
        Audit::log('category.created', $c);

        return self::row($c) + ['followers' => User::whereIn('status', ['active', 'passive'])->count(), 'activities' => 0];
    }

    public function update(Request $r, Category $category)
    {
        abort_unless($r->user()->may('categories.manage'), 403);
        $data = $r->validate([
            'name' => 'required|string|max:50|unique:categories,name,'.$category->id,
            'color' => 'nullable|string|size:7',
            'mandatory' => 'boolean',
            'archived' => 'boolean',
        ]);
        $renamed = $data['name'] !== $category->name;
        $category->fill(collect($data)->only('name', 'color', 'mandatory')->all());
        if ($category->isDirty('mandatory')) {
            Audit::log($category->mandatory ? 'category.made_mandatory' : 'category.made_optional', $category);
        }
        if (array_key_exists('archived', $data) && $data['archived'] !== (bool) $category->archived_at) {
            $category->archived_at = $data['archived'] ? now() : null;
            Audit::log($data['archived'] ? 'category.archived' : 'category.restored', $category);
        }
        $category->save();
        if ($renamed) {
            Audit::log('category.renamed', $category);
        }

        return self::row($category);
    }

    private static function row(Category $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'color' => $c->color, 'mandatory' => (bool) $c->mandatory, 'archived' => (bool) $c->archived_at];
    }
}
