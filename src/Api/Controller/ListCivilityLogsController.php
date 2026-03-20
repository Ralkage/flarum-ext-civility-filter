<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\ConnectionInterface;
use Ralkage\CivilityFilter\Api\Serializer\CivilityLogSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListCivilityLogsController extends AbstractListController
{
    public $serializer = CivilityLogSerializer::class;

    protected $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    protected function data(ServerRequestInterface $request, Document $document)
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

        // Apply filters
        if (! empty($params['filter']['username'])) {
            $query->where('username', $params['filter']['username']);
        }
        if (! empty($params['filter']['action'])) {
            $query->where('action_taken', $params['filter']['action']);
        }

        $total = $query->count();

        $document->addMeta('total', $total);
        $document->addMeta('perPage', $perPage);
        $document->addMeta('currentPage', $page);

        return $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->all();
    }
}
