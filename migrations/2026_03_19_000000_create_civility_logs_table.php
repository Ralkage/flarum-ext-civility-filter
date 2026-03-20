<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('civility_logs')) {
            $schema->create('civility_logs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('content_type', 25)->default('post');
                $table->unsignedInteger('content_id')->default(0);
                $table->unsignedInteger('discussion_id')->default(0);
                $table->unsignedInteger('user_id')->default(0);
                $table->string('username', 100)->default('');
                $table->text('message_excerpt')->nullable();
                $table->unsignedTinyInteger('civility_score')->default(0);
                $table->string('categories', 500)->default('');
                $table->string('action_taken', 25)->default('allowed');
                $table->text('ai_response')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('user_id');
                $table->index(['content_type', 'content_id']);
                $table->index('action_taken');
                $table->index('created_at');
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('civility_logs');
    },
];
