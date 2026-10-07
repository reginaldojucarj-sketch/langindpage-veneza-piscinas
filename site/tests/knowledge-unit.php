<?php
declare(strict_types=1);

require __DIR__.'/../lib/knowledge.php';

function check($condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

putenv('VENEZA_PUBLIC_API_ORIGIN=https://api.example.test');
$post = ['id' => 7, 'slug' => 'agua-da-piscina', 'title' => 'Água & sol', 'status' => 'PP', 'html' => '<p>Seguro</p>'];
$response = static function (string $url) use ($post): array {
    check($url === 'https://api.example.test/api/public/posts/slug/agua-da-piscina', 'URL de API inesperada');
    return ['status' => 200, 'body' => json_encode(['data' => $post])];
};
check(veneza_fetch_article('slug', 'agua-da-piscina', $response)['status'] === 200, 'Artigo válido recusado');

foreach ([
    ['status' => 404, 'body' => ''], ['status' => 410, 'body' => ''],
    ['status' => 503, 'body' => ''], ['status' => 200, 'body' => '{json inválido'],
    ['status' => 200, 'body' => json_encode(['data' => array_replace($post, ['status' => 'PO'])])],
    ['status' => 200, 'body' => json_encode(['data' => array_replace($post, ['slug' => 'outro-slug'])])],
    ['status' => 200, 'body' => json_encode(['data' => array_replace($post, ['id' => 8])])],
] as $index => $fixture) {
    $kind = $index === 6 ? 'id' : 'slug';
    $identifier = $kind === 'id' ? '7' : 'agua-da-piscina';
    $result = veneza_fetch_article($kind, $identifier, static fn () => $fixture);
    check($result['status'] === ($index < 2 ? $fixture['status'] : ($index === 4 ? 404 : 503)), 'Resposta inválida não recusada: '.$index);
}

check(veneza_valid_slug('agua-da-piscina'), 'Slug válido recusado');
foreach (['', 'ÁGUA', 'agua/', 'https://evil.test', str_repeat('a', 201)] as $slug) {
    check(! veneza_valid_slug($slug), 'Slug inseguro aceito');
}
check(veneza_safe_url('uploads/capa.jpg') === 'https://pena.venezapiscinas.com.br/uploads/capa.jpg', 'Mídia legada não resolvida');
foreach (['javascript:alert(1)', '//evil.example/x', 'data:text/html,x'] as $url) {
    check(veneza_safe_url($url) === null, 'Protocolo inseguro aceito');
}

$safe = veneza_sanitize_html('<p onclick="alert(1)">Água <strong>limpa</strong><script>malicioso()</script></p>'
    .'<img src="uploads/capa.jpg" onerror="malicioso()" alt="Capa">'
    .'<iframe src="https://www.youtube.com/embed/abc_123" title="Vídeo"></iframe>'
    .'<iframe src="https://videos.example.test/watch" title="Outro"></iframe>'
    .'<a href="javascript:malicioso()">texto</a>');
check(strpos($safe, '<strong>limpa</strong>') !== false, 'Formatação legítima removida');
check(strpos($safe, 'https://pena.venezapiscinas.com.br/uploads/capa.jpg') !== false, 'Imagem legítima removida');
check(strpos($safe, 'https://www.youtube.com/embed/abc_123') !== false, 'YouTube legítimo removido');
check(strpos($safe, 'Abrir mídia do artigo') !== false, 'Embed externo não convertido em link seguro');
foreach (['<script', 'onclick', 'onerror', 'javascript:', 'malicioso()'] as $unsafe) {
    check(strpos($safe, $unsafe) === false, 'HTML perigoso preservado: '.$unsafe);
}
check(veneza_escape('<script>') === '&lt;script&gt;', 'Metadado não escapado');
check(veneza_description(['description' => '<p>Água &amp; sol</p>']) === 'Água & sol', 'Descrição incorreta');

check(veneza_public_api_origin() === 'https://api.example.test', 'Origem HTTPS recusada');
putenv('VENEZA_PUBLIC_API_ORIGIN=https://api.example.test/path');
check(veneza_public_api_origin() === null, 'Origem com caminho aceita');
putenv('VENEZA_PUBLIC_API_ORIGIN=https://api.example.test');
ob_start();
require __DIR__.'/../conhecimento/index.php';
$listing = ob_get_clean();
check(strpos($listing, '<meta name="veneza-public-api-origin" content="https://api.example.test">') !== false,
    'Homologação HTTPS não configurou a listagem pela API');

echo "PASS: contrato, falhas, URLs, metadados e sanitização DOM.\n";
