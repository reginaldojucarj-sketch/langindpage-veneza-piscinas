<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Biblioteca de mídias | PENA</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-content.css') }}">
    <script src="{{ asset('admin-media.js') }}" defer></script>
</head>
<body>
    <header class="topbar">
        <div class="brand">PENA <small>Veneza Piscinas</small></div>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="secondary" type="submit">Sair</button></form>
    </header>
    <main class="dashboard content-dashboard" data-media-library>
        <p><a href="{{ route('admin.dashboard') }}">← Voltar ao painel</a></p>
        <h1>Biblioteca de mídias</h1>
        <p>JPEG, PNG e WebP; até 2 MB por arquivo e 6.000 px por lado. O limite de pixels se adapta à memória PHP disponível (máximo absoluto de 20 megapixels). A imagem pública é normalizada, redimensionada a no máximo 1.800 px e tem metadados EXIF removidos. O original permanece privado.</p>
        @if (session('status')) <p class="success" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <div class="error-box" role="alert"><p>Não foi possível concluir:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="panel">
            <h2>Enviar imagem</h2>
            <form method="post" enctype="multipart/form-data" action="{{ route('admin.media.store') }}" class="form-stack" data-media-upload>
                @csrf
                <label for="media-file">Arquivo</label>
                <input id="media-file" type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
                <label for="alt_text">Texto alternativo</label>
                <input id="alt_text" name="alt_text" value="{{ old('alt_text') }}" maxlength="255" required>
                <p class="content-note">Não envie SVG, HTML, PDF, vídeo ou arquivo executável. Nomes de arquivo são ignorados para gerar o identificador armazenado.</p>
                <progress value="0" max="100" data-upload-progress hidden></progress>
                <p role="status" aria-live="polite" data-upload-status></p>
                <button type="submit" data-upload-button>Enviar e processar imagem</button>
            </form>
        </section>
        <section aria-labelledby="uploaded-heading">
            <h2 id="uploaded-heading">Imagens enviadas</h2>
            @if (count($uploaded))
                <div class="media-grid">
                    @foreach ($uploaded as $item)
                        <article class="media-card" data-media-card data-search-text="{{ $item['name'].' '.$item['alt_text'] }}">
                            @if ($item['is_active'])
                                <img src="{{ $item['preview_url'] }}" alt="{{ $item['alt_text'] }}" loading="lazy" width="{{ $item['width'] }}" height="{{ $item['height'] }}">
                            @else
                                <div class="media-placeholder" role="img" aria-label="Imagem inativa">Imagem inativa; arquivo privado preservado</div>
                            @endif
                            <div class="media-card__body">
                                <h3>{{ $item['name'] }}</h3>
                                <p>{{ $item['mime'] }} · {{ number_format($item['bytes'] / 1024, 0, ',', '.') }} KB · {{ $item['width'] }} × {{ $item['height'] }}</p>
                                <form method="post" action="{{ route('admin.media.alt-text', $item['id']) }}" class="form-stack media-alt-form">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="expected_version" value="{{ $item['version'] }}">
                                    <label for="alt-text-{{ $item['id'] }}">Texto alternativo</label>
                                    <input id="alt-text-{{ $item['id'] }}" name="alt_text" value="{{ $item['alt_text'] }}" maxlength="255" required>
                                    <button type="submit" class="secondary">Salvar texto alternativo</button>
                                </form>
                                <p>{{ $item['is_active'] ? 'Ativa' : 'Inativa' }} · enviada por {{ $item['uploaded_by_name'] ?? 'conta removida' }} em {{ $item['created_at'] }}</p>
                                @if ($item['selectable'])
                                    <button type="button" data-use-media data-media-kind="managed" data-media-id="{{ $item['id'] }}" data-media-url="{{ $item['selection_url'] }}" data-media-alt="{{ $item['alt_text'] }}">Selecionar para artigo</button>
                                @endif
                                @if ($item['is_active'])
                                    <form method="post" action="{{ route('admin.media.deactivate', $item['id']) }}" class="inline-form" data-confirm-deactivate>
                                        @csrf @method('PATCH')<button type="submit" class="danger-button">Desativar</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('admin.media.activate', $item['id']) }}" class="inline-form">
                                        @csrf @method('PATCH')<button type="submit">Reativar</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="empty-state">Nenhum arquivo novo enviado ainda. As capas legadas abaixo continuam preservadas.</p>
            @endif
        </section>
        <section aria-labelledby="legacy-heading">
            <h2 id="legacy-heading">Mídias legadas em uso</h2>
            <p>URLs antigas não são baixadas nem alteradas. A disponibilidade dos arquivos na hospedagem ainda precisa ser confirmada.</p>
            @if (count($legacy))
                <div class="media-grid">
                    @foreach ($legacy as $item)
                        <article class="media-card" data-media-card data-search-text="{{ $item['name'].' '.$item['id'] }}">
                            @if ($item['preview_url'])
                                <img src="{{ $item['preview_url'] }}" alt="{{ $item['name'] }}" loading="lazy">
                            @else
                                <div class="media-placeholder" role="img" aria-label="Prévia indisponível por segurança">Prévia indisponível</div>
                            @endif
                            <div class="media-card__body">
                                <h3>{{ $item['name'] }}</h3>
                                @if ($item['references'] !== null)<p>Referenciada em {{ $item['references'] }} {{ $item['references'] === 1 ? 'artigo' : 'artigos' }}</p>@else<p>Cadastro de imagem legado</p>@endif
                                @if ($item['selectable'])
                                    <button type="button" class="secondary" data-use-media data-media-kind="legacy" data-media-id="{{ $item['id'] }}" data-media-url="{{ $item['selection_url'] }}" data-media-alt="{{ $item['name'] }}">Selecionar para artigo</button>
                                @else
                                    <p class="content-note">Não disponível para seleção: o endereço não pertence a um host HTTPS autorizado.</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="empty-state">Nenhuma capa legada foi encontrada. A tabela de imagens do backup estava vazia.</p>
            @endif
        </section>
        <section class="panel media-selection" aria-labelledby="selection-heading" data-selection-output hidden>
            <h2 id="selection-heading">Mídia selecionada</h2>
            <p data-selection-label></p>
            <label for="selected-media-url">URL pronta para uso</label>
            <input id="selected-media-url" type="url" readonly data-selection-url>
            <button type="button" class="secondary" data-copy-media>Copiar URL</button>
            <p role="status" aria-live="polite" data-selection-status></p>
        </section>
        <p class="content-note">Se você abriu a biblioteca pelo editor, escolha uma imagem para preencher a capa no formulário que ficou aberto. Confira a seleção antes de salvar. Sem seleção automática, copie a URL acima e cole no campo “URL da capa existente”. A biblioteca não altera arquivos antigos.</p>
    </main>
</body>
</html>
