<?php

namespace Ralkage\CivilityFilter\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Symfony\Contracts\Translation\TranslatorInterface;

class CivilityFlaggedBlueprint implements BlueprintInterface, MailableInterface
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

    public function getEmailView()
    {
        return ['text' => 'ralkage-civility-filter::emails.civilityFlagged'];
    }

    public function getEmailSubject(TranslatorInterface $translator)
    {
        if ($this->action === 'moderated') {
            return $translator->trans('ralkage-civility-filter.email.moderated_subject');
        }

        return $translator->trans('ralkage-civility-filter.email.warned_subject');
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
