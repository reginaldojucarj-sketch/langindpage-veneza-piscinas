<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alterar senha | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-users.css') }}">
</head>
<body>
<main class="dashboard">
    <p><a href="{{ route('admin.dashboard') }}">← Voltar ao painel</a></p>
    <h1>Alterar minha senha</h1>
    <p>Após salvar, entre novamente. Todas as suas sessões anteriores serão revogadas.</p>
    @if ($errors->any())
        <ul class="error" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <section class="panel">
        <form method="post" action="{{ route('admin.account.password.update') }}" class="form-stack">
            @csrf
            @include('admin.password-fields')
            <button type="submit">Alterar senha</button>
        </form>
    </section>
</main>
</body>
</html>
