<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

/*
| Guests: render login at "/" (no hop). Authenticated: relative redirect to role home.
| Avoids / ↔ /login loops and https:// Location rewrites from spoofed Forwarded-Proto.
*/
Route::get('/', [AuthController::class, 'home'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        // Orders
        Route::get('/orders/partial', [AdminController::class, 'ordersPartial'])->name('orders.partial');
        Route::get('/orders/{order}', [AdminController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/status', [AdminController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::delete('/orders/{order}', [AdminController::class, 'destroyOrder'])->name('orders.destroy');

        // Catalog
        Route::get('/catalog', [AdminController::class, 'catalog'])->name('catalog.index');
        Route::post('/catalog/preview', [AdminController::class, 'previewCatalog'])->name('catalog.preview');
        Route::post('/catalog/import', [AdminController::class, 'importCatalog'])->name('catalog.import');

        // Workers
        Route::resource('workers', AdminController::class)->except(['show']);
    });

    Route::middleware('role:worker')->prefix('worker')->name('worker.')->group(function () {
        Route::get('/', [WorkerController::class, 'dashboard'])->name('dashboard');

        // Order Creation
        Route::get('/order/new', [WorkerController::class, 'createOrder'])->name('order.create');
        Route::get('/order/{order}/edit', [WorkerController::class, 'editOrder'])->name('order.edit');
        Route::post('/order/{order}/update', [WorkerController::class, 'updateOrder'])->name('order.update');
        Route::post('/order/{order}/submit', [WorkerController::class, 'submitOrder'])->name('order.submit');

        // Product Search (AJAX)
        Route::get('/products/search', [WorkerController::class, 'searchProducts'])->name('products.search');
    });
});
