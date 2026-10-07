<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use InvalidArgumentException;

final class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function sanitize(string $html): string
    {
        if (preg_match('//u', $html) !== 1) {
            throw new InvalidArgumentException('O conteúdo HTML não está em UTF-8 válido.');
        }

        return ($this->purifier ??= $this->buildPurifier())->purify($html);
    }

    private function buildPurifier(): HTMLPurifier
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'p', 'div', 'section', 'br', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote',
            'figure', 'figcaption', 'small', 'sup', 'sub', 'hr', 'pre', 'code',
            'a[href|title]', 'img[src|alt|title|width|height]',
            'iframe[src|width|height|title|allowfullscreen]',
        ]));
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '#\Ahttps://www\.(?:youtube\.com|youtube-nocookie\.com)/embed/[A-Za-z0-9_-]{11}(?:\?[A-Za-z0-9_~.%=&-]*)?\z#D');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('HTML.Trusted', false);
        $config->set('Attr.EnableID', false);
        $config->set('CSS.AllowedProperties', []);
        $config->set('HTML.DefinitionID', 'veneza-pena-article-html5');
        $config->set('HTML.DefinitionRev', 1);
        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addElement('section', 'Block', 'Flow', 'Common');
            $definition->addElement('figure', 'Block', 'Flow', 'Common');
            $definition->addElement('figcaption', 'Block', 'Flow', 'Common');
        }

        return new HTMLPurifier($config);
    }
}
