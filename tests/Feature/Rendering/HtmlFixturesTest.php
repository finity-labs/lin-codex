<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Rendering\Html\HtmlPipeline;
use FinityLabs\LinCodex\Rendering\Html\SanitizerFactory;

/**
 * Golden files pin the HTML-format markup contract: sanitizer output passed
 * through HeadingPass and serialized by DOMDocument. Only trailing newlines
 * are normalised; everything else is compared byte for byte. Regenerate with
 * UPDATE_FIXTURES=1 vendor/bin/pest --filter=HtmlFixtures and read the diff
 * before committing it.
 */
it('renders html fixtures', function (string $name): void {
    $dir = dirname(__DIR__, 2).'/Fixtures/render/html';
    $pipeline = new HtmlPipeline(SanitizerFactory::make());
    $result = $pipeline->render((string) file_get_contents("$dir/$name.input.html"), 'en', 'users/permissions');

    if (getenv('UPDATE_FIXTURES') === '1') {
        file_put_contents("$dir/$name.html", rtrim($result->html, "\n")."\n");

        if (is_file("$dir/$name.toc.json")) {
            file_put_contents("$dir/$name.toc.json", json_encode($result->toc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }
    }

    expect(rtrim($result->html, "\n"))->toBe(rtrim((string) file_get_contents("$dir/$name.html"), "\n"));

    if (is_file("$dir/$name.toc.json")) {
        expect($result->toc)->toBe(json_decode((string) file_get_contents("$dir/$name.toc.json"), true));
    }
})->with(function (): array {
    $names = array_map(
        fn (string $file): string => basename($file, '.input.html'),
        glob(dirname(__DIR__, 2).'/Fixtures/render/html/*.input.html') ?: [],
    );

    return array_combine($names, array_map(fn (string $name): array => [$name], $names));
});
