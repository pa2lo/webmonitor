<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\SettingsController;

Route::redirect('/', 'dashboard');
Route::redirect('websites', 'dashboard');

Route::get('status', [MonitorController::class, 'status']);

Route::middleware(['auth', 'verified'])->group(function () {
	Route::get('dashboard', [MonitorController::class, 'index'])->name('dashboard');
	Route::post('websites', [MonitorController::class, 'store']);
	Route::post('websites/{website}/switch', [MonitorController::class, 'switchActive']);
	Route::get('websites/{website}', [MonitorController::class, 'showStats']);
	Route::get('websites/{website}/dailyStats', [MonitorController::class, 'getDailyStats']);
	Route::get('websites/{website}/stats', [MonitorController::class, 'getStats']);
	Route::post('websites/{website}/monthData', [MonitorController::class, 'getMonthData']);
	Route::post('websites/{website}/dayData', [MonitorController::class, 'getDayData']);
	Route::patch('websites/{website}', [MonitorController::class, 'update']);
	Route::delete('websites/{website}', [MonitorController::class, 'destroy']);
	Route::get('websites/{website}/check', [MonitorController::class, 'checkSite']);

	Route::get('downtimes', [MonitorController::class, 'downtimeLog']);
	Route::post('downtimes/getRecords', [MonitorController::class, 'getDowntimeRecords']);

	Route::get('settings', [SettingsController::class, 'index']);
	Route::patch('settings', [SettingsController::class, 'update']);
});

/* cron URLs */
Route::get('checkAll', [MonitorController::class, 'checkAll']);
Route::get('mailReport', [MonitorController::class, 'mailReport']);

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
