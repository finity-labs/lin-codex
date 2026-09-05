<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Html\SanitizerFactory;
use FinityLabs\LinCodex\Rendering\RenderedArticle;

describe('sanitizer', function (): void {
    it('drops event handlers, style attributes and foreign classes', function (): void {
        expect(SanitizerFactory::make()->sanitize('<p onclick="x()" style="color:red" class="fi-x codex-lead">a</p>'))
            ->toBe('<p class="codex-lead">a</p>');
    });

    it('drops scripts, embeds, styles and form controls with their content', function (): void {
        $html = '<script>alert(1)</script><iframe src="x"></iframe><object></object><embed><form><input><button>b</button></form><style>p{}</style><p>kept</p>';
        $clean = SanitizerFactory::make()->sanitize($html);

        expect($clean)->toBe('<p>kept</p>')
            ->and($clean)->not->toContain('alert')
            ->and($clean)->not->toContain('b<');
    });

    it('drops javascript hrefs and keeps relative links and images', function (): void {
        $sanitizer = SanitizerFactory::make();

        expect($sanitizer->sanitize('<a href="javascript:alert(1)">x</a>'))->toBe('<a>x</a>');
        expect($sanitizer->sanitize('<a href="roles.md">r</a>'))->toBe('<a href="roles.md">r</a>');
        expect($sanitizer->sanitize('<img src="images/a.png" alt="a">'))->toContain('src="images/a.png"')->toContain('alt="a"');
    });

    it('keeps ids on headings only', function (): void {
        $sanitizer = SanitizerFactory::make();

        expect($sanitizer->sanitize('<h2 id="custom" class="codex-h">T</h2>'))->toBe('<h2 id="custom" class="codex-h">T</h2>');
        expect($sanitizer->sanitize('<p id="nope">x</p>'))->toBe('<p>x</p>');
    });

    it('keeps every attribute the markdown renderer emits', function (): void {
        $sanitizer = SanitizerFactory::make();

        $image = $sanitizer->sanitize('<img src="/a.png" alt="x" loading="lazy" decoding="async" data-codex-lightbox>');
        expect($image)->toContain('src="/a.png"')->toContain('alt="x"')->toContain('loading="lazy"')->toContain('decoding="async"')->toContain('data-codex-lightbox');

        $link = $sanitizer->sanitize('<a href="/help/x" data-codex-article="x" target="_blank" rel="noopener noreferrer">l</a>');
        expect($link)->toContain('href="/help/x"')->toContain('data-codex-article="x"')->toContain('target="_blank"')->toContain('rel="noopener noreferrer"');

        $aside = $sanitizer->sanitize('<aside role="note"><span aria-hidden="true"></span><a aria-label="L" href="#x">#</a></aside>');
        expect($aside)->toContain('<aside role="note">')->toContain('<span aria-hidden="true">')->toContain('aria-label="L"')->toContain('href="#x"');
    });

    it('keeps figures, details, tables, code blocks, rules and breaks', function (): void {
        $html = '<figure><figcaption>c</figcaption></figure><details><summary>s</summary>d</details><table><thead><tr><th align="right">h</th></tr></thead><tbody><tr><td>c</td></tr></tbody></table><pre><code class="language-php">x</code></pre><hr><br>';
        $clean = SanitizerFactory::make()->sanitize($html);

        expect($clean)
            ->toContain('<figure><figcaption>c</figcaption></figure>')
            ->toContain('<details><summary>s</summary>d</details>')
            ->toContain('<table><thead><tr><th align="right">h</th></tr></thead><tbody><tr><td>c</td></tr></tbody></table>')
            ->toContain('<pre><code class="language-php">x</code></pre>')
            ->toContain('<hr')
            ->toContain('<br');
    });

    it('does not truncate a 100 KB body unless configured to', function (): void {
        $body = '<p>'.str_repeat('a', 100000).' END</p>';

        expect(SanitizerFactory::make()->sanitize($body))->toContain('END');

        config()->set('lin-codex.render.sanitizer.max_input_length', 100);

        expect(SanitizerFactory::make()->sanitize($body))->not->toContain('END');
    });
});

describe('pipeline', function (): void {
    it('returns a rendered article with search text and empty metadata', function (): void {
        $dir = dirname(__DIR__, 2).'/Fixtures/render/html';
        $result = app(HtmlPipeline::class)->render((string) file_get_contents("$dir/headings.input.html"), 'en', 'users/permissions');

        expect($result)->toBeInstanceOf(RenderedArticle::class)
            ->and($result->metadata)->toBe(['front_matter' => null, 'warnings' => []])
            ->and($result->plainText)->toBe('Title Reset a password Step one Reset a password Author id Deep Ärger über Straße x');
    });

    it('renders an empty body to nothing', function (): void {
        $result = app(HtmlPipeline::class)->render('', 'en', 'x');

        expect($result->html)->toBe('')
            ->and($result->toc)->toBe([])
            ->and($result->plainText)->toBe('')
            ->and($result->metadata['warnings'])->toBe([]);
    });

    it('round-trips utf-8 and entities', function (): void {
        $result = app(HtmlPipeline::class)->render('<p>é &amp; ü</p>', 'en', 'x');

        expect($result->html)->toContain('é')->toContain('&amp;')->toContain('ü');
    });

    it('slugs heading ids for the requested locale', function (): void {
        $result = app(HtmlPipeline::class)->render('<h2>Ärger</h2>', 'de', 'x');

        expect($result->html)->toContain('<h2 id="aerger">');
    });

    it('treats the app url host as internal', function (): void {
        config()->set('app.url', 'https://app.test');
        $pipeline = new HtmlPipeline(SanitizerFactory::make());

        $result = $pipeline->render('<p><a href="https://app.test/x">in</a><a href="http://localhost/x">out</a></p>', 'en', 'x');

        expect($result->html)->toContain('<a href="https://app.test/x">in</a>')
            ->and($result->html)->toContain('<a href="http://localhost/x" target="_blank" rel="noopener noreferrer" class="codex-external">out</a>');
    });

    it('exposes scalar fingerprint input', function (): void {
        $input = app(HtmlPipeline::class)->fingerprintInput();

        expect($input)->toHaveKeys(['sanitizer', 'help_center', 'internal_host'])
            ->and($input['internal_host'])->toBe('localhost')
            ->and(json_encode($input))->toBeString();
    });
});
