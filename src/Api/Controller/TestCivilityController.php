<?php

namespace Ralkage\CivilityFilter\Api\Controller;

use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Ralkage\CivilityFilter\CivilityChecker;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TestCivilityController implements RequestHandlerInterface
{
    protected $checker;

    public function __construct(CivilityChecker $checker)
    {
        $this->checker = $checker;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $request->getAttribute('actor');

        if (! $actor || ! $actor->isAdmin()) {
            throw new PermissionDeniedException();
        }

        $body = $request->getParsedBody();
        $message = $body['message'] ?? '';
        $discussionTitle = $body['discussionTitle'] ?? '';

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message is required'], 422);
        }

        $context = [];
        if (! empty($discussionTitle)) {
            $context['discussion_title'] = $discussionTitle;
        }

        $result = $this->checker->analyzeForTest($message, $context);

        return new JsonResponse($result);
    }
}
