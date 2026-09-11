<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPhoto extends Model
{
    protected $fillable = [
        'tournament_external_id', 'tournated_user_id', 'person_key', 'name', 'gender', 'photo',
        'rating_type', 'rating_points', 'country', 'city',
    ];
}
