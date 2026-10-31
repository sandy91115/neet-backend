<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::view('/admin', 'admin.app')->name('admin.dashboard');
Route::view('/admin/login', 'admin.app')->name('admin.login');
