<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises the rich-text HTML that flux:editor produces for posts and comments before
 * it is stored, so a user cannot inject scripts or unsafe markup. A conservative
 * allow-list of formatting tags is kept, plus the data-mention attribute that the
 * mention parser relies on.
 */
final class HtmlContent
{
    public static function sanitize(?string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('span')
            ->allowElement('a', ['href'])
            ->allowAttribute('data-mention', ['span', 'a'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener nofollow ugc');

        return trim((new HtmlSanitizer($config))->sanitize((string) $html));
    }
}
