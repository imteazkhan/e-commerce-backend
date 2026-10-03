<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Menu categories, in the order they were created. Paused ones are left out
     * unless a staff member asks for all=1.
     */
    public function index(Request $request)
    {
        $query = Category::withCount('products')->orderBy('id');

        if (! ($request->boolean('all') && $this->isStaff($request))) {
            $query->active();
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
        ]);

        // Slug defaults to the name, e.g. "Ethnic Wear" -> "ethnic-wear".
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);

        if ($data['slug'] === '') {
            return response()->json(['message' => 'Could not build a slug from this name.'], 422);
        }

        if (Category::where('slug', $data['slug'])->exists()) {
            return response()->json(['message' => "A category with slug \"{$data['slug']}\" already exists."], 422);
        }

        $category = Category::create($data);

        return response()->json($category->loadCount('products'), 201);
    }

    /**
     * Rename and/or pause. The slug stays put so existing links keep working.
     */
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($data);

        return response()->json($category->loadCount('products'));
    }

    /**
     * products=move (with move_to), detach (leave uncategorized) or delete.
     * Required when the category still has products. Past orders keep their line items either way.
     */
    public function destroy(Request $request, Category $category)
    {
        $data = $request->validate([
            'products' => ['nullable', Rule::in(['move', 'detach', 'delete'])],
            'move_to' => ['required_if:products,move', 'nullable', 'integer', Rule::exists('categories', 'id'), Rule::notIn([$category->id])],
        ]);

        $count = $category->products()->count();

        if ($count && empty($data['products'])) {
            return response()->json([
                'message' => "This category has {$count} products. Choose whether to move, keep or delete them.",
            ], 422);
        }

        DB::transaction(function () use ($category, $data) {
            match ($data['products'] ?? null) {
                'move' => $category->products()->update(['category_id' => $data['move_to']]),
                'detach' => $category->products()->update(['category_id' => null]),
                'delete' => $category->products()->delete(),
                default => null,
            };
            $category->delete();
        });

        return response()->json(['message' => 'Category deleted']);
    }
}
