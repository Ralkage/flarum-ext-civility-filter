<?php

namespace Ralkage\CivilityFilter;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;

class CivilityChecker
{
    protected $settings;
    protected $db;
    protected $logger;

    public function __construct(
        SettingsRepositoryInterface $settings,
        ConnectionInterface $db,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function analyze(string $message, array $context = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildResult(0, [], '', 'allowed');
        }

        $plainText = $this->stripMarkup($message);

        if (mb_strlen($plainText) < 10) {
            return $this->buildResult(0, [], 'Too short to analyze', 'allowed');
        }

        // Pre-filter: check word blocklist before calling AI
        $blocklistResult = $this->checkBlocklist($plainText);
        if ($blocklistResult) {
            return $blocklistResult;
        }

        // Rate limiting: check if we've exceeded API call budget
        if ($this->isRateLimited()) {
            $this->logger->warning('CivilityFilter: Rate limit reached, failing open');
            return $this->buildResult(0, [], 'Rate limit reached - failed open', 'allowed');
        }

        $prompt = $this->buildPrompt($plainText, $context);
        $aiResult = $this->callAi($prompt);

        // Record this API call for rate limiting
        $this->recordApiCall();

        if (! $aiResult) {
            return $this->buildResult(0, [], 'API error - failed open', 'allowed');
        }

        return $this->processResponse($aiResult);
    }

    public function analyzeForTest(string $message, array $context = []): array
    {
        if (! $this->getApiKey()) {
            return ['error' => 'No API key configured'];
        }

        $plainText = $this->stripMarkup($message);
        $prompt = $this->buildPrompt($plainText, $context);

        $start = microtime(true);
        $aiResult = $this->callAi($prompt);
        $elapsed = round((microtime(true) - $start) * 1000);

        if ($aiResult) {
            $result = $this->processResponse($aiResult);
            $result['latency'] = $elapsed;
            $result['api_success'] = true;

            return $result;
        }

        return [
            'score' => 0,
            'categories' => [],
            'reason' => '',
            'action' => 'allowed',
            'api_success' => false,
            'latency' => $elapsed,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('ralkage-civility-filter.enabled')
            && ! empty($this->getApiKey());
    }

    protected function getProvider(): string
    {
        return $this->settings->get('ralkage-civility-filter.ai_provider') ?: 'anthropic';
    }

    protected function getApiKey(): ?string
    {
        $provider = $this->getProvider();

        if ($provider === 'openai') {
            return $this->settings->get('ralkage-civility-filter.openai_api_key') ?: null;
        }

        return $this->settings->get('ralkage-civility-filter.api_key') ?: null;
    }

    // --- Word Blocklist Pre-filter ---

    protected function checkBlocklist(string $text): ?array
    {
        $blocklist = $this->settings->get('ralkage-civility-filter.word_blocklist');

        if (empty($blocklist)) {
            return null;
        }

        $words = array_filter(array_map('trim', explode("\n", $blocklist)));

        if (empty($words)) {
            return null;
        }

        $lowerText = mb_strtolower($text);
        $matched = [];

        foreach ($words as $word) {
            $lowerWord = mb_strtolower(trim($word));
            if (! empty($lowerWord) && mb_strpos($lowerText, $lowerWord) !== false) {
                $matched[] = $lowerWord;
            }
        }

        if (! empty($matched)) {
            return $this->buildResult(
                100,
                ['blocklisted'],
                'Blocked by word filter: ' . implode(', ', array_slice($matched, 0, 5)),
                'blocked'
            );
        }

        return null;
    }

    // --- Rate Limiting ---

    protected function isRateLimited(): bool
    {
        $limit = (int) $this->settings->get('ralkage-civility-filter.rate_limit');

        if ($limit <= 0) {
            return false;
        }

        $windowStart = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $count = $this->db->table('civility_logs')
            ->where('created_at', '>=', $windowStart)
            ->count();

        return $count >= $limit;
    }

    protected function recordApiCall(): void
    {
        // API calls are already tracked via logResult - no extra table needed
    }

    // --- Prompt Building ---

    public function buildPrompt(string $text, array $context = []): string
    {
        $customPrompt = $this->settings->get('ralkage-civility-filter.custom_prompt');

        if (! empty($customPrompt)) {
            $prompt = $customPrompt . "\n\n";
        } else {
            $prompt = "You are a forum civility moderator. Analyze the following forum post and return ONLY valid JSON (no other text):\n";
            $prompt .= "{\"score\":N,\"categories\":[],\"reason\":\"...\"}\n\n";
            $prompt .= "Score guide:\n";
            $prompt .= "0-20: Civil, constructive, or neutral\n";
            $prompt .= "21-40: Mildly rude or snarky but not harmful\n";
            $prompt .= "41-60: Hostile tone, dismissive, or antagonistic\n";
            $prompt .= "61-80: Personal attacks, inflammatory language, bad-faith arguing\n";
            $prompt .= "81-95: Hate speech, severe harassment, threats\n";
            $prompt .= "96-100: Extreme abuse or dangerous content\n\n";
            $prompt .= "Categories (include all that apply): personal_attack, inflammatory, hate_speech, bad_faith, trolling, harassment, threat, profanity, discrimination\n\n";
            $prompt .= "Important: Political disagreement alone is NOT uncivil. Focus on HOW something is said, not WHAT position is taken. Passionate but respectful debate should score low.\n\n";
        }

        if (! empty($context['discussion_title'])) {
            $prompt .= "Discussion title: " . $context['discussion_title'] . "\n";
        }

        $truncated = mb_substr($text, 0, 2000);
        $prompt .= "\nPost to analyze:\n\"\"\"\n" . $truncated . "\n\"\"\"";

        return $prompt;
    }

    // --- AI API Calls ---

    protected function callAi(string $prompt): ?array
    {
        $provider = $this->getProvider();

        if ($provider === 'openai') {
            return $this->callOpenAiApi($prompt);
        }

        return $this->callClaudeApi($prompt);
    }

    public function callClaudeApi(string $prompt): ?array
    {
        $apiKey = $this->settings->get('ralkage-civility-filter.api_key');
        $model = $this->settings->get('ralkage-civility-filter.model') ?: 'claude-haiku-4-5-20251001';

        $client = new Client();

        try {
            $response = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 256,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                'timeout' => 15,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['content'][0]['text'])) {
                return $this->parseJsonResponse($body['content'][0]['text']);
            }
        } catch (\Exception $e) {
            $this->logger->error('CivilityFilter Claude API error: ' . $e->getMessage());
        }

        return null;
    }

    public function callOpenAiApi(string $prompt): ?array
    {
        $apiKey = $this->settings->get('ralkage-civility-filter.openai_api_key');
        $model = $this->settings->get('ralkage-civility-filter.model') ?: 'gpt-4o-mini';

        $client = new Client();

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 256,
                    'temperature' => 0.1,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a forum civility moderator. Respond only with valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                'timeout' => 15,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['choices'][0]['message']['content'])) {
                return $this->parseJsonResponse($body['choices'][0]['message']['content']);
            }
        } catch (\Exception $e) {
            $this->logger->error('CivilityFilter OpenAI API error: ' . $e->getMessage());
        }

        return null;
    }

    protected function parseJsonResponse(string $text): ?array
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```\s*$/', '', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);
        if (is_array($parsed) && isset($parsed['score'])) {
            return $parsed;
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return json_decode($matches[0], true);
        }

        return null;
    }

    // --- Response Processing ---

    public function processResponse(array $aiResult): array
    {
        $score = isset($aiResult['score']) ? (int) $aiResult['score'] : 0;
        $score = max(0, min(100, $score));

        $categories = isset($aiResult['categories']) ? (array) $aiResult['categories'] : [];
        $reason = isset($aiResult['reason']) ? (string) $aiResult['reason'] : '';

        $blockThreshold = (int) ($this->settings->get('ralkage-civility-filter.block_threshold') ?: 95);
        $holdThreshold = (int) ($this->settings->get('ralkage-civility-filter.hold_threshold') ?: 80);
        $warnThreshold = (int) ($this->settings->get('ralkage-civility-filter.warn_threshold') ?: 60);

        if ($score >= $blockThreshold) {
            $action = 'blocked';
        } elseif ($score >= $holdThreshold) {
            $action = 'moderated';
        } elseif ($score >= $warnThreshold) {
            $action = 'warned';
        } else {
            $action = 'allowed';
        }

        return $this->buildResult($score, $categories, $reason, $action, $aiResult);
    }

    protected function buildResult(int $score, array $categories, string $reason, string $action, array $raw = []): array
    {
        return [
            'score' => $score,
            'categories' => $categories,
            'reason' => $reason,
            'action' => $action,
            'raw' => $raw,
        ];
    }

    protected function stripMarkup(string $message): string
    {
        $text = preg_replace('/<QUOTE[^>]*>.*?<\/QUOTE>/si', '', $message);
        $text = strip_tags($text);
        $text = preg_replace('/\[\/?\w+[^\]]*\]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    // --- Auto-suspend ---

    public function getRecentViolationCount(int $userId, int $days = 7): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $this->db->table('civility_logs')
            ->where('user_id', $userId)
            ->where('action_taken', '!=', 'allowed')
            ->where('created_at', '>=', $since)
            ->count();
    }

    // --- Logging ---

    public function logResult(array $result, array $contentInfo): void
    {
        $logAll = (bool) $this->settings->get('ralkage-civility-filter.log_all');

        if ($result['action'] === 'allowed' && ! $logAll) {
            return;
        }

        $this->db->table('civility_logs')->insert([
            'content_type' => $contentInfo['content_type'] ?? 'post',
            'content_id' => $contentInfo['content_id'] ?? 0,
            'discussion_id' => $contentInfo['discussion_id'] ?? 0,
            'user_id' => $contentInfo['user_id'] ?? 0,
            'username' => $contentInfo['username'] ?? '',
            'message_excerpt' => mb_substr($contentInfo['message'] ?? '', 0, 500),
            'civility_score' => $result['score'],
            'categories' => json_encode($result['categories']),
            'action_taken' => $result['action'],
            'ai_response' => json_encode($result['raw'] ?? $result),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
