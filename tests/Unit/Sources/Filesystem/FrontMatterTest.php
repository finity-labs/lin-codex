<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Sources\Filesystem\FrontMatter;

it('splits a fenced yaml block from the body', function (string $contents, ?string $yaml, string $body): void {
    expect(FrontMatter::split($contents))->toBe(['yaml' => $yaml, 'body' => $body]);
})->with([
    'lf' => ["---\ntitle: a\n---\nbody", 'title: a', 'body'],
    'crlf' => ["---\r\ntitle: a\r\n---\r\nbody", 'title: a', 'body'],
    'bom' => ["\xEF\xBB\xBF---\ntitle: a\n---\nbody", 'title: a', 'body'],
    'no trailing newline' => ["---\ntitle: a\n---", 'title: a', ''],
    'empty block' => ["---\n---\nbody", '', 'body'],
    'no front matter' => ['no front matter', null, 'no front matter'],
    'bom without front matter' => ["\xEF\xBB\xBFplain", null, 'plain'],
    'fence with trailing text' => ["--- not fm\nx", null, "--- not fm\nx"],
]);

it('reads front matter as a mapping and keeps yaml 1.2 strings', function (): void {
    expect(FrontMatter::read("---\ntitle: Hello\norder: 3\npublished: no\n---\nbody"))->toBe([
        'data' => ['title' => 'Hello', 'order' => 3, 'published' => 'no'],
        'body' => 'body',
        'error' => null,
    ]);
});

it('reports a yaml parse error instead of throwing', function (): void {
    $result = FrontMatter::read("---\ntitle: Users: overview\n---\nx");

    expect($result['data'])->toBe([])
        ->and($result['body'])->toBe('x')
        ->and($result['error'])->toBeString()->toContain('colon');
});

it('rejects front matter that is not a mapping', function (): void {
    $result = FrontMatter::read("---\njust a scalar\n---\nx");

    expect($result['data'])->toBe([])
        ->and($result['error'])->toBe('Front matter must be a YAML mapping.');
});

it('treats an empty block and no block alike', function (): void {
    expect(FrontMatter::read("---\n---\nx"))->toBe(['data' => [], 'body' => 'x', 'error' => null])
        ->and(FrontMatter::read('x'))->toBe(['data' => [], 'body' => 'x', 'error' => null]);
});

it('never instantiates php objects from yaml tags', function (): void {
    expect(FrontMatter::read("---\nx: !php/object 'O:8:\"stdClass\":0:{}'\n---\nb")['data'])->toBe(['x' => null]);
});

it('keeps mixed context lists as written', function (): void {
    expect(FrontMatter::read("---\ncontexts:\n  - route:users.index\n  - url: /users/*\n---\n")['data']['contexts'])
        ->toBe(['route:users.index', ['url' => '/users/*']]);
});
