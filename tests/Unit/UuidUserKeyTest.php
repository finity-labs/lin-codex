<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests\Unit;

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\RevisionReason;
use FinityLabs\LinCodex\Events\ArticleTranslated;
use FinityLabs\LinCodex\Models\Article;
use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Models\ArticleTranslation;
use FinityLabs\LinCodex\Models\Media;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use FinityLabs\LinCodex\Settings\CodexSettings;
use FinityLabs\LinCodex\Sync\ArticleImporter;
use FinityLabs\LinCodex\Sync\ImportOptions;
use FinityLabs\LinCodex\Tests\Fixtures\UuidUser;
use FinityLabs\LinCodex\Tests\UuidUserTestCase;
use FinityLabs\LinCodex\Translation\TranslationReport;
use Illuminate\Support\Facades\Schema;

/**
 * The package on a host whose user model uses HasUuids: every column that
 * holds a user id is a string, and every path that writes one stores the
 * UUID instead of dropping it.
 */
class UuidUserKeyTest extends UuidUserTestCase
{
    protected UuidUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UuidUser::create(['name' => 'Jane Uuid', 'email' => 'jane-'.uniqid().'@example.com']);
    }

    public function test_the_migrations_create_string_user_columns_for_a_uuid_user_model(): void
    {
        // The name a driver gives its uuid column, never an integer type:
        // Postgres has one of its own, MySQL and MariaDB store char(36) and
        // SQLite reports the varchar it was declared as.
        $expected = match ($this->databaseDriver()) {
            'pgsql' => 'uuid',
            'mysql', 'mariadb' => 'char',
            default => 'varchar',
        };

        $this->assertSame($expected, Schema::getColumnType('codex_articles', 'created_by'));
        $this->assertSame($expected, Schema::getColumnType('codex_articles', 'updated_by'));
        $this->assertSame($expected, Schema::getColumnType('codex_article_revisions', 'user_id'));
        $this->assertSame($expected, Schema::getColumnType('codex_media', 'uploaded_by'));
    }

    public function test_an_article_stores_the_uuid_author_and_reads_it_back_through_the_relationships(): void
    {
        $article = Article::factory()->create([
            'slug' => 'uuid/author',
            'created_by' => $this->user->getKey(),
            'updated_by' => $this->user->getKey(),
        ]);

        $article->refresh();

        $this->assertSame($this->user->getKey(), $article->created_by);
        $this->assertSame($this->user->getKey(), $article->updated_by);
        $this->assertTrue($article->creator?->is($this->user));
        $this->assertTrue($article->updater?->is($this->user));
    }

    public function test_media_stores_the_uuid_uploader(): void
    {
        $media = Media::factory()->create(['uploaded_by' => $this->user->getKey()]);

        $media->refresh();

        $this->assertSame($this->user->getKey(), $media->uploaded_by);
        $this->assertTrue($media->uploader?->is($this->user));
    }

    public function test_an_attributed_revision_carries_the_uuid_author(): void
    {
        $this->enableRevisions();
        $translation = $this->translation();

        $this->revisions()->attributing(RevisionReason::Import, $this->user->getKey(), function () use ($translation): void {
            $translation->body = 'Rewritten';
            $translation->save();
        });

        $revision = ArticleRevision::query()->latest('id')->firstOrFail();

        $this->assertSame($this->user->getKey(), $revision->user_id);
        $this->assertSame(RevisionReason::Import, $revision->reason);
        $this->assertTrue($revision->user?->is($this->user));
    }

    public function test_a_snapshot_and_a_restore_carry_the_uuid_author(): void
    {
        $this->enableRevisions();
        $translation = $this->translation();

        $snapshot = $this->revisions()->snapshot($translation, RevisionReason::Manual, $this->user->getKey());

        $this->assertSame($this->user->getKey(), $snapshot->fresh()?->user_id);

        $translation->fill(['title' => 'Second', 'body' => 'Second body'])->save();
        $this->revisions()->restore($snapshot, $this->user->getKey());

        $this->assertSame($this->user->getKey(), ArticleRevision::query()->latest('id')->firstOrFail()->user_id);
    }

    public function test_the_authenticated_uuid_viewer_is_the_default_revision_author(): void
    {
        $this->enableRevisions();
        $translation = $this->translation();

        $this->actingAs($this->user);

        $translation->body = 'Signed';
        $translation->save();

        $this->assertSame($this->user->getKey(), ArticleRevision::query()->latest('id')->firstOrFail()->user_id);
    }

    public function test_the_importer_stamps_the_uuid_author_on_created_and_updated_articles(): void
    {
        config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-roundtrip')]);
        config()->set('lin-codex.source', 'composite');
        $this->forgetSources();

        app(ArticleImporter::class)->import(new ImportOptions(
            only: ['users/roles'],
            locale: 'de',
            userId: $this->user->getKey(),
        ));

        $article = Article::query()->where('slug', 'users/roles')->firstOrFail();

        $this->assertSame($this->user->getKey(), $article->created_by);
        $this->assertSame($this->user->getKey(), $article->updated_by);
    }

    public function test_the_import_command_accepts_a_uuid_user_option(): void
    {
        config()->set('lin-codex.sources.filesystem.paths', [$this->fixtureDocsPath('docs-roundtrip')]);
        config()->set('lin-codex.source', 'composite');
        $this->forgetSources();

        $this->artisan('codex:import', ['--only' => ['users/roles'], '--locale' => 'de', '--user' => $this->user->getKey()])
            ->assertExitCode(0);

        $this->assertSame(
            $this->user->getKey(),
            Article::query()->where('slug', 'users/roles')->firstOrFail()->created_by,
        );
    }

    public function test_the_revisions_restore_command_accepts_a_uuid_user_option(): void
    {
        $this->enableRevisions();
        $translation = $this->translation();
        $article = $translation->article;

        $revision = ArticleRevision::factory()->manual()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Old',
            'body' => 'Old body',
            'format' => ArticleFormat::Markdown,
        ]);

        $this->artisan('codex:revisions:restore', ['revision' => (string) $revision->id, '--user' => $this->user->getKey()])
            ->assertExitCode(0);

        $newest = $article->revisions()->orderByDesc('id')->firstOrFail();

        $this->assertSame($this->user->getKey(), $newest->user_id);
    }

    public function test_the_translated_event_carries_the_uuid_of_the_admin_who_queued_the_run(): void
    {
        $event = new ArticleTranslated(1, $this->user->getKey(), new TranslationReport);

        $this->assertSame($this->user->getKey(), $event->userId);
    }

    private function revisions(): RevisionManager
    {
        return app(RevisionManager::class);
    }

    private function enableRevisions(): void
    {
        $settings = app(CodexSettings::class);
        $settings->revisions_enabled = true;
        $settings->save();
    }

    private function translation(): ArticleTranslation
    {
        $article = Article::factory()->markdown()->create(['slug' => 'uuid/revisioned']);

        return ArticleTranslation::query()->create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'First',
            'body' => 'First body',
        ]);
    }
}
