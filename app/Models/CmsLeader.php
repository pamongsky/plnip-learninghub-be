<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsLeader extends Model
{
    /** @use HasFactory<\Database\Factories\CmsLeaderFactory> */
    protected $fillable = ['name', 'title', 'initial', 'image_path', 'order'];
}
