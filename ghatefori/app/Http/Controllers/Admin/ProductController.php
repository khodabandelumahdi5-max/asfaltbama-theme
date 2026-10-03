<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarMake;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.products.index', [
            'products' => Product::with('brand', 'category', 'offers')
                ->withCount(['alerts as waiting_alerts' => fn ($q) => $q->whereNull('notified_at')])
                ->search($request->string('q')->toString())
                ->when($request->filled('stock'), fn ($q) => $q->where('stock_status', $request->string('stock')))
                ->latest()->paginate(40)->withQueryString(),
        ]);
    }

    public function create()
    {
        return $this->form(new Product(['authenticity' => 'aftermarket', 'condition' => 'new', 'stock_status' => 'on_request', 'is_active' => true]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $product = Product::create($data + ['slug' => Product::uniqueSlug($data['name'])]);
        $product->vehicles()->sync($request->input('vehicles', []));

        return redirect()->route('admin.products.edit', $product)->with('status', 'محصول ساخته شد. حالا عکس‌ها و قیمت تأمین‌کننده‌ها را اضافه کنید.');
    }

    public function edit(Product $product)
    {
        return $this->form($product->load('images', 'offers.supplier', 'vehicles', 'alerts'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);
        if ($data['name'] !== $product->name) {
            $data['slug'] = Product::uniqueSlug($data['name'], $product->id);
        }
        $product->update($data);
        $product->vehicles()->sync($request->input('vehicles', []));

        $waiting = $product->alerts()->whereNull('notified_at')->count();
        $msg = 'محصول ذخیره شد.';
        if ($product->stock_status === 'in_stock' && $waiting) {
            $msg .= ' '.fa_digits($waiting).' نفر منتظر موجود شدن این کالا هستند — از بخش «خبرم کن» تماس بگیرید.';
        }

        return back()->with('status', $msg);
    }

    public function addImage(Request $request, Product $product)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ProductImage::KINDS))],
            'caption' => ['required_if:kind,installed', 'nullable', 'string', 'max:160'],
            'image' => ['required_without:url', 'nullable', 'image', 'max:4096'],
            'url' => ['required_without:image', 'nullable', 'url', 'max:500'],
        ], ['caption.required_if' => 'برای عکس نصب‌شده بنویسید مربوط به کدام خودرو و نسخه است.']);

        $product->images()->create([
            'kind' => $data['kind'],
            'caption' => $data['caption'] ?? null,
            'path' => $request->file('image')?->store('products', 'public') ?? $data['url'],
            'sort' => $product->images()->count(),
        ]);

        return back()->with('status', 'عکس اضافه شد.');
    }

    public function removeImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $image->delete();

        return back();
    }

    public function saveOffer(Request $request, Product $product)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'valid_until' => ['nullable', 'date'],
        ]);
        $product->offers()->updateOrCreate(['supplier_id' => $data['supplier_id']], $data);
        $product->update(['price_checked_at' => now()]);

        return back()->with('status', 'قیمت تأمین‌کننده ثبت شد.');
    }

    public function removeOffer(Product $product, int $offer)
    {
        $product->offers()->whereKey($offer)->delete();

        return back();
    }

    private function form(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => PartCategory::orderBy('sort')->get(),
            'brands' => PartBrand::orderBy('name')->get(),
            'makes' => CarMake::with('vehicles')->orderBy('sort')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'part_category_id' => ['required', 'exists:part_categories,id'],
            'part_brand_id' => ['nullable', 'exists:part_brands,id'],
            'part_number' => ['nullable', 'string', 'max:60'],
            'oem_number' => ['nullable', 'string', 'max:60'],
            'authenticity' => ['required', Rule::in(array_keys(Product::AUTHENTICITY))],
            'condition' => ['required', Rule::in(array_keys(Product::CONDITIONS))],
            'side' => ['nullable', Rule::in(array_keys(Product::SIDES))],
            'position' => ['nullable', Rule::in(array_keys(Product::POSITIONS))],
            'package_contents' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'incompatibility_notes' => ['nullable', 'string', 'max:2000'],
            'warranty' => ['nullable', 'string', 'max:255'],
            'return_policy' => ['nullable', 'string', 'max:255'],
            'stock_status' => ['required', Rule::in(array_keys(Product::STOCK))],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['required_if:stock_status,on_request', 'nullable', 'integer', 'min:0', 'max:90'],
            'price' => ['nullable', 'integer', 'min:0'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'vehicles' => ['array'],
            'vehicles.*' => ['exists:vehicles,id'],
        ], ['lead_time_days.required_if' => 'برای کالای «قابل تأمین» زمان تأمین را مشخص کنید.']);

        if (($data['stock_status'] === 'in_stock') && empty($data['stock_qty'])) {
            $data['stock_status'] = 'out_of_stock';
        }
        unset($data['vehicles']);

        return $data + [
            'stock_qty' => $data['stock_qty'] ?? 0,
            'requires_fitment_check' => $request->boolean('requires_fitment_check'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
