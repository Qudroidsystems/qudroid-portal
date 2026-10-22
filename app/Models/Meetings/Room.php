<?php

namespace App\Models\Meetings;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'meeting_rooms';

    protected $fillable = ['name', 'capacity', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
