<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsLoginBackground extends Model
{
    protected $fillable = [
        'image_path',
        'title',
        'order',
    ];
}
