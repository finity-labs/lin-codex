<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Commands\Concerns;

use Illuminate\Console\Command;

/**
 * The --user option codex:import and codex:revisions:restore share.
 *
 * A console run has no authenticated user, so both commands take the author
 * as an option. The host's key type decides what a valid value looks like: a
 * whole number on the default auto-increment user model, a UUID or ULID
 * string on a host whose user model uses HasUuids or HasUlids. A digit string
 * comes back as an int so an integer column stores what it always did;
 * anything else that is not empty comes back as the string it was typed as,
 * and the foreign key rejects an id that belongs to nobody.
 *
 * @mixin Command
 */
trait ResolvesUserOption
{
    protected function userOption(): int|string|null
    {
        $user = $this->option('user');

        if (! is_string($user) || $user === '') {
            return null;
        }

        return ctype_digit($user) ? (int) $user : $user;
    }
}
