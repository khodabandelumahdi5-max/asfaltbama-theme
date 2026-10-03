<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff roles: manager | sales | technical | shipping
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('sales')->after('email');
            $table->string('phone')->nullable()->after('role');
        });

        // ---- Vehicles -------------------------------------------------------
        Schema::create('car_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('origin'); // chinese | korean | japanese
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_make_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->unsignedSmallInteger('year_from')->nullable(); // شمسی
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('engine')->nullable();   // e.g. 1.5 توربو
            $table->string('gearbox')->nullable();  // دستی | اتومات | CVT
            $table->string('trim')->nullable();     // تیپ
            $table->timestamps();
        });

        // ---- Catalogue ------------------------------------------------------
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(0);
        });

        // Part manufacturers (not to be confused with car makes).
        Schema::create('part_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->text('notes')->nullable(); // documented differences vs. other brands
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('part_brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('part_number')->nullable()->index(); // manufacturer's technical number
            $table->string('oem_number')->nullable()->index();  // car maker's reference number
            $table->string('authenticity'); // genuine | oem_supplier | aftermarket
            $table->string('condition')->default('new'); // new | used
            $table->string('side')->nullable();     // left | right
            $table->string('position')->nullable(); // front | rear
            $table->string('package_contents')->nullable();
            $table->text('description')->nullable();
            $table->text('incompatibility_notes')->nullable();
            $table->string('warranty')->nullable();
            $table->string('return_policy')->nullable();
            // Availability: in_stock (ready to ship) | on_request (sourceable after confirmation) | out_of_stock
            $table->string('stock_status')->default('on_request');
            $table->unsignedInteger('stock_qty')->default(0);
            $table->unsignedSmallInteger('lead_time_days')->nullable(); // for on_request
            $table->unsignedBigInteger('price')->nullable();      // تومان, shown to customers
            $table->unsignedBigInteger('min_price')->nullable();  // floor — no sale below this
            $table->boolean('requires_fitment_check')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('price_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('kind'); // full | back | connector | label | package | installed | illustration
            $table->string('caption')->nullable(); // required for "installed": which vehicle/version
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Verified compatibility only.
        Schema::create('product_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->unique(['product_id', 'vehicle_id']);
        });

        // ---- Sourcing (wholesalers) ----------------------------------------
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('payment_terms')->nullable(); // نقد | چک ۳۰ روزه …
            $table->text('return_terms')->nullable();
            $table->boolean('ships_direct')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cost_price');
            $table->unsignedInteger('stock_qty')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->date('valid_until')->nullable(); // how long the wholesaler holds price/stock
            $table->timestamps();
            $table->unique(['product_id', 'supplier_id']);
        });

        // ---- Customers (CRM) -----------------------------------------------
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('type')->default('owner'); // owner | garage | shop
            $table->string('city')->nullable();
            $table->string('source')->nullable(); // instagram | google | referral | garage | other
            $table->boolean('marketing_consent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable(); // free text if not in the list
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vin')->nullable(); // private, never shown publicly
            $table->date('last_service_date')->nullable();
            $table->unsignedInteger('last_service_km')->nullable();
            $table->timestamps();
        });

        Schema::create('fitment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('vehicle_text')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('engine')->nullable();
            $table->string('vin')->nullable();
            $table->string('part_number')->nullable();
            $table->text('description');
            $table->string('photo_path')->nullable();
            // pending | compatible | incompatible | needs_info | unavailable
            $table->string('status')->default('pending');
            $table->text('result_note')->nullable();
            $table->unsignedBigInteger('quoted_price')->nullable();
            $table->unsignedSmallInteger('quoted_lead_days')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('followed_up_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->boolean('contact_consent')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });

        // ---- Orders ---------------------------------------------------------
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            // awaiting_confirmation | confirmed | paid | shipped | delivered | cancelled | returned
            $table->string('status')->default('awaiting_confirmation');
            $table->string('city');
            $table->text('address');
            $table->unsignedBigInteger('shipping_charge')->default(0); // charged to customer
            $table->unsignedBigInteger('shipping_cost')->default(0);   // actual courier cost
            $table->unsignedBigInteger('inbound_cost')->default(0);    // freight from wholesaler
            $table->unsignedBigInteger('packaging_cost')->default(0);
            $table->unsignedBigInteger('payment_fee')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('acquisition_cost')->default(0); // ad cost attributed to this order
            $table->unsignedBigInteger('return_reserve')->default(0);   // estimated share of returns/defects
            $table->string('source')->nullable();
            $table->text('customer_note')->nullable();
            $table->foreignId('fitment_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('fitment_note')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('cancel_reason')->nullable(); // no_stock | price_change | customer | fitment | other
            $table->string('return_reason')->nullable(); // fitment | defect | damaged | customer | other
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('promised_at')->nullable(); // promised delivery date
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body');
            $table->timestamps();
        });

        // Reviews come only from delivered orders and are published with consent.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->boolean('publish_consent')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        // Service reminders — always based on customer-reported data.
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('due_on');
            $table->boolean('is_estimate')->default(true);
            $table->string('basis')->nullable(); // where the interval came from
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reminders', 'reviews', 'order_events', 'order_items', 'orders', 'stock_alerts', 'fitment_requests',
            'customer_vehicles', 'customers', 'supplier_offers', 'suppliers', 'product_vehicle', 'product_images',
            'products', 'part_brands', 'part_categories', 'vehicles', 'car_makes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'phone']));
    }
};
