<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPrintController;
use App\Http\Controllers\InspectionActPrintController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});



// Маршрут для постраничной печати производственного паспорта заказа
Route::get('/admin/orders/{order}/print-passport', [OrderPrintController::class, 'print'])
    ->name('orders.print-passport')
    ->middleware(['web', 'auth']);

// Маршрут для печати акта входного контроля по одной строке поступления (партии)
Route::get('/admin/receipt-lines/{receiptLine}/print-inspection-act', [InspectionActPrintController::class, 'print'])
    ->name('receipt-lines.print-inspection-act')
    ->middleware(['web', 'auth']);


require __DIR__.'/auth.php';
