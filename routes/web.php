<?php

use Illuminate\Support\Facades\Route;

Route::get('/adgang', [\App\Http\Controllers\GateController::class, 'show']);
Route::post('/adgang', [\App\Http\Controllers\GateController::class, 'check'])->middleware('throttle:10,1');

// Alt andet end /api håndteres af webappen (Vue)
Route::view('/{any?}', 'app')->where('any', '^(?!api|up|adgang).*$');
