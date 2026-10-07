<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form>
    </header>
    <main class="dashboard">
        <h1>Painel administrativo</h1>
        <p>Seu perfil define as ferramentas disponíveis. Você pode alterar sua própria senha; administradores também gerenciam contas e a ordem dos artigos.</p>
        <section class="notice" aria-label="Estado da integração">
            <h2>Conteúdo em desenvolvimento</h2>
            <p>O editor de posts está preparado localmente. A gravação permanece bloqueada enquanto o acervo estiver em MyISAM ou houver escritor legado sem coordenação. A instalação exige backup e revisão.</p>
        </section>
        @can('order-posts')<p><a href="{{ route('admin.posts.order.index') }}">Organizar a ordem dos artigos publicados →</a></p>@endcan
        @can('edit-content')<p><a href="{{ route('admin.posts.index') }}">Gerenciar artigos →</a></p><p><a href="{{ route('admin.authors.index') }}">Gerenciar autores →</a></p><p><a href="{{ route('admin.media.index') }}">Biblioteca de mídias →</a></p>@endcan
        @can('manage-users')<p><a href="{{ route('admin.users.index') }}">Gerenciar usuários →</a></p>@endcan
        <p><a href="{{ route('admin.account.password') }}">Alterar minha senha →</a></p>
    </main>
</body>
</html>
