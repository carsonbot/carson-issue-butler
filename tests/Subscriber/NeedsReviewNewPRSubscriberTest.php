<?php

namespace App\Tests\Subscriber;

use App\Api\Status\Status;
use App\Api\Status\StatusApi;
use App\Event\GitHubEvent;
use App\GitHubEvents;
use App\Model\Repository;
use App\Subscriber\NeedsReviewNewPRSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class NeedsReviewNewPRSubscriberTest extends TestCase
{
    private $needsReviewSubscriber;

    private $statusApi;

    private $repository;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    protected function setUp(): void
    {
        $this->statusApi = $this->createMock(StatusApi::class);
        $this->needsReviewSubscriber = new NeedsReviewNewPRSubscriber($this->statusApi);
        $this->repository = new Repository('weaverryan', 'symfony', null);

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addSubscriber($this->needsReviewSubscriber);
    }

    public function testOnPullRequestOpen()
    {
        $this->statusApi->expects($this->once())
            ->method('setIssueStatus')
            ->with(1234, Status::NEEDS_REVIEW);

        $event = new GitHubEvent([
            'action' => 'opened',
            'pull_request' => ['number' => 1234, 'title' => 'foobar'],
        ], $this->repository);

        $this->dispatcher->dispatch($event, GitHubEvents::PULL_REQUEST);

        $responseData = $event->getResponseData();

        $this->assertCount(2, $responseData);
        $this->assertSame(1234, $responseData['pull_request']);
        $this->assertSame(Status::NEEDS_REVIEW, $responseData['status_change']);
    }

    public function testOnPullRequestOpenWIP()
    {
        $this->statusApi->expects($this->once())
            ->method('setIssueStatus')
            ->with(1234, Status::NEEDS_WORK);

        $event = new GitHubEvent([
            'action' => 'opened',
            'pull_request' => ['number' => 1234, 'title' => '[WIP] foobar'],
        ], $this->repository);

        $this->dispatcher->dispatch($event, GitHubEvents::PULL_REQUEST);

        $responseData = $event->getResponseData();
        $this->assertCount(2, $responseData);
        $this->assertSame(1234, $responseData['pull_request']);
        $this->assertSame(Status::NEEDS_WORK, $responseData['status_change']);
    }

    public function testOnPullRequestNotOpen()
    {
        $this->statusApi->expects($this->never())
            ->method('setIssueStatus');

        $event = new GitHubEvent([
            'action' => 'close',
        ], $this->repository);

        $this->dispatcher->dispatch($event, GitHubEvents::PULL_REQUEST);

        $responseData = $event->getResponseData();

        $this->assertEmpty($responseData);
    }
}
