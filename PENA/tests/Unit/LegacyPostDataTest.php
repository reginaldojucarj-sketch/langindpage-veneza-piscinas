<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use App\Support\LegacyPostData;
use PHPUnit\Framework\TestCase;

class LegacyPostDataTest extends TestCase
{
    private function row(array $values = []): object
    {
        return (object) array_replace(array_fill_keys([
            'title', 'html', 'description', 'snippet', 'highlight', 'published_at',
            'created_at', 'updated_at', 'slug', 'keywords', 'author_signature',
            'first_name', 'last_name', 'category', 'categories', 'post_image',
            'media_image', 'image_name', 'sort_order',
        ], null), ['id' => '7', 'status' => 'PP'], $values);
    }

    public function test_numeric_fields_and_nulls_have_stable_types(): void
    {
        $post = LegacyPostData::fromRow($this->row(), new HtmlSanitizer);
        $this->assertSame(7, $post['id']);
        $this->assertNull($post['sort_order']);
        $this->assertNull($post['author']);
        $this->assertNull($post['image']);
        $this->assertSame(2, LegacyPostData::fromRow($this->row(['sort_order' => '2']), new HtmlSanitizer)['sort_order']);
    }

    public function test_signature_and_post_image_take_precedence(): void
    {
        $post = LegacyPostData::fromRow($this->row([
            'author_signature' => ' Equipe Veneza ', 'first_name' => 'Outro',
            'post_image' => 'posts/capa.jpg', 'media_image' => 'midia/capa.jpg',
            'categories' => 'Piscinas,Água', 'category' => 'Piscinas',
        ]), new HtmlSanitizer);
        $this->assertSame('Equipe Veneza', $post['author']);
        $this->assertSame('posts/capa.jpg', $post['image']);
        $this->assertSame('Piscinas,Água', $post['categories']);
    }

    public function test_author_is_not_inferred_from_person_and_legacy_image_is_used(): void
    {
        $post = LegacyPostData::fromRow($this->row([
            'first_name' => 'Ana', 'last_name' => 'Silva',
            'media_image' => 'midia/capa.jpg', 'category' => 'Água',
        ]), new HtmlSanitizer);
        $this->assertNull($post['author']);
        $this->assertSame('midia/capa.jpg', $post['image']);
        $this->assertSame('Água', $post['categories']);
    }

    public function test_adapter_preserves_editorial_content_and_does_not_leak_join_fields(): void
    {
        $post = LegacyPostData::fromRow($this->row(['html' => '<p>Água &amp; segurança</p>']), new HtmlSanitizer);
        $this->assertSame('<p>Água &amp; segurança</p>', $post['html']);
        $this->assertArrayNotHasKey('author_signature', $post);
        $this->assertArrayNotHasKey('post_image', $post);
        $this->assertCount(18, $post);
    }

    public function test_listing_summary_excludes_full_html(): void
    {
        $post = LegacyPostData::summaryFromRow($this->row([
            'title' => 'Artigo', 'description' => 'Resumo', 'html' => '<p>Conteúdo integral</p>',
        ]));

        $this->assertSame('Resumo', $post['description']);
        $this->assertArrayNotHasKey('html', $post);
    }
}
