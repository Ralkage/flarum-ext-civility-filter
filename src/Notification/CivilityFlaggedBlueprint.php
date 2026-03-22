<?php

namespace Ralkage\CivilityFilter\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Flarum\User\User;

class CivilityFlaggedBlueprint implements BlueprintInterface, AlertableInterface
{
    public Discussion $discussion;
    protected int $postNumber;

    public function __construct(
        Post $post,
        public string $action,
        public string $reason = '',
        public int $score = 0
    ) {
        $this->discussion = $post->discussion;
        $this->postNumber = $post->number;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->discussion;
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getData(): mixed
    {
        return [
            'action' => $this->action,
            'reason' => $this->reason,
            'score' => $this->score,
            'postNumber' => $this->postNumber,
        ];
    }

    public static function getType(): string
    {
        return 'civilityFlagged';
    }

    public static function getSubjectModel(): string
    {
        return Discussion::class;
    }
}
