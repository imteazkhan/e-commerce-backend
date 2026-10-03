<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Query params used by the frontend: category (slug), search, sort
     * (new|price_asc|price_desc), featured=1, discount=1, stock (in|low|out),
     * all=1 (staff only: include products in paused categories).
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        if (! ($request->boolean('all') && $this->isStaff($request))) {
            $query->visible();
        }

        if ($slug = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        match ($request->query('stock')) {
            'in' => $query->where('stock', '>', Product::LOW_STOCK),
            'low' => $query->whereBetween('stock', [1, Product::LOW_STOCK]),
            'out' => $query->where('stock', 0),
            default => null,
        };

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('discount')) {
            $query->whereColumn('compare_price', '>', 'price');
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'new' => $query->latest()->orderByDesc('id'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        return response()->json($query->get());
    }

    public function show(Request $request, Product $product)
    {
        if ($product->category && ! $product->category->is_active && ! $this->isStaff($request)) {
            abort(404);
        }

        return response()->json($product->load('category'));
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));

        return response()->json($product->load('category'), 201);
    }

    public function update(Request $request, Product $product)
    {
        // Managers may only adjust stock; admins can edit everything.
        if ($request->user()->role === 'manager') {
            $data = $request->validate(['stock' => ['required', 'integer', 'min:0']]);
        } else {
            $data = $this->validated($request, $product);
        }

        $product->update($data);

        return response()->json($product->load('category'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $required = $product ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'price' => [$required, 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:2048'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:2048'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'.($product ? ','.$product->id : '')],
            'is_featured' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
        ]);

        // Allow the category to be sent as a slug instead of an id.
        if (! empty($data['category'])) {
            $data['category_id'] = Category::where('slug', $data['category'])->value('id');
        }
        unset($data['category']);

        // The admin form sends "" for an empty stock field.
        if (array_key_exists('stock', $data) && $data['stock'] === null) {
            $data['stock'] = 0;
        }

        return $data;
    }
}
