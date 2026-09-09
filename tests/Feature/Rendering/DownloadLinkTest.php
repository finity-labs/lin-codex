<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\DownloadLink;
use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Markdown\MarkdownPipeline;

/*
 * A link to a file saves the file. Both pipelines stamp the download
 * attribute from the same rule, so an article reads the same whichever
 * format it was written in.
 */

it('stamps a Markdown link to a document with its file name', function (): void {
    $html = (new MarkdownPipeline)->render('Read the [guide](/storage/codex/2026/09/user%20guide.pdf) first.', 'en', 'intro')->html;

    expect($html)->toContain('<a download="user guide.pdf" href="/storage/codex/2026/09/user%20guide.pdf">guide</a>');
});

it('leaves article links, page links and images alone', function (): void {
    $html = (new MarkdownPipeline)->render(
        '[Roles](roles.md) and [Home](/home) and ![Shot](/storage/codex/shot.png) and [Site](https://example.com/page)',
        'en',
        'intro',
    )->html;

    expect($html)->not->toContain('download=');
});

it('stamps an HTML article link the same way and keeps an authored attribute', function (): void {
    $pipeline = app(HtmlPipeline::class);

    expect($pipeline->render('<p><a href="/storage/codex/report.xlsx">Report</a></p>', 'en', 'intro')->html)
        ->toContain('download="report.xlsx"');

    expect($pipeline->render('<p><a href="/files/x.bin" download="x.bin">Blob</a></p>', 'en', 'intro')->html)
        ->toContain('download="x.bin"');
});

it('follows the configured extension list', function (): void {
    config()->set('lin-codex.render.download_extensions', ['.ZIP', 'pdf']);

    expect(DownloadLink::extensions())->toBe(['zip', 'pdf'])
        ->and(DownloadLink::fileName('/files/archive.zip?v=2'))->toBe('archive.zip')
        ->and(DownloadLink::fileName('/files/report.docx'))->toBeNull()
        ->and(DownloadLink::fileName('https://cdn.example.com/paper.PDF#p2'))->toBe('paper.PDF')
        ->and(DownloadLink::fileName('/help/users'))->toBeNull()
        ->and(DownloadLink::fileName('#top'))->toBeNull();
});
