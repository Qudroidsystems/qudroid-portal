<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $table = 'hr_holidays';

    protected $fillable = ['date', 'name'];

    protected $casts = ['date' => 'date'];
}
