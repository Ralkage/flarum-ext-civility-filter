<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CivilityStatsController implements RequestHandlerInterface
{
    protected $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $request->getAttribute('actor');

        if (! $actor || ! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $total = $this->db->table('civility_logs')->count();

        // Action breakdown
        $actions = $this->db->table('civility_logs')
            ->selectRaw('action_taken, COUNT(*) as count')
            ->groupBy('action_taken')
            ->pluck('count', 'action_taken')
            ->toArray();

        // Top categories
        $allCategories = $this->db->table('civility_logs')
            ->whereNotNull('categories')
            ->where('categories', '!=', '[]')
            ->pluck('categories')
            ->toArray();

        $categoryCounts = [];
        foreach ($allCategories as $cats) {
            $decoded = json_decode($cats, true) ?: [];
            foreach ($decoded as $cat) {
                $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
            }
        }
        arsort($categoryCounts);

        // Average score
        $avgScore = $this->db->table('civility_logs')->avg('civility_score') ?: 0;

        // Top offenders (users with most non-allowed actions)
        $topOffenders = $this->db->table('civility_logs')
            ->selectRaw('user_id, username, COUNT(*) as violation_count, ROUND(AVG(civility_score)) as avg_score')
            ->where('action_taken', '!=', 'allowed')
            ->groupBy('user_id', 'username')
            ->orderByDesc('violation_count')
            ->limit(10)
            ->get()
            ->toArray();

        // Daily trend (last 30 days)
        $dailyTrend = $this->db->table('civility_logs')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN action_taken != "allowed" THEN 1 ELSE 0 END) as flagged')
            ->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')))
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();

        return new JsonResponse([
            'total' => $total,
            'actions' => $actions,
            'categories' => $categoryCounts,
            'averageScore' => round($avgScore, 1),
            'topOffenders' => $topOffenders,
            'dailyTrend' => $dailyTrend,
        ]);
    }
}
