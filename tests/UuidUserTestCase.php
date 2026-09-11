<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests;

use FinityLabs\LinCodex\Tests\Fixtures\UuidUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boots the package against a users table keyed by a UUID string.
 *
 * The user model is set in defineEnvironment(), which runs before the
 * migrations: they size created_by, updated_by, user_id and uploaded_by from
 * it, so the columns come out as strings and the foreign keys hold.
 */
abstract class UuidUserTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', UuidUser::class);
    }

    protected function createUsersTable(string $table): void
    {
        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
}
