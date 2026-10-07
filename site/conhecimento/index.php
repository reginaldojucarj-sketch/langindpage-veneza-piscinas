<?php
declare(strict_types=1);

require_once __DIR__.'/../lib/knowledge.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
$apiOrigin = veneza_public_api_origin();
if ($apiOrigin === null && getenv('VENEZA_SITE_TEST_MODE') !== '1') {
    http_response_code(503);
    header('Retry-After: 60');
    exit('Central de Conhecimento temporariamente indisponível.');
}
$html = file_get_contents(__DIR__.'/../conhecimento.html');
if ($html === false) {
    http_response_code(503);
    exit('Central de Conhecimento temporariamente indisponível.');
}
// The static preview lives at /conhecimento.html; this route keeps all its relative links valid.
$apiMeta = $apiOrigin !== null && strpos($apiOrigin, 'https://') === 0
    ? '<meta name="veneza-public-api-origin" content="'.veneza_escape($apiOrigin).'">'
    : '';
echo str_replace('<head>', '<head><base href="/"><link rel="canonical" href="https://venezapiscinas.com.br/conhecimento/"><meta property="og:url" content="https://venezapiscinas.com.br/conhecimento/">'.$apiMeta, $html);
