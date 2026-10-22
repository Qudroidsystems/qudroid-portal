<?php

use App\Http\Controllers\Inventory\AssetController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\PurchaseOrderController;
use App\Http\Controllers\Inventory\SupplierController;
use Illuminate\Support\Facades\Route;

/*
| Inventory & Assets. Loaded inside the signed-in group. View inventory
| reads everything; Manage inventory changes stock, orders and assets.
| Anyone can see the equipment assigned to them under /me/assets.
*/

Route::prefix('inventory')->name('inventory.')->middleware('permission:View inventory|Manage inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');

    Route::middleware('permission:Manage inventory')->group(function () {
        Route::get('/settings', [InventoryController::class, 'settings'])->name('settings');
        Route::post('/categories', [InventoryController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [InventoryController::class, 'updateCategory'])->name('categories.update');
        Route::post('/locations', [InventoryController::class, 'storeLocation'])->name('locations.store');
        Route::put('/locations/{location}', [InventoryController::class, 'updateLocation'])->name('locations.update');

        Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('/items', [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
        Route::post('/items/{item}/receive', [ItemController::class, 'receive'])->name('items.receive');
        Route::post('/items/{item}/issue', [ItemController::class, 'issue'])->name('items.issue');
        Route::post('/items/{item}/adjust', [ItemController::class, 'adjust'])->name('items.adjust');
        Route::post('/items/{item}/transfer', [ItemController::class, 'transfer'])->name('items.transfer');

        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

        Route::get('/orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('orders.edit');
        Route::put('/orders/{order}', [PurchaseOrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{order}/place', [PurchaseOrderController::class, 'place'])->name('orders.place');
        Route::post('/orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('orders.receive');
        Route::post('/orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/bill', [PurchaseOrderController::class, 'bill'])->name('orders.bill');

        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('/assets/{asset}/action', [AssetController::class, 'action'])->name('assets.action');
    });

    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('/orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [PurchaseOrderController::class, 'show'])->name('orders.show');
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
});

Route::get('/me/assets', [AssetController::class, 'mine'])->name('me.assets');
