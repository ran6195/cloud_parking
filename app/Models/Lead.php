<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'source_url',
        'handled',
    ];

    protected function casts(): array
    {
        return [
            'handled' => 'boolean',
        ];
    }
}
