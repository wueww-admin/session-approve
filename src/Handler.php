<?php


namespace SessionApprove;


use Doctrine\DBAL\DBALException;
use SessionApprove\Exception\ProposalConflictException;
use SessionApprove\Exception\SessionNotFoundException;
use TopicAdvisor\Lambda\RuntimeApi\Http\HttpRequestInterface;
use TopicAdvisor\Lambda\RuntimeApi\Http\HttpResponse;
use TopicAdvisor\Lambda\RuntimeApi\InvocationRequestHandlerInterface;
use TopicAdvisor\Lambda\RuntimeApi\InvocationRequestInterface;
use TopicAdvisor\Lambda\RuntimeApi\InvocationResponseInterface;

class Handler implements InvocationRequestHandlerInterface
{
    /**
     * @var SessionRepository
     */
    private $sessionRepository;

    public function __construct(SessionRepository $sessionRepository)
    {
        $this->sessionRepository = $sessionRepository;
    }

    /**
     * @param InvocationRequestInterface $request
     * @return bool
     */
    public function canHandle(InvocationRequestInterface $request): bool
    {
        return $request instanceof HttpRequestInterface;
    }

    /**
     * @param InvocationRequestInterface $request
     * @return void
     */
    public function preHandle(InvocationRequestInterface $request)
    {
    }

    /**
     * @param InvocationRequestInterface $request
     * @return InvocationResponseInterface
     * @throws DBALException
     * @throws \Exception
     */
    public function handle(InvocationRequestInterface $request): InvocationResponseInterface
    {
        if (!$request instanceof HttpRequestInterface) {
            throw new \LogicException('Must be invoked with HttpRequestInterface only');
        }

        $response = new HttpResponse($request->getInvocationId());

        if ($request->getMethod() !== 'POST') {
            $response->setStatusCode(405);
            return $response;
        }

        $sessionId = (int)trim($request->getUri()->getPath(), '/');

        if (array_key_exists('proposal', $request->getQueryParams())) {
            $proposalId = (int) $request->getQueryParams()['proposal'];
        } else {
            $proposalId = null;
        }

        try {
            $this->sessionRepository->approveSession($sessionId, $proposalId);
        } catch (ProposalConflictException $ex) {
            $response->setStatusCode(409);
            return $response;
        } catch (SessionNotFoundException $ex) {
            $response->setStatusCode(404);
            return $response;
        }

        $response->setStatusCode(200);
        return $response;
    }

    /**
     * @param InvocationRequestInterface $request
     * @param InvocationResponseInterface $response
     * @return void
     */
    public function postHandle(InvocationRequestInterface $request, InvocationResponseInterface $response)
    {
    }
}
