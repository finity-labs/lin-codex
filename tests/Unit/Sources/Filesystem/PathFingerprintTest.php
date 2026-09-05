<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Sources\Filesystem\PathFingerprint;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tmp = sys_get_temp_dir().'/lin-codex-fp-'.uniqid();
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

it('reports a missing folder and an empty folder distinctly', function (): void {
    expect(PathFingerprint::of($this->tmp))->toBe('missing');

    mkdir($this->tmp, 0777, true);

    expect(PathFingerprint::of($this->tmp))->toBe('0:0');
});

it('counts article files recursively and takes the newest mtime', function (): void {
    mkdir($this->tmp.'/en/sub', 0777, true);
    file_put_contents($this->tmp.'/en/a.md', 'a');
    file_put_contents($this->tmp.'/en/sub/b.html', 'b');
    clearstatcache(true);

    $newest = max((int) filemtime($this->tmp.'/en/a.md'), (int) filemtime($this->tmp.'/en/sub/b.html'));

    expect(PathFingerprint::of($this->tmp))->toBe('2:'.$newest);
});

it('changes when a file mtime moves forward', function (): void {
    mkdir($this->tmp.'/en', 0777, true);
    file_put_contents($this->tmp.'/en/a.md', 'a');
    clearstatcache(true);

    $before = PathFingerprint::of($this->tmp);
    $bumped = (int) filemtime($this->tmp.'/en/a.md') + 5;

    touch($this->tmp.'/en/a.md', $bumped);
    clearstatcache(true);

    $after = PathFingerprint::of($this->tmp);

    expect($after)->not->toBe($before)
        ->and(explode(':', $after)[1])->toBe((string) $bumped);
});

it('ignores dot files, non-article files and vcs folders in the count', function (): void {
    mkdir($this->tmp.'/en/.git', 0777, true);
    file_put_contents($this->tmp.'/en/a.md', 'a');
    clearstatcache(true);

    $count = explode(':', PathFingerprint::of($this->tmp))[0];

    file_put_contents($this->tmp.'/en/.hidden.md', 'h');
    file_put_contents($this->tmp.'/en/notes.txt', 'n');
    file_put_contents($this->tmp.'/en/.git/x.md', 'x');
    clearstatcache(true);

    expect(explode(':', PathFingerprint::of($this->tmp))[0])->toBe($count)->toBe('1');
});

it('is always count colon mtime', function (): void {
    mkdir($this->tmp.'/en', 0777, true);
    file_put_contents($this->tmp.'/en/a.md', 'a');
    clearstatcache(true);

    expect(preg_match('/^\d+:\d+$/', PathFingerprint::of($this->tmp)))->toBe(1);
});
