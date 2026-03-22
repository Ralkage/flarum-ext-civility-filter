<?php

namespace Ralkage\CivilityFilter\Job;

use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Ralkage\CivilityFilter\CivilityChecker;
use Ralkage\CivilityFilter\Notification\CivilityFlaggedBlueprint;
use Ralkage\CivilityFilter\WebhookNotifier;

class CheckCivilityJob extends AbstractJob
{
    private $postId;
    private $userId;
    private $content;
    private $discussionTitle;

    public function __construct(int $postId, int $userId, string $content, string $discussionTitle = '')
    {
        parent::__construct();
        $this->postId = $postId;
        $this->userId = $userId;
        $this->content = $content;
        $this->discussionTitle = $discussionTitle;
    }

    public function handle(
        CivilityChecker $checker,
        NotificationSyncer $notifications,
        WebhookNotifier $webhook,
        SettingsRepositoryInterface $settings
    ): void {
        $post = CommentPost::find($this->postId);
        $user = User::find($this->userId);

        if (! $post || ! $user) {
            return;
        }

        $context = [];
        if ($this->discussionTitle) {
            $context['discussion_title'] = $this->discussionTitle;
        }

        $result = $checker->analyze($this->content, $context);

        switch ($result['action']) {
            case 'blocked':
                // Hide the post retroactively
                $post->hide();
                $post->civility_action = 'blocked';
                $post->save();
                break;

            case 'moderated':
                $post->is_approved = false;
                $post->civility_action = 'moderated';
                $post->save();
                break;

            case 'warned':
                $post->civility_action = 'warned';
                $post->save();
                break;
        }

        // Log the result
        $contentInfo = [
            'content_type' => 'post',
            'content_id' => $post->id,
            'discussion_id' => $post->discussion_id,
            'user_id' => $user->id,
            'username' => $user->username,
            'message' => $this->content,
        ];

        $checker->logResult($result, $contentInfo);

        // Send notification for non-allowed actions
        if ($result['action'] !== 'allowed') {
            $notifications->sync(
                new CivilityFlaggedBlueprint($post, $result['action'], $result['reason'] ?? '', $result['score'] ?? 0),
                [$user]
            );

            $webhook->notify($result, $contentInfo);

            // Auto-suspend check
            $this->checkAutoSuspend($user, $checker, $settings);
        }
    }

    protected function checkAutoSuspend(User $user, CivilityChecker $checker, SettingsRepositoryInterface $settings): void
    {
        $threshold = (int) $settings->get('ralkage-civility-filter.auto_suspend_threshold');
        $days = (int) ($settings->get('ralkage-civility-filter.auto_suspend_days') ?: 3);
        $window = (int) ($settings->get('ralkage-civility-filter.auto_suspend_window') ?: 7);

        if ($threshold <= 0) {
            return;
        }

        $violationCount = $checker->getRecentViolationCount($user->id, $window);

        if ($violationCount >= $threshold) {
            if (isset($user->suspended_until)) {
                if ($user->suspended_until && strtotime($user->suspended_until) > time()) {
                    return;
                }

                $user->suspended_until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $user->save();
            }
        }
    }
}
