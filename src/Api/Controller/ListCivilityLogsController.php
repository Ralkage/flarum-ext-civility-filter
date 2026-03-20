<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListCivilityLogsController implements RequestHandlerInterface
{
    protected ConnectionInterface $db;

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

        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page']['number'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $query = $this->db->table('civility_logs');

        if (! empty($params['filter']['username'])) {
            $query->where('username', $params['filter']['username']);
        }
        if (! empty($params['filter']['action'])) {
            $query->where('action_taken', $params['filter']['action']);
        }

        $total = $query->count();

        $logs = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $data = $logs->map(fn ($log) => [
            'type' => 'civility-logs',
            'id' => (string) $log->id,
            'attributes' => [
                'id' => $log->id,
                'contentType' => $log->content_type,
                'contentId' => $log->content_id,
                'discussionId' => $log->discussion_id,
                'userId' => $log->user_id,
                'username' => $log->username,
                'messageExcerpt' => $log->message_excerpt,
                'civilityScore' => (int) $log->civility_score,
                'categories' => json_decode($log->categories, true) ?: [],
                'actionTaken' => $log->action_taken,
                'createdAt' => $log->created_at,
            ],
        ])->toArray();

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
            ],
        ]);
    }
}
