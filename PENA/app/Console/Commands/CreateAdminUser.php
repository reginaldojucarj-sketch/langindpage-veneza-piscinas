<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Services\AdminAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreateAdminUser extends Command
{
    protected $signature = 'pena:create-admin
        {--backup= : Caminho absoluto para um dump físico verificado}
        {--sha256= : SHA-256 esperado do dump}';

    protected $description = 'Cria uma conta de administrador após verificar o backup local';

    public function handle(): int
    {
        $backup = $this->option('backup');
        $expected = strtolower((string) $this->option('sha256'));

        if (! $backup || ! is_file($backup) || filesize($backup) === 0 ||
            ! preg_match('/^[a-f0-9]{64}$/', $expected) ||
            ! hash_equals($expected, hash_file('sha256', $backup))) {
            $this->error('Informe um backup físico não vazio e seu SHA-256 correto. Nenhum usuário foi criado.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('pena_admin_users') || ! Schema::hasTable('pena_admin_access_lock') || ! Schema::hasColumn('pena_admin_users', 'role')) {
            $this->error('A tabela pena_admin_users não existe. Verifique o esquema e aplique a migração somente após o backup.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nome do administrador'));
        $email = strtolower(trim((string) $this->ask('E-mail')));
        $password = (string) $this->secret('Senha (mínimo de 12 caracteres)');
        $confirmation = (string) $this->secret('Confirme a senha');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 ||
            ! hash_equals($password, $confirmation)) {
            $this->error('Nome, e-mail ou senha inválidos. Nenhum usuário foi criado.');

            return self::FAILURE;
        }

        if (AdminUser::where('email', $email)->exists()) {
            $this->error('Já existe um administrador com esse e-mail.');

            return self::FAILURE;
        }

        try {
            app(AdminAccounts::class)->createFromConsole([
                'name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation,
            ]);
        } catch (ValidationException) {
            $this->error('Dados inválidos. Nenhum usuário foi criado.');

            return self::FAILURE;
        }
        $this->info('Administrador criado. A senha não foi exibida nem gravada em texto aberto.');

        return self::SUCCESS;
    }
}
