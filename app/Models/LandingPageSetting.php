<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPageSetting extends Model
{
    /** @use HasFactory<\Database\Factories\LandingPageSettingFactory> */
    protected $fillable = ['key', 'value'];
}
