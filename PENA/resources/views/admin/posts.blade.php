<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Artigos | PENA</title><link rel="stylesheet" href="{{ asset('admin.css') }}"><link rel="stylesheet" href="{{ asset('admin-content.css') }}"><link rel="stylesheet" href="{{ asset('admin-posts.css') }}"></head>
<body>
<header class="topbar"><div class="brand">PENA <small>Veneza Piscinas</small></div><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form></header>
<main class="dashboard content-dashboard">
    <p><a href="{{ route('admin.dashboard') }}">← Voltar ao painel</a></p>
    <div class="content-heading"><div><h1>Artigos</h1><p>PP = publicado; PO = oculto; PE = excluído logicamente. PR não tem significado confirmado e permanece somente leitura.</p></div><a class="button-link" href="{{ route('admin.posts.create') }}">Novo artigo</a></div>
    @unless($canWrite)<p class="error-box" role="status">Gravação bloqueada: o acervo precisa estar em InnoDB, sem escritor legado concorrente, e ser habilitado explicitamente após backup e revisão.</p>@endunless
    @if(session('status'))<p class="success" role="status">{{ session('status') }}</p>@endif
    <form method="get" action="{{ route('admin.posts.index') }}" class="search-form post-search">
        <div class="post-search__field"><label for="post-search">Buscar título ou URL amigável</label><input id="post-search" type="search" name="q" value="{{ $search }}" maxlength="100"></div>
        <div class="post-search__field"><label for="post-status">Estado</label><select id="post-status" name="status"><option value="">Todos</option>@foreach(['PP' => 'Publicado', 'PO' => 'Oculto', 'PE' => 'Excluído', 'PR' => 'PR (não definido)'] as $code => $label)<option value="{{ $code }}" @selected($status === $code)>{{ $label }}</option>@endforeach</select></div>
        <button type="submit">Filtrar</button>
    </form>
    <div class="content-table-wrap"><table class="content-table"><thead><tr><th>ID</th><th>Artigo</th><th>Estado</th><th>Modificado</th><th>Ações</th></tr></thead><tbody>
        @forelse($posts as $post)<tr><td>{{ $post['ID_POST'] }}</td><td><strong>{{ $post['TITULO_POST'] }}</strong><small>{{ $post['LINK_POST'] }}</small></td><td>{{ $post['STATUS_POST'] }}</td><td>{{ $post['DATA_ULTIMA_MODIFICACAO_POST'] ?: '—' }}</td><td><a href="{{ route('admin.posts.edit', $post['ID_POST']) }}">Abrir</a> · <a href="{{ route('admin.posts.preview', $post['ID_POST']) }}">Prévia privada</a></td></tr>
        @empty<tr><td colspan="5">Nenhum artigo encontrado.</td></tr>@endforelse
    </tbody></table></div><p class="content-note">{{ $posts->total() }} artigo(s); 25 por página. A API pública nunca exibe PO, PE ou PR.</p>{{ $posts->links('admin.pagination') }}
</main>
</body></html>
