<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\Panel;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RfqController;
use Illuminate\Support\Facades\Route;

// Public marketplace
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product}/inquiry', [InquiryController::class, 'store'])->name('inquiries.store')->middleware('throttle:10,1');
Route::get('/suppliers', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/suppliers/{company}', [CompanyController::class, 'show'])->name('companies.show');
Route::get('/rfq', [RfqController::class, 'index'])->name('rfqs.index');
Route::get('/rfq/create', [RfqController::class, 'create'])->name('rfqs.create');
Route::post('/rfq', [RfqController::class, 'store'])->name('rfqs.store')->middleware('throttle:10,1');
Route::get('/rfq/{rfq}', [RfqController::class, 'show'])->name('rfqs.show');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// User panel
Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [Panel\DashboardController::class, 'index'])->name('dashboard');

    // Buyer: own RFQs and received quotes
    Route::get('/my-rfqs', [Panel\BuyerRfqController::class, 'index'])->name('buyer.rfqs');
    Route::get('/my-rfqs/{rfq}', [Panel\BuyerRfqController::class, 'show'])->name('buyer.rfqs.show');
    Route::post('/quotes/{quote}/accept', [Panel\BuyerRfqController::class, 'accept'])->name('buyer.quotes.accept');

    // Supplier
    Route::middleware('role:supplier')->group(function () {
        Route::get('/company', [Panel\CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/company', [Panel\CompanyController::class, 'update'])->name('company.update');
        Route::resource('products', Panel\ProductController::class)->except('show');
        Route::get('/inquiries', [Panel\InquiryController::class, 'index'])->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [Panel\InquiryController::class, 'show'])->name('inquiries.show');
        Route::get('/leads', [Panel\QuoteController::class, 'index'])->name('quotes.index');
        Route::post('/rfq/{rfq}/quote', [Panel\QuoteController::class, 'store'])->name('quotes.store');

        // CRM
        Route::get('/crm', [Panel\CrmController::class, 'index'])->name('crm.index');
        Route::get('/crm/tasks', [Panel\CrmController::class, 'tasks'])->name('crm.tasks');
        Route::get('/crm/create', [Panel\CrmController::class, 'create'])->name('crm.create');
        Route::post('/crm', [Panel\CrmController::class, 'store'])->name('crm.store');
        Route::get('/crm/{contact}', [Panel\CrmController::class, 'show'])->name('crm.show');
        Route::put('/crm/{contact}', [Panel\CrmController::class, 'update'])->name('crm.update');
        Route::patch('/crm/{contact}/stage', [Panel\CrmController::class, 'stage'])->name('crm.stage');
        Route::delete('/crm/{contact}', [Panel\CrmController::class, 'destroy'])->name('crm.destroy');
        Route::post('/crm/{contact}/activities', [Panel\CrmController::class, 'storeActivity'])->name('crm.activities.store');
        Route::patch('/crm/activities/{activity}/done', [Panel\CrmController::class, 'completeActivity'])->name('crm.activities.done');
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/companies', [Admin\CompanyController::class, 'index'])->name('companies.index');
        Route::patch('/companies/{company}/verify', [Admin\CompanyController::class, 'toggleVerify'])->name('companies.verify');
    });
});
