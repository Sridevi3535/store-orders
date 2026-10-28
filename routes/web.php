<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/billing');
Route::view('/billing', 'billing');