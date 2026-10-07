<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminUser extends Authenticatable
{
    protected $table = 'pena_admin_users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'auth_version'];

    protected $attributes = ['role' => 'editor', 'is_active' => true, 'auth_version' => 1];

    public function isAdministrator(): bool
    {
        return $this->is_active && $this->role === 'admin';
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'auth_version' => 'integer', 'legacy_person_id' => 'integer'];
    }
}
