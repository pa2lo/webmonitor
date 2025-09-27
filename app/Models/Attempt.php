<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
	const UPDATED_AT = null;

    protected $guarded = [];

	protected $casts = [
		'phrase' => 'boolean',
		'redirected' => 'boolean'
	];

	public function website() {
		return $this->belongsTo(Website::class);
	}
}
