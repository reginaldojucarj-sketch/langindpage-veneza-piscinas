<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ordem dos artigos | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-order.css') }}">
    <script src="{{ asset('admin-order.js') }}" defer></script>
</head>
<body>
    <header class="topbar">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form>
    </header>
    <main class="dashboard">
        <p><a href="{{ route('admin.dashboard') }}">&larr; Voltar ao painel</a></p>
        <h1>Ordem dos artigos</h1>
        <p>Os artigos publicados aparecem no site nesta ordem. Use os bot&otilde;es para mover cada item e salve ao terminar.</p>
        @if (session('status')) <p class="success" role="status">{{ session('status') }}</p> @endif
        @error('ids') <p class="error" role="alert">{{ $message }}</p> @enderror
        @if ($conflictMessage)
            <p class="error" role="alert">{{ $conflictMessage }}</p>
            @if ($correlationId)<p>Refer&ecirc;ncia: {{ $correlationId }}</p>@endif
            @if (!empty($unavailableDraftIds))
                <p class="notice">Estes itens do rascunho j&aacute; n&atilde;o est&atilde;o publicados e foram retirados da nova ordem: {{ implode(', ', $unavailableDraftIds) }}.</p>
            @endif
        @endif
        @if (!$canSave)
            <p class="notice">O armazenamento transacional da ordena&ccedil;&atilde;o n&atilde;o est&aacute; instalado ou est&aacute; indispon&iacute;vel. Nenhuma altera&ccedil;&atilde;o pode ser salva.</p>
        @elseif (count($posts) > 0)
            <form method="post" action="{{ route('admin.posts.order.update') }}">
                @csrf
                <input type="hidden" name="expected_revision" value="{{ $revision }}">
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <p>Revis&atilde;o editorial: {{ $revision }}</p>
                <ol class="order-list" id="post-order-list">
                    @foreach ($posts as $post)
                        <li>
                            <input type="hidden" name="ids[]" value="{{ $post['id'] }}">
                            <span>{{ $post['title'] ?: 'Artigo '.$post['id'] }}</span>
                            <span class="order-controls">
                                <button type="button" class="secondary" data-move="up" aria-label="Mover {{ $post['title'] }} para cima">&uarr;</button>
                                <button type="button" class="secondary" data-move="down" aria-label="Mover {{ $post['title'] }} para baixo">&darr;</button>
                            </span>
                        </li>
                    @endforeach
                </ol>
                <button type="submit">Salvar ordem</button>
            </form>
        @else
            <p>N&atilde;o h&aacute; artigos publicados.</p>
        @endif
    </main>
</body>
</html>
