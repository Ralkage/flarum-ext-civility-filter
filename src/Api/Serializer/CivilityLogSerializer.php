<?php

namespace Ralkage\CivilityFilter\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;

class CivilityLogSerializer extends AbstractSerializer
{
    protected $type = 'civility-logs';

    protected function getDefaultAttributes($log)
    {
        return [
            'id' => $log->id,
            'contentType' => $log->content_type,
            'contentId' => $log->content_id,
            'discussionId' => $log->discussion_id,
            'userId' => $log->user_id,
            'username' => $log->username,
            'messageExcerpt' => $log->message_excerpt,
            'civilityScore' => (int) $log->civility_score,
            'categories' => json_decode($log->categories, true) ?: [],
            'actionTaken' => $log->action_taken,
            'createdAt' => $log->created_at,
        ];
    }
}
