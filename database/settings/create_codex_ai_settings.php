<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Settings\CodexAiSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (CodexAiSettings::defaults() as $key => $value) {
            if ($this->migrator->exists("lin-codex-ai.{$key}")) {
                continue;
            }

            if (in_array($key, CodexAiSettings::ENCRYPTED, true)) {
                $this->migrator->addEncrypted("lin-codex-ai.{$key}", $value);
            } else {
                $this->migrator->add("lin-codex-ai.{$key}", $value);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(CodexAiSettings::defaults()) as $key) {
            $this->migrator->deleteIfExists("lin-codex-ai.{$key}");
        }
    }
};
