<?php

namespace Ralkage\CivilityFilter;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class WebhookNotifier
{
    protected $settings;
    protected $logger;

    public function __construct(SettingsRepositoryInterface $settings, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function notify(array $result, array $contentInfo): void
    {
        $webhookUrl = $this->settings->get('ralkage-civility-filter.webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

        $minAction = $this->settings->get('ralkage-civility-filter.webhook_min_action') ?: 'warned';
        $actionOrder = ['allowed' => 0, 'warned' => 1, 'moderated' => 2, 'blocked' => 3];

        if (($actionOrder[$result['action']] ?? 0) < ($actionOrder[$minAction] ?? 1)) {
            return;
        }

        $isDiscord = strpos($webhookUrl, 'discord.com/api/webhooks') !== false;

        try {
            $client = new Client();

            if ($isDiscord) {
                $this->sendDiscord($client, $webhookUrl, $result, $contentInfo);
            } else {
                $this->sendGeneric($client, $webhookUrl, $result, $contentInfo);
            }
        } catch (\Exception $e) {
            $this->logger->error('CivilityFilter webhook error: ' . $e->getMessage());
        }
    }

    protected function sendDiscord(Client $client, string $url, array $result, array $contentInfo): void
    {
        $colors = ['warned' => 16776960, 'moderated' => 16744448, 'blocked' => 16711680];
        $color = $colors[$result['action']] ?? 8421504;

        $client->post($url, [
            'json' => [
                'embeds' => [[
                    'title' => 'Civility Filter: Post ' . ucfirst($result['action']),
                    'color' => $color,
                    'fields' => [
                        ['name' => 'User', 'value' => $contentInfo['username'] ?? 'Unknown', 'inline' => true],
                        ['name' => 'Score', 'value' => (string) $result['score'], 'inline' => true],
                        ['name' => 'Action', 'value' => ucfirst($result['action']), 'inline' => true],
                        ['name' => 'Categories', 'value' => implode(', ', $result['categories'] ?: ['None']), 'inline' => false],
                        ['name' => 'Reason', 'value' => mb_substr($result['reason'], 0, 1024) ?: 'N/A', 'inline' => false],
                        ['name' => 'Excerpt', 'value' => mb_substr($contentInfo['message'] ?? '', 0, 500) ?: 'N/A', 'inline' => false],
                    ],
                    'timestamp' => date('c'),
                ]],
            ],
            'timeout' => 5,
        ]);
    }

    protected function sendGeneric(Client $client, string $url, array $result, array $contentInfo): void
    {
        $client->post($url, [
            'json' => [
                'event' => 'civility_flagged',
                'action' => $result['action'],
                'score' => $result['score'],
                'categories' => $result['categories'],
                'reason' => $result['reason'],
                'user_id' => $contentInfo['user_id'] ?? 0,
                'username' => $contentInfo['username'] ?? '',
                'post_id' => $contentInfo['content_id'] ?? 0,
                'discussion_id' => $contentInfo['discussion_id'] ?? 0,
                'excerpt' => mb_substr($contentInfo['message'] ?? '', 0, 500),
                'timestamp' => date('c'),
            ],
            'timeout' => 5,
        ]);
    }
}
