<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/memos/{memo}/print', \App\Http\Controllers\MemoPrintController::class)->name('memos.print')->middleware('auth');
Route::get('/sppds/{sppd}/print', \App\Http\Controllers\SppdPrintController::class)->name('sppds.print')->middleware('auth');
Route::get('/sppd-costs/{sppdCost}/print', \App\Http\Controllers\SppdCostPrintController::class)->name('sppd-costs.print')->middleware('auth');
Route::get('/reimbursements/{reimbursement}/print', \App\Http\Controllers\ReimbursementPrintController::class)->name('reimbursements.print')->middleware('auth');
Route::get('/verify-signature/{token}', \App\Http\Controllers\SignatureVerificationController::class)->name('signatures.verify');

