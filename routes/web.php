<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ServeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CraftsmanController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingController;

// Serve uploaded files from /tmp (Vercel serverless)
Route::get('/storage/{path}', [ServeController::class, 'serve'])
    ->where('path', '.*');

// Frontend Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');
Route::get('/history', [HistoryController::class, 'index'])->name('history');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

// Admin Authentication
Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.post');

// Admin Routes (Protected)
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('products', ProductController::class);
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::resource('craftsmen', CraftsmanController::class);
    Route::resource('gallery', GalleryController::class);
    Route::resource('messages', ContactMessageController::class)->only(['index', 'show', 'destroy']);
    Route::post('/messages/mark-all-read', [ContactMessageController::class, 'markAllRead'])->name('messages.markAllRead');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/name', [ProfileController::class, 'updateName'])->name('profile.name');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

// System Maintenance (untuk lingkungan serverless Vercel)
Route::get('/system/migrate-db', function () {
    $token = env('DB_MIGRATE_TOKEN');
    if (empty($token) || request('token') !== $token) {
        abort(403, 'Akses ditolak: Token tidak valid.');
    }
    
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate --force');
        return '<h3>Database Migration Successful:</h3><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return '<h3>Migration Failed:</h3><pre>' . $e->getMessage() . '</pre>';
    }
});

Route::get('/system/seed-db', function () {
    $token = env('DB_MIGRATE_TOKEN');
    if (empty($token) || request('token') !== $token) {
        abort(403, 'Akses ditolak: Token tidak valid.');
    }
    
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed --force');
        return '<h3>Database Seeding Successful:</h3><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return '<h3>Seeding Failed:</h3><pre>' . $e->getMessage() . '</pre>';
    }
});