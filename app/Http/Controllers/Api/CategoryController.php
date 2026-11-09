<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Audit;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::orderBy('name')->get(['id', 'name', 'color']);
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->isBoard(), 403);
        $c = Category::create($r->validate(['name' => 'required|string|max:50|unique:categories,name', 'color' => 'nullable|string|size:7']));
        Audit::log('category.created', $c);

        return $c;
    }

    public function update(Request $r, Category $category)
    {
        abort_unless($r->user()->isBoard(), 403);
        $category->update($r->validate(['name' => 'required|string|max:50|unique:categories,name,'.$category->id, 'color' => 'nullable|string|size:7']));
        Audit::log('category.renamed', $category);

        return $category;
    }
}
