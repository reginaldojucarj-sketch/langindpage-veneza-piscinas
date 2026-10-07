<?php
declare(strict_types=1);

function check_http($condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

/** @return array{status:int,headers:string,body:string} */
function request_site(string $path): array
{
    $handle = curl_init('http://127.0.0.1:8092'.$path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($handle);
    if (! is_string($response)) throw new RuntimeException('Falha HTTP local: '.$path);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    return ['status' => $status, 'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
}

for ($attempt = 0; $attempt < 20; $attempt++) {
    try {
        $first = request_site('/conhecimento/agua-da-piscina');
        if ($first['status'] === 200) break;
    } catch (RuntimeException $error) {
    }
    usleep(100000);
}
check_http(isset($first) && $first['status'] === 200, 'URL amigável direta não respondeu 200');
$body = $first['body'];
check_http(strpos($body, '<html lang="pt-BR">') !== false, 'Idioma ausente');
check_http(strpos($body, '<title>Água &amp; sol | Veneza Piscinas</title>') !== false, 'Título inicial incorreto');
check_http(strpos($body, '<link rel="canonical" href="https://venezapiscinas.com.br/conhecimento/agua-da-piscina">') !== false, 'Canonical inicial ausente');
check_http(strpos($body, '<meta property="og:url" content="https://venezapiscinas.com.br/conhecimento/agua-da-piscina">') !== false, 'Open Graph inicial ausente');
check_http(strpos($body, 'application/ld+json') !== false && strpos($body, 'BlogPosting') !== false, 'Dados estruturados ausentes');
check_http(strpos($body, '<strong>seguro</strong>') !== false, 'HTML legítimo removido');
check_http(strpos($body, 'uploads/foto.jpg') !== false && strpos($body, 'youtube.com/embed/abc123') !== false, 'Mídia legítima removida');
check_http(strpos($body, 'malicioso()') === false, 'HTML inseguro preservado');
check_http(request_site('/conhecimento/agua-da-piscina')['status'] === 200, 'Recarregar URL direta falhou');

foreach (['retirado' => 404, 'oculto' => 404, 'indisponivel' => 503, 'quebrado' => 503, 'divergente' => 503, 'MAIUSCULO' => 404] as $slug => $expected) {
    $response = request_site('/conhecimento/'.$slug);
    check_http($response['status'] === $expected, 'Status incorreto para '.$slug.': '.$response['status'].' em vez de '.$expected);
    check_http(stripos($response['headers'], 'no-store') !== false || $slug === 'MAIUSCULO', 'Cache inseguro em '.$slug);
    check_http(strpos($response['body'], 'Conteúdo <strong>seguro</strong>') === false, 'Artigo ressuscitado em '.$slug);
}
$legacy = request_site('/artigo.html?id=7&next=https://evil.test');
check_http($legacy['status'] === 302 && stripos($legacy['headers'], 'Location: https://venezapiscinas.com.br/conhecimento/agua-da-piscina') !== false, 'ID antigo não redireciona à URL canônica');
check_http(request_site('/artigo.html?id=8')['status'] === 404, 'ID oculto não retornou 404');
$admin = request_site('/admin?next=https://evil.test');
check_http($admin['status'] === 302 && stripos($admin['headers'], 'Location: https://pena.venezapiscinas.com.br/') !== false
    && stripos($admin['headers'], 'evil.test') === false, '/admin não usou destino fixo');
$listing = request_site('/conhecimento/');
check_http($listing['status'] === 200 && strpos($listing['body'], '<base href="/">') !== false, 'Listagem amigável não abriu');
$oldListing = request_site('/conhecimento.html');
check_http($oldListing['status'] === 302 && stripos($oldListing['headers'], 'Location: /conhecimento/') !== false, 'Lista antiga não redireciona');

echo "PASS: HTTP direto, recarga, SEO inicial, sanitização, erros e redirecionamentos.\n";
