<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/memos/{memo}/print', \App\Http\Controllers\MemoPrintController::class)->name('memos.print')->middleware('auth');
