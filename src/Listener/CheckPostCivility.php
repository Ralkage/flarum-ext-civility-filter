<?php

namespace Ralkage\CivilityFilter\Listener;

use Flarum\Post\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\Notification\NotificationSyncer;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Events\Dispatcher;
use Ralkage\CivilityFilter\CivilityChecker;
use Ralkage\CivilityFilter\Notification\CivilityFlaggedBlueprint;
use Ralkage\CivilityFilter\WebhookNotifier;

class CheckPostCivility
{
    protected $checker;
    protected $translator;
    protected $settings;
    protected $notifications;
    protected $webhook;

    public function __construct(
        CivilityChecker $checker,
        Translator $translator,
        SettingsRepositoryInterface $settings,
        NotificationSyncer $notifications,
        WebhookNotifier $webhook
    ) {
        $this->checker = $checker;
        $this->translator = $translator;
        $this->settings = $settings;
        $this->notifications = $notifications;
        $this->webhook = $webhook;
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Saving::class, [$this, 'handle']);
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;
        $actor = $event->actor;

        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        if (! $this->checker->isEnabled()) {
            return;
        }

        if ($actor->isAdmin() || $actor->hasPermission('ralkage-civility-filter.bypass')) {
            return;
        }

        $content = $post->content;
        if (empty($content)) {
            return;
        }

        if (! $this->isMonitoredDiscussion($post)) {
            return;
        }

        $context = [];
        if ($post->discussion) {
            $context['discussion_title'] = $post->discussion->title;
        }

        $result = $this->checker->analyze($content, $context);

        // Apply the action
        switch ($result['action']) {
            case 'blocked':
                // Log blocked posts immediately (no afterSave since post won't save)
                $this->checker->logResult($result, [
                    'content_type' => 'post',
                    'content_id' => 0,
                    'discussion_id' => $post->discussion_id ?: 0,
                    'user_id' => $actor->id,
                    'username' => $actor->username,
                    'message' => $content,
                ]);

                $this->webhook->notify($result, [
                    'user_id' => $actor->id,
                    'username' => $actor->username,
                    'discussion_id' => $post->discussion_id ?: 0,
                    'content_id' => 0,
                    'message' => $content,
                ]);

                // Auto-suspend check even on blocked posts
                $this->checkAutoSuspend($actor);

                throw new ValidationException([
                    'content' => $this->translator->trans('ralkage-civility-filter.api.post_blocked'),
                ]);

            case 'moderated':
                $post->is_approved = false;
                $post->civility_action = 'moderated';
                break;

            case 'warned':
                $post->civility_action = 'warned';
                break;
        }

        // afterSave for non-blocked actions
        if ($result['action'] !== 'allowed' && $result['action'] !== 'blocked') {
            $checker = $this->checker;
            $notifications = $this->notifications;
            $webhook = $this->webhook;
            $settings = $this->settings;

            $post->afterSave(function ($post) use ($result, $actor, $content, $checker, $notifications, $webhook, $settings) {
                $contentInfo = [
                    'content_type' => 'post',
                    'content_id' => $post->id,
                    'discussion_id' => $post->discussion_id,
                    'user_id' => $actor->id,
                    'username' => $actor->username,
                    'message' => $content,
                ];

                $checker->logResult($result, $contentInfo);

                // Send notification
                $notifications->sync(
                    new CivilityFlaggedBlueprint($post, $result['action'], $result['reason'], $result['score']),
                    [$actor]
                );

                // Webhook alert
                $webhook->notify($result, $contentInfo);

                // Auto-suspend check
                $this->checkAutoSuspendStatic($actor, $checker, $settings);
            });
        } elseif ($result['action'] === 'allowed') {
            $logAll = (bool) $this->settings->get('ralkage-civility-filter.log_all');
            if ($logAll) {
                $checker = $this->checker;
                $post->afterSave(function ($post) use ($result, $actor, $content, $checker) {
                    $checker->logResult($result, [
                        'content_type' => 'post',
                        'content_id' => $post->id,
                        'discussion_id' => $post->discussion_id,
                        'user_id' => $actor->id,
                        'username' => $actor->username,
                        'message' => $content,
                    ]);
                });
            }
        }
    }

    protected function checkAutoSuspend($actor): void
    {
        $this->checkAutoSuspendStatic($actor, $this->checker, $this->settings);
    }

    protected static function checkAutoSuspendStatic($actor, CivilityChecker $checker, SettingsRepositoryInterface $settings): void
    {
        $threshold = (int) $settings->get('ralkage-civility-filter.auto_suspend_threshold');
        $days = (int) ($settings->get('ralkage-civility-filter.auto_suspend_days') ?: 3);
        $window = (int) ($settings->get('ralkage-civility-filter.auto_suspend_window') ?: 7);

        if ($threshold <= 0) {
            return;
        }

        $violationCount = $checker->getRecentViolationCount($actor->id, $window);

        if ($violationCount >= $threshold) {
            // Check if user has suspended_until column (flarum/suspend extension)
            $user = User::find($actor->id);
            if ($user && isset($user->suspended_until)) {
                // Don't re-suspend if already suspended
                if ($user->suspended_until && strtotime($user->suspended_until) > time()) {
                    return;
                }

                $user->suspended_until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $user->save();
            }
        }
    }

    protected function isMonitoredDiscussion($post): bool
    {
        $tagIdsSetting = $this->settings->get('ralkage-civility-filter.monitored_tags');

        if (empty($tagIdsSetting)) {
            return true;
        }

        $monitoredIds = json_decode($tagIdsSetting, true);

        if (! is_array($monitoredIds) || empty($monitoredIds)) {
            return true;
        }

        $monitoredIds = array_map('intval', $monitoredIds);

        $discussion = $post->discussion;
        if (! $discussion) {
            return true;
        }

        if (method_exists($discussion, 'tags') && $discussion->tags) {
            foreach ($discussion->tags as $tag) {
                if (in_array((int) $tag->id, $monitoredIds)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }
}
