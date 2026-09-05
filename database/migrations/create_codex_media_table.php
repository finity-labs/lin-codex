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
        $media = config('lin-codex.table_names.media') ?? 'codex_media';
        $users = config('lin-codex.users_table') ?? 'users';

        $schema->create($media, function (Blueprint $table) use ($articles, $users): void {
            $table->id();
            $table->string('disk', 64);
            $table->string('path');
            $table->string('name');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained($users)->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained($articles)->nullOnDelete();
            $table->timestamps();

            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())
            ->dropIfExists(config('lin-codex.table_names.media') ?? 'codex_media');
    }
};
