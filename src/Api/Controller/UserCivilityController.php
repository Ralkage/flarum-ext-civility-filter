<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserCivilityController implements RequestHandlerInterface
{
    protected $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $request->getAttribute('actor');
        $params = $request->getQueryParams();
        $userId = (int) ($params['userId'] ?? 0);

        if (! $actor) {
            throw new PermissionDeniedException();
        }

        // Users can view their own history, admins can view anyone's
        if ($userId !== $actor->id && ! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        if (! $userId) {
            return new JsonResponse(['error' => 'userId is required'], 422);
        }

        $total = $this->db->table('civility_logs')
            ->where('user_id', $userId)
            ->count();

        $avgScore = $this->db->table('civility_logs')
            ->where('user_id', $userId)
            ->avg('civility_score') ?: 0;

        $actions = $this->db->table('civility_logs')
            ->where('user_id', $userId)
            ->selectRaw('action_taken, COUNT(*) as count')
            ->groupBy('action_taken')
            ->pluck('count', 'action_taken')
            ->toArray();

        $recentLogs = $this->db->table('civility_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'civility_score', 'action_taken', 'categories', 'created_at', 'discussion_id'])
            ->toArray();

        // Monthly trend
        $monthlyTrend = $this->db->table('civility_logs')
            ->where('user_id', $userId)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, ROUND(AVG(civility_score)) as avg_score, COUNT(*) as total')
            ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
            ->orderBy('month')
            ->limit(12)
            ->get()
            ->toArray();

        return new JsonResponse([
            'userId' => $userId,
            'totalChecks' => $total,
            'averageScore' => round($avgScore, 1),
            'actions' => $actions,
            'recentLogs' => $recentLogs,
            'monthlyTrend' => $monthlyTrend,
        ]);
    }
}
