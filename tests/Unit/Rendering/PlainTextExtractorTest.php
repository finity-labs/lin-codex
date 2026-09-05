<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\PlainTextExtractor;

it('turns rendered html into search text', function (string $html, string $expected): void {
    expect((new PlainTextExtractor)->fromHtml($html))->toBe($expected);
})->with([
    'heading permalink dropped, entities decoded' => [
        '<h2 id="a">Title<a class="codex-anchor" href="#a" aria-label="Link to Title">#</a></h2><p>Body &amp; more</p>',
        'Title Body & more',
    ],
    'alt text and figcaption kept' => [
        '<figure class="codex-figure"><img src="/a.png" alt="Alt text" loading="lazy" decoding="async" data-codex-lightbox /><figcaption>Caption</figcaption></figure>',
        'Alt text Caption',
    ],
    'task list inputs dropped' => [
        '<ul><li><input checked="" disabled="" type="checkbox"> done</li><li>todo</li></ul>',
        'done todo',
    ],
    'whitespace normalised, code kept' => [
        "<p>a</p>\n<p>b&nbsp;c</p><pre><code class=\"language-php\">echo 1;</code></pre>",
        'a b c echo 1;',
    ],
    'callout title and body kept' => [
        '<aside class="codex-callout codex-callout--note" role="note"><p class="codex-callout__title"><span class="codex-callout__icon" aria-hidden="true"></span>Note</p><div class="codex-callout__body"><p>Hi</p></div></aside>',
        'Note Hi',
    ],
    'step number badge dropped, step title and body kept' => [
        '<ol class="codex-steps"><li class="codex-step"><span class="codex-step__number" aria-hidden="true">1</span><div class="codex-step__title">Open</div><div class="codex-step__body"><p>Then</p></div></li></ol>',
        'Open Then',
    ],
    'empty input' => ['', ''],
]);
