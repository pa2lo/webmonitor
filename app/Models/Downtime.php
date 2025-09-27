<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Downtime extends Model
{
    protected $guarded = [];

	protected $casts = [
		'attempts' => 'json'
	];

	public function website() {
		return $this->belongsTo(Website::class);
	}
}
