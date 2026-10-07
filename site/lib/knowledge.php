<?php
declare(strict_types=1);

const VENEZA_ARTICLE_HOST = 'https://venezapiscinas.com.br';
const VENEZA_LEGACY_MEDIA_BASE = 'https://pena.venezapiscinas.com.br/';

function veneza_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function veneza_valid_slug(string $slug): bool
{
    return strlen($slug) <= 200 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) === 1;
}

function veneza_article_url(string $slug): string
{
    return VENEZA_ARTICLE_HOST.'/conhecimento/'.$slug;
}

function veneza_public_api_origin(): ?string
{
    $origin = rtrim((string) getenv('VENEZA_PUBLIC_API_ORIGIN'), '/');
    $parts = parse_url($origin);
    $localTest = getenv('VENEZA_SITE_TEST_MODE') === '1';
    if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
        || ! in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
        || ($parts['scheme'] !== 'https' && ! ($localTest && in_array($parts['host'], ['127.0.0.1', 'localhost'], true)))) {
        return null;
    }

    return $origin;
}

function veneza_safe_url($value): ?string
{
    if (! is_string($value) || trim($value) === '') {
        return null;
    }
    $value = trim($value);
    if (strpos($value, '//') === 0) {
        return null;
    }
    if ($value[0] === '/' && strpos($value, '/media/') === 0) {
        $value = VENEZA_LEGACY_MEDIA_BASE.ltrim($value, '/');
    } elseif (parse_url($value, PHP_URL_SCHEME) === null) {
        $value = VENEZA_LEGACY_MEDIA_BASE.ltrim($value, '/');
    }
    $parts = parse_url($value);
    if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || empty($parts['host']) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    return $value;
}

/** @return array{status:int,post?:array<string,mixed>} */
function veneza_fetch_article(string $kind, string $identifier, ?callable $transport = null): array
{
    $origin = veneza_public_api_origin();
    if ($origin === null) {
        return ['status' => 503];
    }
    $path = $kind === 'slug' ? '/api/public/posts/slug/' : '/api/public/posts/';
    $url = $origin.$path.rawurlencode($identifier);
    if ($transport !== null) {
        $result = $transport($url);
    } else {
        if (! function_exists('curl_init')) {
            return ['status' => 503];
        }
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'VenezaPiscinasSite/1.0',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2097152) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $result = ['status' => $ok === false ? 503 : (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $body];
    }
    $status = (int) ($result['status'] ?? 503);
    if ($status === 404 || $status === 410) {
        return ['status' => $status];
    }
    if ($status !== 200 || ! isset($result['body']) || ! is_string($result['body'])) {
        return ['status' => 503];
    }
    $payload = json_decode($result['body'], true, 32);
    $post = is_array($payload) ? ($payload['data'] ?? null) : null;
    if (! is_array($post)) {
        return ['status' => 503];
    }
    if (($post['status'] ?? null) !== 'PP') {
        return ['status' => 404];
    }
    if (! isset($post['id'], $post['slug'], $post['title'])
        || ! filter_var($post['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        || ! is_string($post['slug']) || ! veneza_valid_slug($post['slug'])
        || ! is_string($post['title']) || trim($post['title']) === '') {
        return ['status' => 503];
    }
    if (($kind === 'slug' && $post['slug'] !== $identifier)
        || ($kind === 'id' && (string) $post['id'] !== $identifier)) {
        return ['status' => 503];
    }

    return ['status' => 200, 'post' => $post];
}

function veneza_plain_text($value): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function veneza_description(array $post): string
{
    $text = veneza_plain_text($post['description'] ?? $post['snippet'] ?? '');
    if ($text === '') {
        $text = veneza_plain_text($post['html'] ?? '');
    }
    return mb_strlen($text, 'UTF-8') > 160 ? rtrim(mb_substr($text, 0, 159, 'UTF-8')).'…' : $text;
}

function veneza_date($value): ?string
{
    if (! is_string($value) || trim($value) === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('America/Recife'));
    $errors = DateTimeImmutable::getLastErrors();
    return $date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ? null : $date->format(DateTimeInterface::ATOM);
}

function veneza_clean_node(DOMNode $node, DOMDocument $target): ?DOMNode
{
    if ($node instanceof DOMText) {
        return $target->createTextNode($node->textContent);
    }
    if (! ($node instanceof DOMElement)) {
        return null;
    }
    $tag = strtolower($node->tagName);
    if (in_array($tag, ['script', 'style', 'form', 'object', 'embed', 'svg', 'math', 'video', 'audio', 'template'], true)) {
        return null;
    }
    if ($tag === 'img') {
        $url = veneza_safe_url($node->getAttribute('src'));
        if ($url === null) return null;
        $img = $target->createElement('img');
        $img->setAttribute('src', $url);
        $img->setAttribute('alt', $node->getAttribute('alt') ?: 'Imagem do artigo');
        $img->setAttribute('loading', 'lazy');
        return $img;
    }
    if ($tag === 'iframe') {
        $url = veneza_safe_url($node->getAttribute('src'));
        if ($url === null) return null;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (in_array($host, ['www.youtube.com', 'www.youtube-nocookie.com'], true)
            && preg_match('~^/embed/[A-Za-z0-9_-]+$~D', $path) === 1) {
            $frame = $target->createElement('iframe');
            $frame->setAttribute('src', $url);
            $frame->setAttribute('title', $node->getAttribute('title') ?: 'Vídeo do artigo');
            $frame->setAttribute('loading', 'lazy');
            $frame->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            $frame->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation');
            $frame->setAttribute('allowfullscreen', '');
            return $frame;
        }
        $link = $target->createElement('a', 'Abrir mídia do artigo');
        $link->setAttribute('href', $url);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('rel', 'noopener noreferrer');
        return $link;
    }
    $allowed = ['p', 'div', 'section', 'br', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'blockquote', 'figure', 'figcaption', 'small', 'sup', 'sub', 'hr', 'pre', 'code', 'a'];
    $copy = in_array($tag, $allowed, true) ? $target->createElement($tag) : $target->createDocumentFragment();
    if ($tag === 'a') {
        $url = veneza_safe_url($node->getAttribute('href'));
        if ($url !== null) {
            $copy->setAttribute('href', $url);
            $copy->setAttribute('target', '_blank');
            $copy->setAttribute('rel', 'noopener noreferrer');
        }
    }
    foreach ($node->childNodes as $child) {
        $clean = veneza_clean_node($child, $target);
        if ($clean !== null) $copy->appendChild($clean);
    }
    return $copy;
}

function veneza_sanitize_html($html): string
{
    if (! is_string($html) || $html === '') return '';
    $previous = libxml_use_internal_errors(true);
    $source = new DOMDocument('1.0', 'UTF-8');
    $source->loadHTML('<?xml encoding="UTF-8"><div id="article-source">'.$html.'</div>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $source->getElementById('article-source');
    if (! $root) return '';
    $target = new DOMDocument('1.0', 'UTF-8');
    $output = '';
    foreach ($root->childNodes as $child) {
        $clean = veneza_clean_node($child, $target);
        if ($clean !== null) $output .= $target->saveHTML($clean);
    }
    return $output;
}
