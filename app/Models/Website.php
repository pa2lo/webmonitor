<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
	protected $guarded = [];

	protected $casts = [
		'active' => 'boolean'
	];

	public function attempts() {
		return $this->hasMany(Attempt::class);
	}

	public function downtimes() {
		return $this->hasMany(Downtime::class);
	}
}