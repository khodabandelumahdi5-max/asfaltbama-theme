<?php

namespace App\Http\Controllers;

use App\Models\CarMake;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function home()
    {
        return view('home', [
            'categories' => PartCategory::withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('sort')->get(),
            'ready' => Product::active()->with('brand', 'images')->where('stock_status', 'in_stock')
                ->forVehicle(session('vehicle_id'))->latest()->take(8)->get(),
            'reviews' => Review::with('order.customer')->where('is_published', true)->latest()->take(3)->get(),
        ]);
    }

    /** Remember the customer's vehicle so every list is filtered to compatible parts. */
    public function setVehicle(Request $request)
    {
        $data = $request->validate(['vehicle_id' => ['required', 'exists:vehicles,id']]);
        session(['vehicle_id' => (int) $data['vehicle_id']]);

        return redirect()->route('parts.index');
    }

    public function clearVehicle()
    {
        session()->forget('vehicle_id');

        return back();
    }

    public function index(Request $request)
    {
        $vehicleId = session('vehicle_id');
        $category = $request->filled('category') ? PartCategory::where('slug', $request->string('category'))->first() : null;

        $products = Product::active()->with('brand', 'category', 'images')
            ->search($request->string('q')->toString())
            ->forVehicle($vehicleId)
            ->when($category, fn ($q) => $q->where('part_category_id', $category->id))
            ->when($request->filled('brand'), fn ($q) => $q->where('part_brand_id', $request->integer('brand')))
            ->when($request->filled('stock'), fn ($q) => $q->where('stock_status', $request->string('stock')))
            ->orderByRaw("case stock_status when 'in_stock' then 0 when 'on_request' then 1 else 2 end")
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('parts.index', [
            'products' => $products,
            'category' => $category,
            'categories' => PartCategory::orderBy('sort')->get(),
            'brands' => PartBrand::orderBy('name')->get(),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);
        $product->load('brand', 'category', 'images', 'vehicles.make');
        $vehicleId = session('vehicle_id');

        return view('parts.show', [
            'product' => $product,
            // null = no vehicle chosen; true/false = verified fit for the chosen vehicle or not.
            'fitsMyVehicle' => $vehicleId ? $product->vehicles->contains('id', $vehicleId) : null,
            'alternatives' => Product::active()->with('brand')
                ->where('part_category_id', $product->part_category_id)
                ->whereKeyNot($product->id)
                ->whereHas('vehicles', fn ($q) => $q->whereIn('vehicles.id', $product->vehicles->pluck('id')))
                ->take(4)->get(),
        ]);
    }

    public static function vehicleOptions()
    {
        return CarMake::with('vehicles')->orderBy('sort')->get();
    }

    public static function currentVehicle(): ?Vehicle
    {
        return once(fn () => session('vehicle_id') ? Vehicle::with('make')->find(session('vehicle_id')) : null);
    }
}
