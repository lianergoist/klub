<?php

use Illuminate\Support\Facades\Route;

Route::get('/adgang', [\App\Http\Controllers\GateController::class, 'show']);
Route::post('/adgang', [\App\Http\Controllers\GateController::class, 'check'])->middleware('throttle:10,1');

// Klubbens hjemmeside: forsiden og de offentlige sider (kopi af Squarespace-siden)
Route::get('/', [\App\Http\Controllers\SiteController::class, 'show']);
Route::get('/{page}', [\App\Http\Controllers\SiteController::class, 'show'])->whereIn('page', ['priser-og-medlemskab', 'kalender', 'kontakt', 'read-me']);

// Alt andet end /api håndteres af webappen (Vue)
Route::view('/{any?}', 'app')->where('any', '^(?!api|up|adgang|hjemmeside/).*$');
