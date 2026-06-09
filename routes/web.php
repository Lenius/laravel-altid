<?php

use Illuminate\Support\Facades\Route;

Route::view('altid', 'laravel-altid::altid.info');
Route::redirect('altid-demo', 'alderstjek');
Route::view('alderstjek', 'laravel-altid::altid.demo');
