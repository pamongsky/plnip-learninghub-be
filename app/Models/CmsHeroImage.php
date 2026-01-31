<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsHeroImage extends Model
{
    /** @use HasFactory<\Database\Factories\CmsHeroImageFactory> */
    protected $fillable = ['image_path', 'title', 'order'];
}
