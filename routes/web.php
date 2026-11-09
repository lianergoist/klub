<?php

use Illuminate\Support\Facades\Route;

// Alt andet end /api håndteres af webappen (Vue)
Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
