<?php

namespace App\Services;

use App\Models\AdminUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAccounts
{
    /** All administrative account writes serialize on one InnoDB row. */
    private function locked(callable $operation): mixed
    {
        try {
            return $this->transaction($operation);
        } catch (QueryException $error) {
            // Never report SQL bindings: writes may contain password hashes.
            Log::error('Falha no banco administrativo.', ['sqlstate' => $error->errorInfo[0] ?? 'unknown']);
            abort(503, 'Acesso temporariamente indisponível.');
        }
    }

    private function transaction(callable $operation): mixed
    {
        if (DB::getDriverName() === 'mysql') {
            $tables = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->whereIn('TABLE_NAME', ['pena_admin_users', 'pena_admin_access_lock', 'pena_admin_audit'])->get();
            abort_unless($tables->count() === 3 && $tables->every(fn ($table) => strtolower($table->ENGINE) === 'innodb'), 503, 'As tabelas administrativas precisam de InnoDB.');
        } elseif (DB::getDriverName() !== 'sqlite' || ! app()->environment('testing')) {
            abort(503, 'Banco administrativo não suportado.');
        }

        return DB::transaction(function () use ($operation) {
            $lock = DB::table('pena_admin_access_lock')->where('id', 1)->lockForUpdate()->first();
            abort_unless($lock, 503, 'Controle de acesso não instalado.');

            return $operation();
        }, 3);
    }

    private function actor(AdminUser $actor, bool $adminOnly = true): AdminUser
    {
        $fresh = AdminUser::query()->lockForUpdate()->find($actor->id);
        abort_unless($fresh && $fresh->is_active && $fresh->auth_version === $actor->auth_version &&
            in_array($fresh->role, ['admin', 'editor'], true) && (! $adminOnly || $fresh->isAdministrator()), 403);

        return $fresh;
    }

    private function audit(?int $actorId, AdminUser $target, string $action, array $fields): void
    {
        DB::table('pena_admin_audit')->insert([
            'actor_id' => $actorId, 'target_id' => $target->id, 'action' => $action,
            'changed_fields' => json_encode(array_values($fields), JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function validate(array $input, ?AdminUser $target = null): array
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = strtolower(trim($input['email']));
        }
        if (isset($input['name']) && is_string($input['name'])) {
            $input['name'] = trim($input['name']);
        }
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique('pena_admin_users', 'email')->ignore($target?->id)],
            'role' => ['required', Rule::in(['admin', 'editor'])],
            'is_active' => ['required', 'boolean'],
            'legacy_person_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('PESSOA_pena', 'ID_PESSOA'), Rule::unique('pena_admin_users', 'legacy_person_id')->ignore($target?->id)],
        ];
        if ($target) {
            $rules['expected_version'] = ['required', 'integer', 'min:1', 'max:2147483647'];
        } else {
            $rules['password'] = $this->passwordRules();
        }

        return Validator::make($input, $rules)->validate();
    }

    private function passwordRules(): array
    {
        return ['bail', 'required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('A senha deve ter no máximo 72 bytes. Caracteres acentuados podem ocupar mais de um byte.');
            }
            if (str_contains($value, "\0")) {
                $fail('A senha contém um caractere não permitido.');
            }
        }];
    }

    public function create(AdminUser $actor, array $input): AdminUser
    {
        return $this->locked(function () use ($actor, $input) {
            $fresh = $this->actor($actor);

            return $this->insert($input, $fresh->id, 'account.created');
        });
    }

    /** For the backup-guarded local CLI only, never expose this method as a route. */
    public function createFromConsole(array $input): AdminUser
    {
        return $this->locked(fn () => $this->insert(array_merge($input, ['role' => 'admin', 'is_active' => true]), null, 'account.created_cli'));
    }

    private function insert(array $input, ?int $actorId, string $action): AdminUser
    {
        $data = $this->validate($input);
        // Eloquent's "hashed" cast preserves values that already look like
        // configured hashes. Hash the submitted literal explicitly first.
        $data['password'] = Hash::make($data['password']);
        $user = new AdminUser;
        $user->forceFill($data)->save();
        $this->audit($actorId, $user, $action, ['name', 'email', 'role', 'is_active', 'legacy_person_id']);

        return $user;
    }

    public function update(AdminUser $actor, int $id, array $input): AdminUser
    {
        return $this->locked(function () use ($actor, $id, $input) {
            $fresh = $this->actor($actor);
            $target = AdminUser::query()->lockForUpdate()->findOrFail($id);
            $data = $this->validate($input, $target);
            if ((int) $data['expected_version'] !== $target->auth_version) {
                throw ValidationException::withMessages(['expected_version' => 'Esta conta foi alterada desde que você abriu o formulário. Recarregue a página e revise os dados.']);
            }
            unset($data['expected_version']);
            if ($target->isAdministrator() && ($data['role'] !== 'admin' || ! $data['is_active'])) {
                // Current reads after the global account lock, not a stale snapshot.
                $admins = AdminUser::where('role', 'admin')->where('is_active', true)->lockForUpdate()->get();
                if ($admins->count() <= 1) {
                    throw ValidationException::withMessages(['role' => 'Não é possível desativar ou rebaixar o último administrador ativo.']);
                }
            }
            $target->forceFill($data);
            $fields = array_keys($target->getDirty());
            if ($fields !== []) {
                $target->auth_version++;
                $target->remember_token = null;
                $target->save();
                $this->audit($fresh->id, $target, 'account.updated', $fields);
            }

            return $target;
        });
    }

    public function changePassword(AdminUser $actor, int $id, array $input): void
    {
        $this->locked(function () use ($actor, $id, $input) {
            $fresh = $this->actor($actor, false);
            abort_unless($fresh->id === $id || $fresh->isAdministrator(), 403);
            $data = Validator::make($input, [
                'current_password' => ['required', 'string'],
                'password' => $this->passwordRules(),
            ])->validate();
            if (! Hash::check($data['current_password'], $fresh->password)) {
                throw ValidationException::withMessages(['current_password' => 'Sua senha atual está incorreta.']);
            }
            $target = AdminUser::query()->lockForUpdate()->findOrFail($id);
            $target->password = Hash::make($data['password']);
            $target->auth_version++;
            $target->remember_token = null;
            $target->save();
            $this->audit($fresh->id, $target, $fresh->id === $id ? 'password.changed' : 'password.reset_admin', ['password']);
        });
    }
}
