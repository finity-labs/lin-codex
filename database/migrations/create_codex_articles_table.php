<?php

declare(strict_types=1);

use FinityLabs\LinCodex\Enums\ArticleFormat;
use FinityLabs\LinCodex\Enums\Visibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $articles = config('lin-codex.table_names.articles') ?? 'codex_articles';
        $users = config('lin-codex.users_table') ?? 'users';

        $schema->create($articles, function (Blueprint $table) use ($articles, $users): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->foreignId('parent_id')->nullable()->constrained($articles)->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon')->nullable();
            $table->unsignedTinyInteger('format')->default(ArticleFormat::Markdown->value);
            $table->unsignedTinyInteger('visibility')->default(Visibility::Authenticated->value);
            $table->boolean('is_published')->default(true);
            $table->string('source_path')->nullable();
            $table->json('keywords')->nullable();
            $table->json('related')->nullable();
            // Text, not JSON: MySQL's JSON type reorders object keys (shortest first),
            // which breaks the lossless import/export round trip of author metadata.
            $table->longText('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained($users)->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained($users)->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists(config('lin-codex.table_names.articles') ?? 'codex_articles');
    }
};
