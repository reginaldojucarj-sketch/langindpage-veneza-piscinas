<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <h1>Acessar o painel</h1>
        <p>Entre com seu usuário autorizado para gerenciar o conteúdo.</p>
        <form method="post" action="{{ route('admin.login') }}">
            @csrf
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('email') <p class="error" role="alert">{{ $message }}</p> @enderror
            @error('password') <p class="error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
