<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Models\Article;

it('links a child to its parent when the parent exists first', function (): void {
    $users = Article::factory()->create(['slug' => 'users']);
    $roles = Article::factory()->create(['slug' => 'users/roles']);

    expect($roles->fresh()?->parent_id)->toBe($users->id);
});

it('relinks an orphaned child when its parent is created later', function (): void {
    $roles = Article::factory()->create(['slug' => 'users/roles']);

    expect($roles->fresh()?->parent_id)->toBeNull();

    $users = Article::factory()->create(['slug' => 'users']);

    expect($roles->fresh()?->parent_id)->toBe($users->id);
});

it('links each level to its direct parent only', function (): void {
    $a = Article::factory()->create(['slug' => 'a']);
    $ab = Article::factory()->create(['slug' => 'a/b']);
    $abc = Article::factory()->create(['slug' => 'a/b/c']);

    expect($abc->fresh()?->parent_id)->toBe($ab->id)
        ->and($abc->fresh()?->parent_id)->not->toBe($a->id)
        ->and($ab->fresh()?->parent_id)->toBe($a->id)
        ->and($a->children()->pluck('slug')->all())->toBe(['a/b'])
        ->and($ab->children()->pluck('slug')->all())->toBe(['a/b/c']);
});

it('orphans old children and adopts matching ones when a parent slug is renamed', function (): void {
    $teams = Article::factory()->create(['slug' => 'people/teams']);

    expect($teams->fresh()?->parent_id)->toBeNull();

    $users = Article::factory()->create(['slug' => 'users']);
    $roles = Article::factory()->create(['slug' => 'users/roles']);

    expect($roles->fresh()?->parent_id)->toBe($users->id);

    $users->update(['slug' => 'people']);

    expect($roles->fresh()?->parent_id)->toBeNull()
        ->and($teams->fresh()?->parent_id)->toBe($users->id);
});

it('keeps a root slug parentless even when parent_id is set explicitly', function (): void {
    $users = Article::factory()->create(['slug' => 'users']);

    $root = new Article(['slug' => 'billing']);
    $root->parent_id = $users->id;
    $root->save();

    expect($root->fresh()?->parent_id)->toBeNull();

    $another = Article::factory()->create(['slug' => 'reports', 'parent_id' => $users->id]);

    expect($another->fresh()?->parent_id)->toBeNull();
});

it('exposes the parent and children relations from the cached parent_id', function (): void {
    $users = Article::factory()->create(['slug' => 'users']);
    $roles = Article::factory()->childOf($users, 'roles')->create();
    $teams = Article::factory()->childOf($users, 'teams')->create();

    expect($roles->parent?->is($users))->toBeTrue()
        ->and($users->children->pluck('id')->sort()->values()->all())->toBe([$roles->id, $teams->id]);
});
