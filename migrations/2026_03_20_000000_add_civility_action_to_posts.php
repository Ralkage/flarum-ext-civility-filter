<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('posts', 'civility_action')) {
            $schema->table('posts', function (Blueprint $table) {
                $table->string('civility_action', 25)->default('')->after('is_approved');
            });
        }
    },
    'down' => function (Builder $schema) {
        if ($schema->hasColumn('posts', 'civility_action')) {
            $schema->table('posts', function (Blueprint $table) {
                $table->dropColumn('civility_action');
            });
        }
    },
];
