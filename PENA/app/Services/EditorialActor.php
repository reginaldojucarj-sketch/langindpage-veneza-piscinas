<?php

namespace App\Services;

use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

class EditorialActor
{
    public function fresh(AdminUser $actor): AdminUser
    {
        $fresh = AdminUser::query()->lockForUpdate()->find($actor->getKey());
        abort_unless($fresh && $fresh->is_active && $fresh->auth_version === $actor->auth_version && in_array($fresh->role, ['admin', 'editor'], true), 403);

        return $fresh;
    }

    public function ensureStorage(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $tables = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->whereIn('TABLE_NAME', [
                    'pena_authors', 'pena_media', 'pena_content_audit',
                    'pena_post_author_assignments', 'pena_post_media_assignments',
                ])
                ->get();
            abort_unless($tables->count() === 5 && $tables->every(fn ($table) => strtolower($table->ENGINE) === 'innodb'), 503, 'O armazenamento editorial não está instalado corretamente.');

            return;
        }

        abort_unless(DB::getDriverName() === 'sqlite' && app()->environment('testing'), 503, 'Banco editorial não suportado.');
    }
}
