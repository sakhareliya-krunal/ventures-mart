<?php

use App\Http\Controllers\SeoAssetController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SeoAssetController::class, 'sitemap']);
Route::get('/robots.txt', [SeoAssetController::class, 'robots']);
Route::get('/{any?}', SpaController::class)->where('any', '.*');
