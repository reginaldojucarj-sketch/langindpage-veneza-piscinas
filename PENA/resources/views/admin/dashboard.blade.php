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
        <p>O acesso está protegido. O gerenciamento de usuários, autores, mídias, posts e ordem editorial depende da verificação do esquema e do backup físico da base existente.</p>
        <section class="notice" aria-label="Estado da integração">
            <h2>Integração pendente</h2>
            <p>Nenhuma alteração foi feita no banco de produção. As ferramentas de edição serão liberadas somente após uma cópia de segurança verificável.</p>
        </section>
    </main>
</body>
</html>
