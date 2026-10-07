<?php

/** Protected OpenAPI document. Examples are synthetic and never read from the database. */
$ref = fn (string $name): array => ['$ref' => '#/components/schemas/'.$name];
$object = function (array $properties, array $required = []): array {
    $schema = ['type' => 'object', 'properties' => $properties];
    if ($required !== []) {
        $schema['required'] = $required;
    }

    return $schema;
};
$text = ['type' => 'string'];
$id = ['type' => 'integer', 'minimum' => 1];
$version = ['type' => 'integer', 'minimum' => 1];
$fingerprint = ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
$uuid = ['type' => 'string', 'format' => 'uuid'];
$status = ['type' => 'string', 'enum' => ['PP', 'PO', 'PE', 'PR']];

$schemas = [
    'Error' => $object(['message' => $text, 'errors' => ['type' => 'object', 'additionalProperties' => true]]),
    'User' => $object(['id' => $id, 'name' => $text, 'email' => ['type' => 'string', 'format' => 'email'], 'role' => ['type' => 'string', 'enum' => ['admin', 'editor']], 'is_active' => ['type' => 'boolean'], 'legacy_person_id' => ['type' => 'integer', 'nullable' => true], 'auth_version' => $version]),
    'UserCreate' => $object(['name' => ['type' => 'string', 'example' => 'Pessoa de teste'], 'email' => ['type' => 'string', 'format' => 'email', 'example' => 'pessoa@example.test'], 'password' => ['type' => 'string', 'minLength' => 12, 'maxLength' => 72, 'format' => 'password'], 'password_confirmation' => ['type' => 'string', 'format' => 'password'], 'role' => ['type' => 'string', 'enum' => ['admin', 'editor']], 'legacy_person_id' => $id], ['name', 'email', 'password', 'password_confirmation']),
    'UserUpdate' => $object(['name' => $text, 'email' => ['type' => 'string', 'format' => 'email'], 'role' => ['type' => 'string', 'enum' => ['admin', 'editor']], 'is_active' => ['type' => 'boolean'], 'legacy_person_id' => $id, 'expected_version' => $version], ['name', 'email', 'role', 'is_active', 'expected_version']),
    'Author' => $object(['id' => $id, 'legacy_author_id' => ['type' => 'integer', 'nullable' => true], 'person_id' => $id, 'signature' => $text, 'slug' => $text, 'description' => ['type' => 'string', 'nullable' => true], 'photo_media_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true], 'is_active' => ['type' => 'boolean'], 'version' => $version]),
    'AuthorCreate' => $object(['person_id' => $id, 'signature' => ['type' => 'string', 'example' => 'Autoria de teste'], 'slug' => ['type' => 'string', 'example' => 'autoria-de-teste'], 'description' => $text], ['person_id', 'signature', 'slug']),
    'AuthorUpdate' => $object(['signature' => $text, 'slug' => $text, 'description' => $text, 'photo_media_id' => $uuid, 'expected_version' => $version], ['signature', 'slug', 'expected_version']),
    'Version' => $object(['expected_version' => $version], ['expected_version']),
    'Media' => $object(['id' => $uuid, 'original_name' => $text, 'public_mime' => $text, 'width' => $id, 'height' => $id, 'alt_text' => $text, 'is_active' => ['type' => 'boolean'], 'version' => $version]),
    'MediaListing' => $object(['uploaded' => ['type' => 'array', 'items' => $object(['id' => $uuid, 'name' => $text, 'mime' => $text, 'preview_url' => $text])], 'legacy' => ['type' => 'array', 'items' => $object(['id' => $text, 'name' => $text, 'preview_url' => ['type' => 'string', 'nullable' => true]])]], ['uploaded', 'legacy']),
    'MediaAlt' => $object(['alt_text' => $text, 'expected_version' => $version], ['alt_text', 'expected_version']),
    'MediaUpload' => $object(['file' => ['type' => 'string', 'format' => 'binary', 'description' => 'JPEG, PNG ou WebP; no máximo 2 MiB.'], 'alt_text' => $text], ['file', 'alt_text']),
    'PostSummary' => $object(['ID_POST' => $id, 'TITULO_POST' => $text, 'LINK_POST' => $text, 'STATUS_POST' => $status]),
    'Post' => $object(['id' => $id, 'title' => $text, 'slug' => $text, 'status' => $status, 'html' => $text, 'snippet' => ['type' => 'string', 'nullable' => true], 'description' => ['type' => 'string', 'nullable' => true], 'keywords' => ['type' => 'string', 'nullable' => true], 'cover_url' => ['type' => 'string', 'nullable' => true], 'author_id' => ['type' => 'integer', 'nullable' => true], 'media_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true], 'main_category_id' => ['type' => 'integer', 'nullable' => true], 'category_ids' => ['type' => 'array', 'items' => $id], 'fingerprint' => $fingerprint]),
    'PostInput' => $object(['title' => ['type' => 'string', 'maxLength' => 71, 'example' => 'Cuidados com a piscina'], 'slug' => ['type' => 'string', 'maxLength' => 200, 'example' => 'cuidados-com-a-piscina'], 'html' => ['type' => 'string', 'description' => 'HTML sanitizado pelo servidor.', 'example' => '<p>Conteúdo demonstrativo.</p>'], 'snippet' => ['type' => 'string', 'maxLength' => 156], 'description' => $text, 'keywords' => ['type' => 'string', 'maxLength' => 200], 'cover_url' => ['type' => 'string', 'maxLength' => 200], 'author_id' => $id, 'media_id' => $uuid, 'main_category_id' => $id, 'category_ids' => ['type' => 'array', 'items' => $id, 'uniqueItems' => true]], ['title', 'html', 'author_id', 'main_category_id', 'category_ids']),
    'PostUpdate' => $object(['title' => $text, 'slug' => $text, 'html' => $text, 'snippet' => $text, 'description' => $text, 'keywords' => $text, 'cover_url' => $text, 'author_id' => $id, 'media_id' => $uuid, 'main_category_id' => $id, 'category_ids' => ['type' => 'array', 'items' => $id], 'expected_fingerprint' => $fingerprint], ['title', 'slug', 'html', 'author_id', 'main_category_id', 'category_ids', 'expected_fingerprint']),
    'PostTransition' => $object(['expected_fingerprint' => $fingerprint], ['expected_fingerprint']),
    'PostDelete' => $object(['expected_fingerprint' => $fingerprint, 'confirm_delete' => ['type' => 'boolean', 'enum' => [true]]], ['expected_fingerprint', 'confirm_delete']),
    'OrderUpdate' => $object(['ids' => ['type' => 'array', 'items' => $id, 'uniqueItems' => true], 'expected_revision' => $version, 'idempotency_key' => $uuid], ['ids', 'expected_revision', 'idempotency_key']),
    'PublicSummary' => $object(['id' => $id, 'title' => $text, 'slug' => $text, 'status' => ['type' => 'string', 'enum' => ['PP']], 'description' => ['type' => 'string', 'nullable' => true], 'snippet' => ['type' => 'string', 'nullable' => true], 'image' => ['type' => 'string', 'nullable' => true], 'author' => ['type' => 'string', 'nullable' => true], 'category' => ['type' => 'string', 'nullable' => true], 'sort_order' => ['type' => 'integer', 'nullable' => true]]),
    'PublicDetail' => ['allOf' => [$ref('PublicSummary'), $object(['html' => $text], ['html'])]],
    'OrderResult' => $object(['revision' => $version, 'count' => $id, 'replayed' => ['type' => 'boolean']]),
    'OrderEntry' => $object(['id' => $id, 'title' => $text, 'slug' => $text, 'sort_order' => ['type' => 'integer', 'nullable' => true]]),
    'PageMeta' => $object(['current_page' => $id, 'last_page' => $id, 'per_page' => $id, 'total' => ['type' => 'integer', 'minimum' => 0], 'snapshot' => $text]),
];

$definitions = [
    ['get', '/api/public/posts', 'Pública', 'Lista somente artigos publicados, com paginação, busca e snapshot.', null, 'PublicSummary', true, ['page', 'per_page', 'q']],
    ['get', '/api/public/posts/slug/{slug}', 'Pública', 'Consulta um artigo publicado pela URL amigável existente; não publicados retornam 404.', null, 'PublicDetail', false],
    ['get', '/api/public/posts/{id}', 'Pública', 'Consulta um artigo publicado por ID; não publicados retornam 404.', null, 'PublicDetail', false],
    ['get', '/api/admin/v1/me', 'Sessão', 'Consulta a conta autenticada.', null, 'User'],
    ['get', '/api/admin/v1/users', 'Usuários', 'Lista contas administrativas.', null, 'User', true],
    ['post', '/api/admin/v1/users', 'Usuários', 'Cadastra uma conta administrativa.', 'UserCreate', 'User'],
    ['get', '/api/admin/v1/users/{user}', 'Usuários', 'Consulta uma conta.', null, 'User'],
    ['patch', '/api/admin/v1/users/{user}', 'Usuários', 'Atualiza conta com versão otimista.', 'UserUpdate', 'User'],
    ['get', '/api/admin/v1/authors', 'Autores', 'Lista autores; q é opcional.', null, 'Author', true, ['q']],
    ['post', '/api/admin/v1/authors', 'Autores', 'Cria autor vinculado a pessoa existente.', 'AuthorCreate', 'Author'],
    ['get', '/api/admin/v1/authors/{author}', 'Autores', 'Consulta autor.', null, 'Author'],
    ['patch', '/api/admin/v1/authors/{author}', 'Autores', 'Atualiza autor e foto.', 'AuthorUpdate', 'Author'],
    ['patch', '/api/admin/v1/authors/{author}/activate', 'Autores', 'Reativa autor.', 'Version', 'Author'],
    ['patch', '/api/admin/v1/authors/{author}/deactivate', 'Autores', 'Desativa autor sem mudar assinatura histórica.', 'Version', 'Author'],
    ['get', '/api/admin/v1/media', 'Mídias', 'Lista uploads e referências legadas.', null, 'MediaListing'],
    ['post', '/api/admin/v1/media', 'Mídias', 'Envia imagem e a reprocessa antes de servir.', 'MediaUpload', 'Media'],
    ['get', '/api/admin/v1/media/{media}', 'Mídias', 'Consulta metadados de uma mídia.', null, 'Media'],
    ['patch', '/api/admin/v1/media/{media}', 'Mídias', 'Atualiza texto alternativo com versão otimista.', 'MediaAlt', 'Media'],
    ['patch', '/api/admin/v1/media/{media}/activate', 'Mídias', 'Reativa a mídia.', null, 'Media'],
    ['patch', '/api/admin/v1/media/{media}/deactivate', 'Mídias', 'Desativa mídia sem remover arquivo; recusa se estiver em uso.', null, 'Media'],
    ['get', '/api/admin/v1/posts/order', 'Ordem', 'Lista resumos publicados e revisão da ordem.', null, 'OrderEntry', true],
    ['put', '/api/admin/v1/posts/order', 'Ordem', 'Salva ordem completa com revisão e idempotência.', 'OrderUpdate', 'OrderResult'],
    ['get', '/api/admin/v1/posts', 'Posts', 'Lista posts de todos os estados, paginados em 25.', null, 'PostSummary', true, ['q', 'status', 'page']],
    ['post', '/api/admin/v1/posts', 'Posts', 'Cria post oculto; requer vínculo explícito do ator com pessoa legada.', 'PostInput', 'Post'],
    ['get', '/api/admin/v1/posts/{post}', 'Posts', 'Consulta rascunho e fingerprint; HTML já sanitizado.', null, 'Post'],
    ['put', '/api/admin/v1/posts/{post}', 'Posts', 'Edita post se fingerprint ainda for atual.', 'PostUpdate', 'Post'],
    ['post', '/api/admin/v1/posts/{post}/publish', 'Posts', 'Publica post oculto.', 'PostTransition', 'Post'],
    ['post', '/api/admin/v1/posts/{post}/hide', 'Posts', 'Oculta post publicado.', 'PostTransition', 'Post'],
    ['post', '/api/admin/v1/posts/{post}/delete', 'Posts', 'Exclui logicamente; não apaga o registro.', 'PostDelete', 'Post'],
];

$paths = [];
foreach ($definitions as $definition) {
    [$method, $path, $tag, $summary, $request, $response] = $definition;
    $isList = $definition[6] ?? false;
    $query = $definition[7] ?? [];
    $parameters = [];
    if (preg_match_all('/\{([^}]+)\}/', $path, $matches)) {
        foreach ($matches[1] as $name) {
            $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true,
                'schema' => $name === 'media' ? $uuid : ($name === 'slug'
                    ? ['type' => 'string', 'maxLength' => 200, 'pattern' => '^[a-z0-9]+(?:-[a-z0-9]+)*$'] : $id)];
        }
    }
    foreach ($query as $name) {
        $querySchema = in_array($name, ['page', 'per_page'], true) ? $id : ($name === 'status' ? $status : $text);
        if ($name === 'per_page') {
            $querySchema['maximum'] = 100;
        } elseif ($name === 'page') {
            $querySchema['maximum'] = 10000;
        } elseif ($name === 'q') {
            $querySchema['maxLength'] = $path === '/api/public/posts' ? 150 : 100;
        }
        $parameters[] = ['name' => $name, 'in' => 'query', 'required' => false,
            'schema' => $querySchema];
    }
    $content = $isList ? ['type' => 'array', 'items' => $ref($response)] : $ref($response);
    $envelope = $object(['data' => $content], ['data']);
    if ($isList && in_array($path, ['/api/public/posts', '/api/admin/v1/posts'], true)) {
        $envelope['properties']['meta'] = $ref('PageMeta');
        $envelope['required'][] = 'meta';
    } elseif ($path === '/api/admin/v1/posts/order' && $method === 'get') {
        $envelope['properties']['meta'] = $object(['revision' => ['type' => 'integer', 'nullable' => true]], ['revision']);
        $envelope['required'][] = 'meta';
    }
    $successCode = $method === 'post' && in_array($path, ['/api/admin/v1/users', '/api/admin/v1/authors', '/api/admin/v1/media', '/api/admin/v1/posts'], true) ? '201' : '200';
    $operation = [
        'tags' => [$tag], 'summary' => $summary,
        'operationId' => $method.'_'.trim(preg_replace('/[^a-z0-9]+/i', '_', $path), '_'),
        'security' => str_starts_with($path, '/api/public/') ? [] : [['sessionCookie' => []]],
        'responses' => [$successCode => [
            'description' => 'Sucesso',
            'content' => ['application/json' => ['schema' => $envelope]],
        ]],
    ];
    if ($parameters !== []) {
        $operation['parameters'] = $parameters;
    }
    if ($request !== null) {
        $mime = $request === 'MediaUpload' ? 'multipart/form-data' : 'application/json';
        $operation['requestBody'] = ['required' => true, 'content' => [$mime => ['schema' => $ref($request)]]];
    }
    $descriptions = ['401' => 'Sem sessão', '403' => 'Sem permissão', '404' => 'Não encontrado', '409' => 'Conflito', '419' => 'CSRF inválido', '422' => 'Validação', '429' => 'Limite de requisições', '503' => 'Indisponível'];
    if (str_starts_with($path, '/api/public/')) {
        $codes = $path === '/api/public/posts' ? ['409', '422', '503'] : ['404', '503'];
    } else {
        $codes = ['401', '403', '503'];
        if ($parameters !== [] && preg_match('/\{/', $path)) {
            $codes[] = '404';
        }
        if ($method !== 'get') {
            array_push($codes, '419', '422', '429');
            if (str_contains($path, '/posts')) {
                $codes[] = '409';
            }
        } elseif ($query !== []) {
            $codes[] = '422';
        }
    }
    foreach ($codes as $code) {
        $operation['responses'][$code] = ['description' => $descriptions[$code], 'content' => ['application/json' => ['schema' => $ref('Error')]]];
    }
    $paths[$path][$method] = $operation;
}

return [
    'openapi' => '3.0.3',
    'info' => ['title' => 'PENA — API', 'version' => '1.0.0', 'description' => 'Documentação privada. As operações administrativas usam a sessão do PENA e exigem CSRF em escritas. “Try it out” atua no ambiente atual. Nenhum token fica no JavaScript.'],
    'servers' => [['url' => '/', 'description' => 'Origem atual']],
    'tags' => array_map(fn ($name) => ['name' => $name], ['Pública', 'Sessão', 'Usuários', 'Autores', 'Mídias', 'Posts', 'Ordem']),
    'paths' => $paths,
    'components' => ['securitySchemes' => ['sessionCookie' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => config('session.cookie'), 'description' => 'Cookie host-only da sessão PENA; o navegador envia automaticamente. Escritas também requerem X-CSRF-TOKEN.']], 'schemas' => $schemas],
];
