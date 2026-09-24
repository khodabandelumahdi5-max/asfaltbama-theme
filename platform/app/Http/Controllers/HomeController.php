<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Rfq;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'categories' => Category::whereNull('parent_id')->withCount('products')
                ->with(['children' => fn ($q) => $q->withCount('products')])->orderBy('sort')->get()
                ->each(fn ($c) => $c->products_count += $c->children->sum('products_count')),
            'featured' => Product::active()->with('company', 'category')->latest()->take(8)->get(),
            'suppliers' => Company::where('is_verified', true)->withCount('products')->latest()->take(6)->get(),
            'rfqs' => Rfq::where('status', 'open')->withCount('quotes')->latest()->take(5)->get(),
            'stats' => [
                'suppliers' => Company::count(),
                'products' => Product::active()->count(),
                'rfqs' => Rfq::count(),
            ],
        ]);
    }
}
