<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\Admin\AddressController as AdminAddressController;
use App\Http\Controllers\Api\Admin\ApplicationErrorController as AdminApplicationErrorController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\UploadController as AdminUploadController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/sale', [ProductController::class, 'sale']);
Route::get('/products/price-bounds', [ProductController::class, 'priceBounds']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/products/{slug}/reviews', [ProductReviewController::class, 'index']);
Route::post('/products/{slug}/reviews', [ProductReviewController::class, 'store']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{slug}', [PostController::class, 'show']);

Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart', [CartController::class, 'store']);
Route::patch('/cart/items/{product}', [CartController::class, 'update']);
Route::delete('/cart/items/{product}', [CartController::class, 'destroy']);
Route::delete('/cart', [CartController::class, 'clear']);

Route::get('/wishlist', [WishlistController::class, 'index']);
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
Route::post('/wishlist/add', [WishlistController::class, 'add']);
Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);

Route::post('/razorpay/webhook', [RazorpayWebhookController::class, 'handle']);

Route::get('/orders', [OrderController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/payment/verify', [OrderController::class, 'verifyPayment']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
});

Route::post('/contact', [ContactController::class, 'store']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', \App\Http\Controllers\Api\GoogleAuthController::class);
Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
    Route::post('/addresses/{address}/default', [AddressController::class, 'setDefault']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/me', fn (Request $request) => new UserResource($request->user()));
    Route::get('/stats', [AdminDashboardController::class, 'stats']);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update']);

    Route::apiResource('products', AdminProductController::class);
    Route::post('/uploads/images', [AdminUploadController::class, 'images']);
    Route::apiResource('categories', AdminCategoryController::class);
    Route::apiResource('posts', AdminPostController::class);

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index']);
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show']);
    Route::delete('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::patch('/users/{user}', [AdminUserController::class, 'update']);

    Route::get('/addresses', [AdminAddressController::class, 'index']);
    Route::delete('/addresses/{address}', [AdminAddressController::class, 'destroy']);

    Route::get('/errors/summary', [AdminApplicationErrorController::class, 'summary']);
    Route::get('/errors', [AdminApplicationErrorController::class, 'index']);
    Route::get('/errors/{error}', [AdminApplicationErrorController::class, 'show']);
    Route::patch('/errors/{error}', [AdminApplicationErrorController::class, 'update']);
    Route::delete('/errors', [AdminApplicationErrorController::class, 'destroyMany']);
    Route::delete('/errors/{error}', [AdminApplicationErrorController::class, 'destroy']);
});

if (app()->environment('testing')) {
    Route::get('/__test-boom', function () {
        throw new RuntimeException('SQLSTATE[HY000]: secret database failure');
    });
}
