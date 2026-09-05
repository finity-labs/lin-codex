<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $articles = config('lin-codex.table_names.articles') ?? 'codex_articles';
        $contexts = config('lin-codex.table_names.article_contexts') ?? 'codex_article_contexts';

        $schema->create($contexts, function (Blueprint $table) use ($articles): void {
            $table->id();
            $table->foreignId('article_id')->constrained($articles)->cascadeOnDelete();
            $table->string('panel_id', 64)->nullable();
            $table->unsignedTinyInteger('type');
            $table->string('key', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // No unique index on (article_id, panel_id, type, key): panel_id is nullable and
            // NULLs never collide in a unique index, so uniqueness is enforced in code instead.
            $table->index(['type', 'key']);
            $table->index(['panel_id', 'type', 'key']);
            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists(config('lin-codex.table_names.article_contexts') ?? 'codex_article_contexts');
    }
};
