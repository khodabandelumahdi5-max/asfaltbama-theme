<?php

namespace App\Http\Controllers\Panel;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends SupplierController
{
    public function index()
    {
        return view('panel.products.index', [
            'products' => $this->company()->products()->with('category')->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('panel.products.form', ['product' => new Product(['unit' => 'تن', 'min_order' => 1, 'is_active' => true]), 'categories' => Category::orderBy('sort')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Product::uniqueSlug($data['name']);
        $this->company()->products()->create($data);

        return redirect()->route('panel.products.index')->with('status', 'محصول ثبت شد.');
    }

    public function edit(Product $product)
    {
        $this->authorizeOwned($product);

        return view('panel.products.form', ['product' => $product, 'categories' => Category::orderBy('sort')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeOwned($product);
        $data = $this->validated($request);

        if ($data['name'] !== $product->name) {
            $data['slug'] = Product::uniqueSlug($data['name'], $product->id);
        }

        $product->update($data);

        return redirect()->route('panel.products.index')->with('status', 'محصول به‌روزرسانی شد.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeOwned($product);
        $product->delete();

        return back()->with('status', 'محصول حذف شد.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', Rule::in(Product::UNITS)],
            'min_order' => ['required', 'numeric', 'min:0'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'image_url' => ['nullable', 'url', 'max:500'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
