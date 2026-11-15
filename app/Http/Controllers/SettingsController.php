<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SettingsService;

class SettingsController extends Controller
{
	public function index(SettingsService $settings) {
		return inertia('Settings', [
			'settings' => $settings->all()
		]);
	}

	public function update(Request $request, SettingsService $settings) {
		$validated = $request->validate([
			'cron_key' => 'required|min:8',
			'node_server' => 'required',
			'php_check_mode' => 'sometimes|required|string|in:concurrent_5,concurrent_20,staggering',
            'reports' => 'nullable|array'
        ]);

		$settings->updateMany($validated + ['reports' => []]);

		return back()->with('message', 'success');
	}
}