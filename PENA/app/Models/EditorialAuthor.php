<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorialAuthor extends Model
{
    protected $table = 'pena_authors';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'person_id' => 'integer', 'legacy_author_id' => 'integer'];
    }
}
