<?php

namespace Ralkage\CivilityFilter\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;

class CivilityFlaggedBlueprint implements BlueprintInterface
{
    public $post;
    protected $action;
    protected $reason;
    protected $score;

    public function __construct(Post $post, string $action, string $reason = '', int $score = 0)
    {
        $this->post = $post;
        $this->action = $action;
        $this->reason = $reason;
        $this->score = $score;
    }

    public function getSubject()
    {
        return $this->post;
    }

    public function getFromUser()
    {
        return null;
    }

    public function getData()
    {
        return [
            'action' => $this->action,
            'reason' => $this->reason,
            'score' => $this->score,
        ];
    }

    public static function getType()
    {
        return 'civilityFlagged';
    }

    public static function getSubjectModel()
    {
        return Post::class;
    }
}
