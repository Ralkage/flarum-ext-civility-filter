<?php

namespace Ralkage\CivilityFilter\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Flarum\User\User;

class CivilityFlaggedBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(
        public Post $post,
        public string $action,
        public string $reason = '',
        public int $score = 0
    ) {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->post;
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
        ];
    }

    public static function getType(): string
    {
        return 'civilityFlagged';
    }

    public static function getSubjectModel(): string
    {
        return Post::class;
    }
}
