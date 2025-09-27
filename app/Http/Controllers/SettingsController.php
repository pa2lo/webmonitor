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
		$request->validate([
			'cron_key' => 'required|min:8',
			'node_server' => 'required',
            'reports' => 'nullable|array'
        ]);

		$settings->updateMany([
			'cron_key' => $request->cron_key,
			'node_server' => $request->node_server,
            'reports' => $request->reports
		]);

		return back()->with('message', 'success');
	}
}