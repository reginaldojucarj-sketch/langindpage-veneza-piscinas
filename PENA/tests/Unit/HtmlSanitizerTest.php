<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_preserves_supported_article_markup_and_safe_youtube_embeds(): void
    {
        $html = '<section><h2>Tratamento da água</h2><p>Texto <strong>legítimo</strong> e <a href="https://example.com/artigo" title="Fonte">fonte</a>.</p>'
            .'<ul><li>Filtro</li></ul><img src="/media/capa.jpg" alt="Piscina" width="640">'
            .'<figure><figcaption>Legenda</figcaption></figure>'
            .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0" width="560" height="315" title="Vídeo"></iframe></section>';

        $clean = (new HtmlSanitizer)->sanitize($html);

        $this->assertStringContainsString('<h2>Tratamento da água</h2>', $clean);
        $this->assertStringContainsString('<strong>legítimo</strong>', $clean);
        $this->assertStringContainsString('href="https://example.com/artigo"', $clean);
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('alt="Piscina"', $clean);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $clean);
        $this->assertStringContainsString('<figcaption>Legenda</figcaption>', $clean);
    }

    public function test_it_removes_scripts_events_styles_unsafe_urls_and_unapproved_embeds(): void
    {
        $html = '<p onclick="steal()" style="background:url(javascript:alert(1))">Seguro</p>'
            .'<script>alert(1)</script><img src="x" onerror="alert(2)">'
            .'<a href="javascript:alert(3)">link</a><iframe src="https://evil.example/embed/123"></iframe>'
            .'<svg onload="alert(4)"><circle></circle></svg>';

        $clean = (new HtmlSanitizer)->sanitize($html);

        $this->assertStringContainsString('<p>Seguro</p>', $clean);
        foreach (['onclick', 'onerror', 'javascript:', '<script', '<svg', 'evil.example', 'background:'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, strtolower($clean));
        }
    }

    public function test_malformed_utf8_is_rejected_instead_of_silently_rewritten(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HtmlSanitizer)->sanitize("\xC3\x28");
    }
}
