<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\Post\Post;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ModeratePostController implements RequestHandlerInterface
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

        $body = $request->getParsedBody();
        $action = $body['action'] ?? '';
        $postId = (int) ($body['postId'] ?? 0);
        $userId = (int) ($body['userId'] ?? 0);

        switch ($action) {
            case 'approve':
                return $this->approvePost($postId);

            case 'delete':
                return $this->deletePost($postId);

            case 'suspend':
                $days = (int) ($body['days'] ?? 3);
                return $this->suspendUser($userId, $days);

            default:
                return new JsonResponse(['error' => 'Invalid action'], 422);
        }
    }

    protected function approvePost(int $postId): ResponseInterface
    {
        $post = Post::find($postId);

        if (! $post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        $post->is_approved = true;
        $post->save();

        return new JsonResponse(['success' => true, 'action' => 'approved', 'postId' => $postId]);
    }

    protected function deletePost(int $postId): ResponseInterface
    {
        $post = Post::find($postId);

        if (! $post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        $post->hide();
        $post->save();

        return new JsonResponse(['success' => true, 'action' => 'deleted', 'postId' => $postId]);
    }

    protected function suspendUser(int $userId, int $days): ResponseInterface
    {
        $user = User::find($userId);

        if (! $user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        // Use Flarum's suspend functionality if available
        if (isset($user->suspended_until)) {
            $user->suspended_until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $user->save();

            return new JsonResponse([
                'success' => true,
                'action' => 'suspended',
                'userId' => $userId,
                'until' => $user->suspended_until,
            ]);
        }

        return new JsonResponse(['error' => 'Suspend extension not available'], 422);
    }
}
