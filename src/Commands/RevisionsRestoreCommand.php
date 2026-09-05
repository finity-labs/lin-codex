<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands;

use FinityLabs\LinCodex\Models\ArticleRevision;
use FinityLabs\LinCodex\Revisions\RevisionManager;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Put a revision's title, body and format back on its article by revision
 * id. The current content is snapshotted first, so the restore can itself
 * be undone; --user records that snapshot's author, otherwise it has none
 * because a console run has no authenticated user.
 */
final class RevisionsRestoreCommand extends Command
{
    protected $signature = 'codex:revisions:restore
        {revision : The revision id}
        {--user= : Record this user id as the author of the snapshot taken before restoring}';

    protected $description = 'Restore an article translation from one of its revisions.';

    public function handle(RevisionManager $revisions): int
    {
        $id = (int) $this->argument('revision');
        $revision = ArticleRevision::query()->find($id);

        if ($revision === null) {
            $this->error(sprintf('Revision %d not found.', $id));

            return self::FAILURE;
        }

        $user = $this->option('user');
        $userId = $user === null ? null : (int) $user;

        try {
            $translation = $revisions->restore($revision, $userId);
        } catch (QueryException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Restored revision %d (%s) of %s.', $id, $translation->locale, $revision->article->slug));

        return self::SUCCESS;
    }
}
