<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-users.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form>
    </header>
    <main class="dashboard">
        <p><a href="{{ route('admin.dashboard') }}">← Voltar ao painel</a></p>
        <h1>Usuários administrativos</h1>
        <p>Contas com acesso ao painel. Cadastre apenas pessoas autorizadas.</p>
        @if (session('status')) <p class="success" role="status">{{ session('status') }}</p> @endif
        <section class="panel">
            <h2>Novo usuário</h2>
            <form method="post" action="{{ route('admin.users.store') }}" class="form-stack">
                @csrf
                <label for="name">Nome</label>
                <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120">
                @error('name') <p class="error" role="alert">{{ $message }}</p> @enderror
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email') <p class="error" role="alert">{{ $message }}</p> @enderror
                <label for="role">Perfil</label>
                <select id="role" name="role" required>
                    <option value="editor" @selected(old('role', 'editor') === 'editor')>Editor</option>
                    <option value="admin" @selected(old('role') === 'admin')>Administrador</option>
                </select>
                @error('role') <p class="error" role="alert">{{ $message }}</p> @enderror
                <label for="password">Senha (mínimo de 12 caracteres; máximo de 72 bytes)</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72">
                @error('password') <p class="error" role="alert">{{ $message }}</p> @enderror
                <label for="password_confirmation">Confirmar senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="72">
                <button type="submit">Cadastrar usuário</button>
            </form>
        </section>
        <section class="panel">
            <h2>Contas existentes</h2>
            <ul class="user-list">
                @foreach ($users as $user)
                    <li><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span>
                        <span>{{ $user->role === 'admin' ? 'Administrador' : 'Editor' }} · {{ $user->is_active ? 'Ativo' : 'Inativo' }}</span>
                        <a href="{{ route('admin.users.edit', $user) }}">Editar {{ $user->name }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    </main>
</body>
</html>
