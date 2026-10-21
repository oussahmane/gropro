<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

/*
| Cookie-bounce rules (stale phone tabs):
| - GET / always 200 login for guests (never 302 → /login)
| - GET /login always 200 for guests; auth users → /admin|/worker (never → /)
| - Auth checks live in AuthController (not guest middleware HOME="/")
*/
Route::get('/', [AuthController::class, 'home'])->name('home');
Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

        // Orders
        Route::get('/orders/partial', [AdminController::class, 'ordersPartial'])->name('orders.partial');
        Route::get('/orders/{order}', [AdminController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/status', [AdminController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::post('/orders/{order}/release-claim', [AdminController::class, 'releaseOrderClaim'])->name('orders.release-claim');
        Route::post('/orders/{order}/reassign-claim', [AdminController::class, 'reassignOrderClaim'])->name('orders.reassign-claim');
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
