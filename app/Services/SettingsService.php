<?php

namespace App\Services;

use App\Models\Setting;

#[Singleton]
class SettingsService
{
	protected ?array $cache = null;

    protected array $defaults = [
		'node_server' => false,
		'cron_key' => 'a1b2c3d4',
        'reports' => [],
    ];

	public function get(string $key, $default = null)
    {
        $all = $this->all();
        return $all[$key] ?? ($default ?? $this->defaults[$key] ?? null);
    }

	public function only(array $keys): array
    {
        $all = $this->all();

        return collect($all)->only($keys)->all();
    }

	public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->cache = null; // reset memory cache
    }

	public function all(): array
    {
        if ($this->cache === null) {
            $stored = Setting::all()->pluck('value', 'key')->toArray();
            $this->cache = array_merge($this->defaults, $stored);
        }
        return $this->cache;
    }

    public function updateMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }
}