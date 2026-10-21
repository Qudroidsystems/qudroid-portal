<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultDisplaySetting extends Model
{
    protected $table = 'result_display_settings';

    protected $fillable = ['parents_can_view', 'students_can_view', 'position_basis', 'fields', 'updated_by'];

    protected $casts = [
        'parents_can_view'  => 'boolean',
        'students_can_view' => 'boolean',
        'fields'            => 'array',
    ];
}
