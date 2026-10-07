<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Servi&ccedil;o indispon&iacute;vel | PENA</title><link rel="stylesheet" href="{{ asset('admin.css') }}"></head>
<body class="auth-page">
    <main class="auth-card">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <h1>Servi&ccedil;o temporariamente indispon&iacute;vel</h1>
        <p>Nenhuma altera&ccedil;&atilde;o foi confirmada. Tente novamente mais tarde.</p>
        @isset($correlationId)<p>Refer&ecirc;ncia: {{ $correlationId }}</p>@endisset
        <p><a href="/admin">Voltar para o painel</a></p>
    </main>
</body>
</html>
