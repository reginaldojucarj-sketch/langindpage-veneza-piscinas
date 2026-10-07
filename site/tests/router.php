<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/conhecimento/([a-z0-9]+(?:-[a-z0-9]+)*)/?$~D', (string) $path, $match) === 1) {
    $_GET['slug'] = $match[1];
    require __DIR__.'/../article.php';
    return true;
}
if ($path === '/artigo.html') {
    require __DIR__.'/../article.php';
    return true;
}
if ($path === '/conhecimento.html') {
    header('Location: /conhecimento/', true, 302);
    return true;
}
if ($path === '/admin' || $path === '/admin/') {
    require __DIR__.'/../admin/index.php';
    return true;
}
if ($path === '/conhecimento' || $path === '/conhecimento/') {
    require __DIR__.'/../conhecimento/index.php';
    return true;
}
if (strpos((string) $path, '/conhecimento/') === 0) {
    http_response_code(404);
    return true;
}
return false;
