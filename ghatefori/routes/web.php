<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CartController;
use App\Http\Controllers\FitmentController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

// Storefront
Route::get('/', [ShopController::class, 'home'])->name('home');
Route::post('/vehicle', [ShopController::class, 'setVehicle'])->name('vehicle.set');
Route::delete('/vehicle', [ShopController::class, 'clearVehicle'])->name('vehicle.clear');
Route::get('/parts', [ShopController::class, 'index'])->name('parts.index');
Route::get('/parts/{product}', [ShopController::class, 'show'])->name('parts.show');
Route::post('/parts/{product}/alert', [FitmentController::class, 'alert'])->name('parts.alert')->middleware('throttle:10,1');

Route::get('/fitment', [FitmentController::class, 'create'])->name('fitment.create');
Route::post('/fitment', [FitmentController::class, 'store'])->name('fitment.store')->middleware('throttle:10,1');
Route::get('/fitment/thanks', [FitmentController::class, 'thanks'])->name('fitment.thanks');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::get('/checkout', [CartController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [CartController::class, 'place'])->name('checkout.place')->middleware('throttle:10,1');

Route::get('/track', [OrderTrackingController::class, 'form'])->name('orders.track');
Route::post('/track', [OrderTrackingController::class, 'find'])->name('orders.find')->middleware('throttle:10,1');
Route::get('/orders/{order}', [OrderTrackingController::class, 'show'])->name('orders.show')->middleware('signed');
Route::post('/orders/{order}/review', [OrderTrackingController::class, 'review'])->name('orders.review')->middleware('signed');

Route::view('/shipping-and-returns', 'pages.shipping')->name('pages.shipping');
Route::view('/how-we-check', 'pages.quality')->name('pages.quality');
Route::view('/contact', 'pages.contact')->name('pages.contact');

// Staff panel
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [Admin\AuthController::class, 'show'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::put('/orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');
        Route::patch('/orders/{order}/status', [Admin\OrderController::class, 'status'])->name('orders.status');

        Route::get('/fitment', [Admin\FitmentController::class, 'index'])->name('fitment.index');
        Route::get('/fitment/{fitment}', [Admin\FitmentController::class, 'show'])->name('fitment.show');
        Route::put('/fitment/{fitment}', [Admin\FitmentController::class, 'update'])->name('fitment.update');

        Route::get('/products', [Admin\ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [Admin\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [Admin\ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [Admin\ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/images', [Admin\ProductController::class, 'addImage'])->name('products.images.store');
        Route::delete('/products/{product}/images/{image}', [Admin\ProductController::class, 'removeImage'])->name('products.images.destroy');
        Route::post('/products/{product}/offers', [Admin\ProductController::class, 'saveOffer'])->name('products.offers.store');
        Route::delete('/products/{product}/offers/{offer}', [Admin\ProductController::class, 'removeOffer'])->name('products.offers.destroy');

        Route::get('/alerts', [Admin\AlertController::class, 'index'])->name('alerts.index');
        Route::patch('/alerts/{alert}', [Admin\AlertController::class, 'notified'])->name('alerts.notified');

        Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/vehicles', [Admin\CustomerController::class, 'addVehicle'])->name('customers.vehicles.store');
        Route::post('/customers/{customer}/reminders', [Admin\CustomerController::class, 'addReminder'])->name('customers.reminders.store');
        Route::patch('/customers/{customer}/reminders/{reminder}', [Admin\CustomerController::class, 'completeReminder'])->name('customers.reminders.done');

        Route::get('/suppliers', [Admin\SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [Admin\SupplierController::class, 'save'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [Admin\SupplierController::class, 'save'])->name('suppliers.update');

        Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}', [Admin\ReviewController::class, 'toggle'])->name('reviews.toggle');

        Route::get('/report', [Admin\ReportController::class, 'weekly'])->name('report')->middleware('role:manager');
    });
});
