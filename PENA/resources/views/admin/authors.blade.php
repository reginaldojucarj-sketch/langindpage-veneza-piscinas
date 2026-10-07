<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autores | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-content.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form>
    </header>
    <main class="dashboard content-dashboard">
        <p><a href="{{ route('admin.dashboard') }}">← Voltar ao painel</a></p>
        <div class="content-heading"><div><h1>Autores</h1><p>Gerencie os perfis sem alterar os registros legados. Artigos antigos preservam a assinatura registrada na época.</p></div><a class="button-link" href="{{ route('admin.authors.create') }}">Cadastrar autor</a></div>
        @if (session('status')) <p class="success" role="status">{{ session('status') }}</p> @endif
        <form method="get" action="{{ route('admin.authors.index') }}" class="search-form">
            <label for="author-search">Buscar autor</label>
            <input id="author-search" type="search" name="q" value="{{ $search }}" maxlength="100">
            <button type="submit">Buscar</button>
        </form>
        @if (count($authors))
            <div class="content-table-wrap">
                <table class="content-table">
                    <thead><tr><th>Assinatura</th><th>Pessoa vinculada</th><th>URL amigável</th><th>Artigos</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                    @foreach ($authors as $author)
                        <tr>
                            <td><strong>{{ $author['signature'] }}</strong>@if ($author['legacy_author_id'])<small>Registro legado #{{ $author['legacy_author_id'] }}</small>@endif</td>
                            <td>{{ trim(($author['person_first_name'] ?? '').' '.($author['person_last_name'] ?? '')) ?: 'Pessoa não localizada' }}</td>
                            <td><code>{{ $author['slug'] }}</code></td>
                            <td>{{ $author['article_count'] }}</td>
                            <td>{{ $author['is_active'] ? 'Ativo' : 'Inativo' }}</td>
                            <td><a href="{{ route('admin.authors.edit', $author['id']) }}">Editar</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="empty-state">Nenhum autor cadastrado.</p>
        @endif
        <p class="content-note">Desativar impede novos vínculos, mas não apaga o autor nem troca sua assinatura em artigos históricos. Autores sem identidade explicitamente selecionada não são criados.</p>
    </main>
</body>
</html>
