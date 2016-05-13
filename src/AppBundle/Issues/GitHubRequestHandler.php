<?php

namespace AppBundle\Issues;

use AppBundle\Event\GitHubEvent;
use AppBundle\Exception\GitHubAccessDeniedException;
use AppBundle\Exception\GitHubBadRequestException;
use AppBundle\Exception\GitHubExceptionInterface;
use AppBundle\Exception\GitHubInvalidConfigurationException;
use AppBundle\Exception\GitHubPreconditionFailedException;
use AppBundle\Exception\GitHubRuntimeException;
use AppBundle\Issues\GitHub\CachedLabelsApi;
use AppBundle\Issues\GitHub\GitHubStatusApi;
use Github\Api\Issue\Labels;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GitHubRequestHandler.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class GitHubRequestHandler
{
    private $labelsApi;
    private $dispatcher;
    private $repositories;

    public function __construct(Labels $labelsApi, EventDispatcherInterface $dispatcher, array $repositories)
    {
        $this->labelsApi = $labelsApi;
        $this->dispatcher = $dispatcher;
        $this->repositories = $repositories;
    }

    /**
     * @return array The response data
     *
     * @throws GitHubExceptionInterface When the request or the configuration are invalid
     */
    public function handle(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        if (null === $data) {
            throw new GitHubBadRequestException('Invalid JSON body!');
        }

        $repository = isset($data['repository']['full_name']) ? $data['repository']['full_name'] : null;
        if (empty($repository)) {
            throw new GitHubBadRequestException('No repository name!');
        }

        if (!isset($this->repositories[$repository])) {
            throw new GitHubPreconditionFailedException(sprintf('Unsupported repository "%s".', $repository));
        }

        $config = $this->repositories[$repository];
        if (isset($config['secret'])) {
            if (!$request->headers->has('X-Hub-Signature')) {
                throw new GitHubAccessDeniedException('The request is not secured.');
            }
            if (!$this->authenticate($request->headers->get('X-Hub-Signature'), $config['secret'], $request->getContent())) {
                throw new GitHubAccessDeniedException('Invalid signature.');
            }
        }
        if (empty($config['listeners'])) {
            throw new GitHubInvalidConfigurationException('The repository "%s" has no listeners configured.');
        }

        $api = new GitHubStatusApi(
            new CachedLabelsApi($this->labelsApi, $repository),
            $repository
        );
        foreach ($config['listeners'] as $listener) {
            if (!is_subclass_of($listener, __NAMESPACE__.'\IssueListener')) {
                throw new GitHubInvalidConfigurationException(sprintf('The listener "%s" must be an instance of "\AppBundle\Issues\IssueListener".', $listener));
            }

            $this->dispatcher->addSubscriber(new $listener($api));
        }

        $event = new GitHubEvent($data, $repository, $config['maintainers']);
        $eventName = $request->headers->get('X-Github-Event');

        try {
            $this->dispatcher->dispatch('github.'.$eventName, $event);
        } catch (\Exception $e) {
            throw new GitHubRuntimeException(sprintf('Failed dispatching "%s" event for "%s" repository.', $eventName, $repository), 0, $e);
        }

        $responseData = $event->getResponseData();

        if (empty($responseData)) {
            throw new GitHubPreconditionFailedException(sprintf('Unsupported event "%s"', $eventName));
        }

        return $responseData;
    }

    private function authenticate($hash, $key, $data)
    {
        if (!extension_loaded('hash')) {
            throw new GitHubInvalidConfigurationException('"hash" extension is needed to check request signature.');
        }

        return $hash !== 'sha1='.hash_hmac('sha1', $data, $key);
    }
}
