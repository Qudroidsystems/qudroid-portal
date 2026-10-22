<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory & Assets: stock items held at locations (every change is a
 * movement), suppliers and purchase orders, and the asset register
 * (laptops, phones, furniture) with who has what.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('kind', 6)->default('stock');                  // stock, asset
            $t->timestamps();
            $t->unique(['kind', 'name']);
        });

        Schema::create('inv_locations', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('inv_suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('contact_name', 150)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('address', 255)->nullable();
            $t->string('bank_details', 255)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('inv_items', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 40)->unique();
            $t->string('name', 150);
            $t->foreignId('category_id')->nullable()->constrained('inv_categories')->nullOnDelete();
            $t->string('unit', 20)->default('pcs');
            $t->decimal('reorder_level', 12, 2)->default(0);
            $t->decimal('avg_cost', 14, 2)->default(0);              // weighted average of what was paid
            $t->foreignId('supplier_id')->nullable()->constrained('inv_suppliers')->nullOnDelete();   // usual supplier
            $t->string('description', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('inv_stock', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained('inv_items')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('inv_locations')->cascadeOnDelete();
            $t->decimal('quantity', 12, 2)->default(0);
            $t->timestamps();
            $t->unique(['item_id', 'location_id']);
        });

        Schema::create('inv_purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('supplier_id')->constrained('inv_suppliers');
            $t->string('status', 12)->default('draft');              // draft, ordered, partial, received, cancelled
            $t->date('order_date')->nullable();
            $t->date('expected_on')->nullable();
            $t->foreignId('location_id')->nullable()->constrained('inv_locations')->nullOnDelete();   // deliver to
            $t->decimal('total', 14, 2)->default(0);
            $t->string('currency', 3)->default('NGN');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('expense_id')->nullable()->constrained('finance_expenses')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'supplier_id']);
        });

        Schema::create('inv_purchase_order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained('inv_purchase_orders')->cascadeOnDelete();
            $t->foreignId('item_id')->nullable()->constrained('inv_items')->nullOnDelete();   // null = not a stock item (service, asset)
            $t->string('description', 255);
            $t->decimal('quantity', 12, 2);
            $t->decimal('unit_cost', 14, 2);
            $t->decimal('received', 12, 2)->default(0);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('inv_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained('inv_items')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('inv_locations');
            $t->string('type', 12);                                   // receive, issue, adjust, transfer
            $t->decimal('quantity', 12, 2);                           // + in, − out
            $t->decimal('unit_cost', 14, 2)->default(0);
            $t->decimal('balance', 12, 2);                            // at this location, after the move
            $t->foreignId('purchase_order_id')->nullable()->constrained('inv_purchase_orders')->nullOnDelete();
            $t->foreignId('issued_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $t->string('reference', 100)->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['item_id', 'created_at']);
        });

        Schema::create('inv_assets', function (Blueprint $t) {
            $t->id();
            $t->string('tag', 30)->unique();
            $t->string('name', 150);
            $t->foreignId('category_id')->nullable()->constrained('inv_categories')->nullOnDelete();
            $t->string('make_model', 150)->nullable();
            $t->string('serial_number', 100)->nullable();
            $t->foreignId('supplier_id')->nullable()->constrained('inv_suppliers')->nullOnDelete();
            $t->date('purchase_date')->nullable();
            $t->decimal('purchase_cost', 14, 2)->nullable();
            $t->date('warranty_until')->nullable();
            $t->string('status', 10)->default('in_store');           // in_store, assigned, repair, retired, lost
            $t->string('condition', 10)->default('good');            // new, good, fair, poor
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('assigned_at')->nullable();
            $t->foreignId('location_id')->nullable()->constrained('inv_locations')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'assigned_to']);
        });

        Schema::create('inv_asset_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('inv_assets')->cascadeOnDelete();
            $t->string('type', 12);                                   // added, assigned, returned, repair, repaired, retired, lost, note
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();     // who it went to / came from
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        $now = now();
        DB::table('inv_locations')->insertOrIgnore([['name' => 'Main store', 'description' => 'Head office store room', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]]);
        $cats = [['stock', 'Office supplies'], ['stock', 'Training materials'], ['stock', 'Networking & cables'], ['stock', 'Cleaning & kitchen'],
                 ['asset', 'Laptops'], ['asset', 'Desktops & monitors'], ['asset', 'Phones & tablets'], ['asset', 'Network equipment'], ['asset', 'Furniture'], ['asset', 'Vehicles']];
        DB::table('inv_categories')->insertOrIgnore(array_map(fn ($c) => ['kind' => $c[0], 'name' => $c[1], 'created_at' => $now, 'updated_at' => $now], $cats));
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_asset_events');
        Schema::dropIfExists('inv_assets');
        Schema::dropIfExists('inv_movements');
        Schema::dropIfExists('inv_purchase_order_lines');
        Schema::dropIfExists('inv_purchase_orders');
        Schema::dropIfExists('inv_stock');
        Schema::dropIfExists('inv_items');
        Schema::dropIfExists('inv_suppliers');
        Schema::dropIfExists('inv_locations');
        Schema::dropIfExists('inv_categories');
    }
};
