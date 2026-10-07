<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $author ? 'Editar autor' : 'Novo autor' }} | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-content.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form>
    </header>
    <main class="dashboard content-dashboard">
        <p><a href="{{ route('admin.authors.index') }}">← Voltar aos autores</a></p>
        <h1>{{ $author ? 'Editar autor' : 'Cadastrar autor' }}</h1>
        <p>O vínculo com a pessoa é explícito e não pode ser inferido a partir do usuário que está conectado.</p>
        @if (session('status')) <p class="success" role="status">{{ session('status') }}</p> @endif
        <form method="post" action="{{ $author ? route('admin.authors.update', $author) : route('admin.authors.store') }}" class="form-stack content-form">
            @csrf
            @if ($author) @method('PATCH')<input type="hidden" name="expected_version" value="{{ $author->version }}"> @endif
            @if (!$author)
                <label for="person_id">Pessoa que representa este autor</label>
                <select id="person_id" name="person_id" required>
                    <option value="">Selecione a pessoa após confirmar a identidade</option>
                    @foreach ($people as $person)
                        <option value="{{ $person['id'] }}" @selected((string) old('person_id') === (string) $person['id'])>{{ $person['first_name'].' '.$person['last_name'] }}</option>
                    @endforeach
                </select>
                @error('person_id') <p class="error" role="alert">{{ $message }}</p> @enderror
            @else
                @php($person = collect($people)->firstWhere('id', $author->person_id))
                <p><strong>Pessoa vinculada:</strong> {{ $person ? $person['first_name'].' '.$person['last_name'] : 'Pessoa não localizada' }}. A identidade do autor não pode ser alterada neste formulário.</p>
                @if ($author->legacy_author_id)<p class="content-note">Vínculo somente de leitura com o autor legado #{{ $author->legacy_author_id }}.</p>@endif
            @endif
            <label for="signature">Assinatura pública</label>
            <input id="signature" name="signature" value="{{ old('signature', $author->signature ?? '') }}" maxlength="50" required autocomplete="off">
            @error('signature') <p class="error" role="alert">{{ $message }}</p> @enderror
            <label for="slug">URL amigável</label>
            <input id="slug" name="slug" value="{{ old('slug', $author->slug ?? '') }}" maxlength="50" pattern="[a-z0-9]+(-[a-z0-9]+)*" required autocomplete="off">
            @error('slug') <p class="error" role="alert">{{ $message }}</p> @enderror
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="5" maxlength="5000">{{ old('description', $author->description ?? '') }}</textarea>
            @error('description') <p class="error" role="alert">{{ $message }}</p> @enderror
            @if ($author)
                <label for="photo_media_id">Foto do autor</label>
                <select id="photo_media_id" name="photo_media_id">
                    <option value="">Remover foto da biblioteca</option>
                    @foreach ($media as $item)
                        <option value="{{ $item['id'] }}" @selected(old('photo_media_id', $author->photo_media_id) === $item['id'])>{{ $item['name'] }} · {{ $item['width'] }} × {{ $item['height'] }}@unless($item['is_active']) · inativa; vínculo atual preservado @endunless</option>
                    @endforeach
                </select>
                <p><a href="{{ route('admin.media.index') }}">Abrir biblioteca de mídias para enviar uma foto</a></p>
                @error('photo_media_id') <p class="error" role="alert">{{ $message }}</p> @enderror
            @endif
            @error('expected_version') <p class="error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">{{ $author ? 'Salvar alterações' : 'Cadastrar autor' }}</button>
        </form>
        @if ($author && $author->is_active)
            <section class="panel danger-panel">
                <h2>Desativar autor</h2>
                <p>O perfil continuará preservado. A assinatura histórica dos artigos não será substituída.</p>
                <form method="post" action="{{ route('admin.authors.deactivate', $author) }}">
                    @csrf @method('PATCH')<input type="hidden" name="expected_version" value="{{ $author->version }}">
                    <button type="submit" class="danger-button">Desativar este autor</button>
                </form>
            </section>
        @elseif ($author)
            <section class="panel">
                <h2>Autor inativo</h2>
                <p>Os vínculos históricos foram mantidos. Ao reativar, o autor poderá ser selecionado em novos artigos.</p>
                <form method="post" action="{{ route('admin.authors.activate', $author) }}">
                    @csrf @method('PATCH')<input type="hidden" name="expected_version" value="{{ $author->version }}">
                    <button type="submit">Reativar autor</button>
                </form>
            </section>
        @endif
    </main>
</body>
</html>
