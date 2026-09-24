<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('buyer')->after('email'); // admin | supplier | buyer
            $table->string('phone')->nullable()->after('role');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('unit')->default('تن');
            $table->decimal('min_order', 12, 2)->default(1);
            $table->unsignedBigInteger('price_min')->nullable(); // تومان
            $table->unsignedBigInteger('price_max')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('quantity', 12, 2);
            $table->string('unit')->default('تن');
            $table->string('city')->nullable();
            $table->text('details')->nullable();
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->string('status')->default('open'); // open | closed
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending | accepted | rejected
            $table->timestamps();
            $table->unique(['rfq_id', 'company_id']);
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete(); // owner (supplier)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // linked buyer account
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('organization')->nullable();
            $table->string('source')->default('manual'); // manual | inquiry | rfq
            $table->string('stage')->default('new');
            $table->unsignedBigInteger('deal_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('note'); // note | call | meeting | task
            $table->text('body');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('companies');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone']);
        });
    }
};
