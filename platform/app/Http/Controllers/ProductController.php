<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->filled('category')
            ? Category::where('slug', $request->string('category'))->first()
            : null;

        $products = Product::active()
            ->with('company', 'category')
            ->search($request->string('q')->toString())
            ->when($category, fn ($q) => $q->whereIn('category_id', $category->children()->pluck('id')->push($category->id)))
            ->when($request->filled('city'), fn ($q) => $q->whereHas('company', fn ($c) => $c->where('city', $request->string('city'))))
            ->when($request->boolean('verified'), fn ($q) => $q->whereHas('company', fn ($c) => $c->where('is_verified', true)))
            ->when($request->input('sort') === 'price', fn ($q) => $q->orderByRaw('price_min is null, price_min asc'), fn ($q) => $q->latest())
            ->paginate(12)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'category' => $category,
            'categories' => Category::whereNull('parent_id')->orderBy('sort')->get(),
            'cities' => Company::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->increment('views');
        $product->load('company', 'category');

        return view('products.show', [
            'product' => $product,
            'related' => Product::active()->with('company')
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->take(4)->get(),
        ]);
    }
}
