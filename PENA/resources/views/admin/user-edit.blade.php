<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar usuário | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-users.css') }}">
</head>
<body>
<main class="dashboard">
    <p><a href="{{ route('admin.users.index') }}">← Voltar aos usuários</a></p>
    <h1>Editar {{ $user->name }}</h1>
    @if (session('status')) <p class="success" role="status">{{ session('status') }}</p> @endif
    @if ($errors->any())
        <ul class="error" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <section class="panel">
        <h2>Dados e acesso</h2>
        <p>Alterações revogam as sessões anteriores desta conta. Não é possível desativar ou rebaixar o último administrador ativo.</p>
        <form method="post" action="{{ route('admin.users.update', $user) }}" class="form-stack">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_version" value="{{ old('expected_version', $user->auth_version) }}">
            <label for="name">Nome</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required minlength="2" maxlength="120" autocomplete="name">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">
            <label for="role">Perfil</label>
            <select id="role" name="role" required>
                <option value="editor" @selected(old('role', $user->role) === 'editor')>Editor</option>
                <option value="admin" @selected(old('role', $user->role) === 'admin')>Administrador</option>
            </select>
            <label for="is_active">Acesso</label>
            <select id="is_active" name="is_active" required>
                <option value="1" @selected((string) old('is_active', (int) $user->is_active) === '1')>Ativo</option>
                <option value="0" @selected((string) old('is_active', (int) $user->is_active) === '0')>Inativo</option>
            </select>
            <label for="legacy_person_id">ID da pessoa legada (opcional)</label>
            <input id="legacy_person_id" name="legacy_person_id" type="number" min="1" value="{{ old('legacy_person_id', $user->legacy_person_id) }}" aria-describedby="legacy-help">
            <p id="legacy-help">Preencha somente após confirmar a identidade em PESSOA_pena. Esse vínculo não define o autor editorial e não importa senhas antigas.</p>
            <button type="submit">Salvar alterações</button>
        </form>
    </section>
    <section class="panel">
        <h2>Redefinir senha desta conta</h2>
        <p>Confirme com a sua própria senha. Entregue a nova senha por canal privado e solicite sua troca. Não há recuperação automática por e-mail.</p>
        <form method="post" action="{{ route('admin.users.password', $user) }}" class="form-stack">
            @csrf
            @include('admin.password-fields')
            <button type="submit">Redefinir senha</button>
        </form>
    </section>
</main>
</body>
</html>
