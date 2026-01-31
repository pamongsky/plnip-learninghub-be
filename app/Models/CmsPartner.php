<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPartner extends Model
{
    /** @use HasFactory<\Database\Factories\CmsPartnerFactory> */
    protected $fillable = ['name', 'logo_path'];
}
