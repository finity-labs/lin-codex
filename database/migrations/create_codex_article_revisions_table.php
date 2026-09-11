<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $articles = config('lin-codex.table_names.articles') ?? 'codex_articles';
        $revisions = config('lin-codex.table_names.article_revisions') ?? 'codex_article_revisions';
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');
        $users = config('lin-codex.users_table') ?? (new $userModel)->getTable();

        $schema->create($revisions, function (Blueprint $table) use ($articles, $userModel, $users): void {
            $table->id();
            $table->foreignId('article_id')->constrained($articles)->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->longText('body');
            $table->unsignedTinyInteger('format');
            $table->unsignedTinyInteger('reason');
            $table->foreignIdFor($userModel, 'user_id')->nullable()->constrained($users)->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists(config('lin-codex.table_names.article_revisions') ?? 'codex_article_revisions');
    }
};
