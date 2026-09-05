<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (CodexSettings::defaults() as $key => $value) {
            if (! $this->migrator->exists("lin-codex.{$key}")) {
                $this->migrator->add("lin-codex.{$key}", $value);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(CodexSettings::defaults()) as $key) {
            $this->migrator->deleteIfExists("lin-codex.{$key}");
        }
    }
};
