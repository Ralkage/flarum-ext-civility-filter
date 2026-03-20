<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExportCivilityLogsController implements RequestHandlerInterface
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

        $logs = $this->db->table('civility_logs')
            ->orderBy('created_at', 'desc')
            ->limit(10000)
            ->get();

        $csv = "ID,Date,Username,User ID,Score,Action,Categories,Post ID,Discussion ID,Message Excerpt\n";

        foreach ($logs as $log) {
            $categories = str_replace('"', '""', $log->categories);
            $excerpt = str_replace('"', '""', $log->message_excerpt ?? '');

            $csv .= sprintf(
                '%d,"%s","%s",%d,%d,"%s","%s",%d,%d,"%s"' . "\n",
                $log->id,
                $log->created_at,
                str_replace('"', '""', $log->username),
                $log->user_id,
                $log->civility_score,
                $log->action_taken,
                $categories,
                $log->content_id,
                $log->discussion_id,
                mb_substr($excerpt, 0, 200)
            );
        }

        $response = new Response('php://temp', 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="civility-logs-' . date('Y-m-d') . '.csv"',
        ]);

        $response->getBody()->write($csv);

        return $response;
    }
}
