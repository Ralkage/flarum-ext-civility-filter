<?php

use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Post\Post;
use Ralkage\CivilityFilter\Api\Controller\CivilityStatsController;
use Ralkage\CivilityFilter\Api\Controller\ClearCivilityLogsController;
use Ralkage\CivilityFilter\Api\Controller\ExportCivilityLogsController;
use Ralkage\CivilityFilter\Api\Controller\ListCivilityLogsController;
use Ralkage\CivilityFilter\Api\Controller\ModeratePostController;
use Ralkage\CivilityFilter\Api\Controller\TestCivilityController;
use Ralkage\CivilityFilter\Api\Controller\UserCivilityController;
use Ralkage\CivilityFilter\Listener\CheckPostCivility;
use Ralkage\CivilityFilter\Notification\CivilityFlaggedBlueprint;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ralkage-civility-filter', __DIR__.'/views'),

    (new Extend\Event())
        ->subscribe(CheckPostCivility::class),

    (new Extend\Routes('api'))
        ->get('/civility-logs', 'civility-logs.index', ListCivilityLogsController::class)
        ->post('/civility-logs/test', 'civility-logs.test', TestCivilityController::class)
        ->delete('/civility-logs', 'civility-logs.clear', ClearCivilityLogsController::class)
        ->get('/civility-logs/export', 'civility-logs.export', ExportCivilityLogsController::class)
        ->get('/civility-logs/stats', 'civility-logs.stats', CivilityStatsController::class)
        ->post('/civility-logs/moderate', 'civility-logs.moderate', ModeratePostController::class)
        ->get('/civility-logs/user', 'civility-logs.user', UserCivilityController::class),

    (new Extend\Model(Post::class))
        ->cast('civility_action', 'string'),

    (new Extend\ApiResource(Resource\PostResource::class))
        ->fields(fn () => [
            Schema\Str::make('civilityAction')
                ->get(function (Post $post, Context $context) {
                    $actor = $context->getActor();
                    $isAuthor = $post->user_id === $actor->id;

                    if ($isAuthor || $actor->isAdmin()) {
                        return $post->civility_action ?: '';
                    }

                    return '';
                }),
        ]),

    (new Extend\Notification())
        ->type(CivilityFlaggedBlueprint::class, ['alert', 'email']),
];
