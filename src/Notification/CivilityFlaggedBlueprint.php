<?php

namespace Ralkage\CivilityFilter\Notification;

use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;

class CivilityFlaggedBlueprint implements BlueprintInterface
{
    public $discussion;
    protected $action;
    protected $reason;
    protected $score;
    protected $postNumber;

    public function __construct(Post $post, string $action, string $reason = '', int $score = 0)
    {
        $this->discussion = $post->discussion;
        $this->action = $action;
        $this->reason = $reason;
        $this->score = $score;
        $this->postNumber = $post->number;
    }

    public function getSubject()
    {
        return $this->discussion;
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
            'postNumber' => $this->postNumber,
        ];
    }

    public static function getType()
    {
        return 'civilityFlagged';
    }

    public static function getSubjectModel()
    {
        return Discussion::class;
    }
}
