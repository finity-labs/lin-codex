<?php

use FinityLabs\LinCodex\LinCodexServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProvider(LinCodexServiceProvider::class))->not->toBeNull();
});

it('prefixes every table name with codex_', function () {
    $tables = config('lin-codex.table_names');

    expect($tables)->toBeArray()->not->toBeEmpty();

    foreach ($tables as $table) {
        expect($table)->toStartWith('codex_');
    }
});
