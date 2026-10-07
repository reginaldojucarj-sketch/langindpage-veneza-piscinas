<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorialMedia extends Model
{
    protected $table = 'pena_media';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'original_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
