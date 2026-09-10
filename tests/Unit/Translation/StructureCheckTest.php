<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Translation\StructureCheck;

it('passes when fences and targets match in another order', function (): void {
    $source = <<<'MD'
        See [a](roles.md) and ![i](img/a.png).

        ```php
        echo 1;
        ```

        ```bash
        ls -la
        ```
        MD;

    $output = <<<'MD'
        Siehe ![i](img/a.png) und [a](roles.md).

        ```bash
        ls -la
        ```

        ```php
        echo 1;
        ```
        MD;

    expect((new StructureCheck)->passes($source, $output))->toBeTrue();
});

it('fails when a fence is dropped', function (): void {
    $source = <<<'MD'
        ```php
        echo 1;
        ```

        ```bash
        ls -la
        ```
        MD;

    $output = <<<'MD'
        ```php
        echo 1;
        ```
        MD;

    expect((new StructureCheck)->passes($source, $output))->toBeFalse();
});

it('fails when a fence body changed', function (): void {
    $source = "```php\necho 1;\n```";
    $output = "```php\necho 'eins';\n```";

    expect((new StructureCheck)->passes($source, $output))->toBeFalse();
});

it('fails when a link target changed', function (): void {
    expect((new StructureCheck)->passes('See [Roles](roles.md).', 'Siehe [Rollen](rollen.md).'))->toBeFalse();
});

it('fails when an image target changed', function (): void {
    expect((new StructureCheck)->passes('![Screen](img/a.png)', '![Bildschirm](img/b.png)'))->toBeFalse();
});

it('fails when a link was dropped', function (): void {
    expect((new StructureCheck)->passes('[a](one.md) and [b](two.md)', '[a](one.md)'))->toBeFalse();
});

it('ignores link text, alt text and titles', function (): void {
    $source = 'See [Roles](roles.md "Roles") and ![Screen](a.png).';
    $output = 'Siehe [Rollen](roles.md "Rollen") und ![Bildschirm](a.png).';

    expect((new StructureCheck)->passes($source, $output))->toBeTrue()
        ->and((new StructureCheck)->linkTargets($source))->toBe(['roles.md', 'a.png']);
});

it('reads tilde fences and info strings', function (): void {
    $check = new StructureCheck;

    expect($check->codeBlocks("~~~sh\nls\n~~~"))->toBe(['ls'])
        ->and($check->codeBlocks("```\nplain\n```"))->toBe(['plain'])
        ->and($check->codeBlocks("```php\necho 1;\n```\n\n```bash\nls\n```"))->toBe(['echo 1;', 'ls'])
        ->and($check->codeBlocks('Use `inline` code here.'))->toBe([]);
});

it('passes on empty input', function (): void {
    expect((new StructureCheck)->passes('', ''))->toBeTrue()
        ->and((new StructureCheck)->codeBlocks(''))->toBe([])
        ->and((new StructureCheck)->linkTargets(''))->toBe([]);
});

it('treats a fence-free text against a fence-wrapped one as different', function (): void {
    expect((new StructureCheck)->passes('plain', "```md\nplain\n```"))->toBeFalse();
});
