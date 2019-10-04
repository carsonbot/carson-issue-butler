<?php

namespace App\Tests\Subscriber;

use App\Event\GitHubEvent;
use App\GitHubEvents;
use App\Issues\Status;
use App\Repository\Repository;
use App\Subscriber\NeedsReviewNewPRSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class NeedsReviewNewPRSubscriberTest extends TestCase
{
    private $needsReviewSubscriber;
    private $statusApi;
    private $repository;
    private $dispatcher;

    protected function setUp()
    {
        $this->statusApi = $this->getMockBuilder('App\Issues\StatusApi')->getMock();

        $this->needsReviewSubscriber = new NeedsReviewNewPRSubscriber($this->statusApi);
        $this->repository = new Repository('weaverryan', 'symfony', [], null);

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addSubscriber($this->needsReviewSubscriber);
    }

    public function testOnPullRequestOpen()
    {
        $this->statusApi->expects($this->once())
            ->method('setIssueStatus')
            ->with(1234, Status::NEEDS_REVIEW);

        $event = new GitHubEvent(array(
            'action' => 'opened',
            'pull_request' => array('number' => 1234),
        ), $this->repository);

        $this->dispatcher->dispatch($event, GitHubEvents::PULL_REQUEST);

        $responseData = $event->getResponseData();

        $this->assertCount(2, $responseData);
        $this->assertSame(1234, $responseData['pull_request']);
        $this->assertSame(Status::NEEDS_REVIEW, $responseData['status_change']);
    }

    public function testOnPullRequestNotOpen()
    {
        $this->statusApi->expects($this->never())
            ->method('setIssueStatus');

        $event = new GitHubEvent(array(
            'action' => 'close',
        ), $this->repository);

        $this->dispatcher->dispatch($event, GitHubEvents::PULL_REQUEST);

        $responseData = $event->getResponseData();

        $this->assertCount(1, $responseData);
        $this->assertSame('close', $responseData['unsupported_action']);
    }
}