<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>API administrativa — PENA</title>
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('api-docs.css') }}">
</head>
<body>
    <header class="pena-docs-header">
        <div><strong>PENA · API administrativa</strong><span>Ambiente atual: {{ request()->getHost() }}</span></div>
        <div><a href="{{ route('admin.dashboard') }}">Painel</a><form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Sair</button></form></div>
    </header>
    <main aria-label="Documentação interativa da API">
        <p class="pena-docs-warning">“Try it out” executa requisições reais neste ambiente. Publique, oculte ou exclua somente quando tiver certeza. A escrita de artigos permanece bloqueada enquanto a base legada usar MyISAM.</p>
        <div id="swagger-ui" data-spec-url="{{ route('api.admin.spec') }}"></div>
    </main>
    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}" defer></script>
    <script src="{{ asset('vendor/swagger-ui/swagger-ui-standalone-preset.js') }}" defer></script>
    <script src="{{ asset('api-docs.js') }}" defer></script>
</body>
</html>
