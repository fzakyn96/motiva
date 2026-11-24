<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/memos/{memo}/print', \App\Http\Controllers\MemoPrintController::class)->name('memos.print')->middleware('auth');
Route::get('/sppds/{sppd}/print', \App\Http\Controllers\SppdPrintController::class)->name('sppds.print')->middleware('auth');
Route::get('/sppd-costs/{sppdCost}/print', \App\Http\Controllers\SppdCostPrintController::class)->name('sppd-costs.print')->middleware('auth');
Route::get('/overtimes/{overtimeOrder}/print', \App\Http\Controllers\OvertimePrintController::class)->name('overtimes.print')->middleware('auth');
Route::get('/recaps/transport/{recap}/print', [\App\Http\Controllers\RecapPrintController::class, 'transport'])->name('recaps.transport.print')->middleware('auth');
Route::get('/recaps/travel/{recap}/print', [\App\Http\Controllers\RecapPrintController::class, 'travel'])->name('recaps.travel.print')->middleware('auth');
Route::get('/attachments/{path}', [\App\Http\Controllers\RecapPrintController::class, 'attachment'])->where('path', '.*')->name('attachments.show')->middleware('auth');
Route::get('/verify-signature/{token}', \App\Http\Controllers\SignatureVerificationController::class)->name('signatures.verify');
Route::get('/verify-signature/{token}/download', [\App\Http\Controllers\SignatureVerificationController::class, 'download'])->name('signatures.download');

