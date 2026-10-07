<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json; charset=utf-8');
$slug = substr((string) $path, strlen('/api/public/posts/slug/'));
if ($path === '/api/public/posts/7') $slug = 'agua-da-piscina';
if ($slug === 'retirado' || $path === '/api/public/posts/8') {
    http_response_code(404);
    echo '{"message":"Artigo não encontrado."}';
    return;
}
if ($slug === 'indisponivel') {
    http_response_code(503);
    echo '{"message":"Indisponível."}';
    return;
}
if ($slug === 'quebrado') {
    echo '{json inválido';
    return;
}
if (! in_array($slug, ['agua-da-piscina', 'divergente', 'oculto'], true)) {
    http_response_code(404);
    echo '{}';
    return;
}
echo json_encode(['data' => [
    'id' => 7, 'slug' => $slug === 'divergente' ? 'outro-slug' : $slug,
    'title' => 'Água & sol', 'description' => 'Como cuidar da água com segurança.',
    'status' => $slug === 'oculto' ? 'PO' : 'PP', 'category' => 'Tratamento',
    'author' => 'Equipe Veneza', 'published_at' => '2026-01-02 10:30:00',
    'image' => 'uploads/capa.jpg', 'image_name' => 'Capa da água',
    'html' => '<p>Conteúdo <strong>seguro</strong><script>malicioso()</script></p>'
        .'<img src="uploads/foto.jpg" alt="Foto"><iframe src="https://www.youtube.com/embed/abc123" title="Vídeo"></iframe>',
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
