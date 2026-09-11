<?php

declare(strict_types=1);

namespace FinityLabs\LinCodex\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User;

/**
 * A user model keyed by a string UUID, as apps using HasUuids ship it.
 */
class UuidUser extends User
{
    use HasUuids;

    protected $table = 'users';

    protected $guarded = [];
}
