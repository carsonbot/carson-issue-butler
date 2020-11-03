<?php

namespace App\Subscriber;

use App\Event\GitHubEvent;
use App\GitHubEvents;
use App\Issues\CommentsApiInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class WelcomeFirstTimeContributorSubscriber implements EventSubscriberInterface
{
    private $commentsApi;

    public function __construct(CommentsApiInterface $commentsApi)
    {
        $this->commentsApi = $commentsApi;
    }

    public function onPullRequest(GitHubEvent $event)
    {
        $data = $event->getData();
        if ('opened' !== $data['action']) {
            return;
        }

        if ('NONE' !== ($data['pull_request']['author_association'] ?? '')) {
            return;
        }

        $repository = $event->getRepository();
        $pullRequestNumber = $data['pull_request']['number'];
        $defaultBranch = $data['repository']['default_branch'];
        $this->commentsApi->commentOnIssue($repository, $pullRequestNumber, <<<TXT
Hey,

I see that this is your first PR. That is great! Thank you.

Symfony has a [contribution guide](https://symfony.com/doc/current/contributing/index.html) which I suggest you to read.

In short:
- Always add tests
- Keep backward compatibility (see https://symfony.com/bc).
- Bug fixes must be submitted against the lowest maintained branch where they apply (see https://symfony.com/releases)
- Features and deprecations must be submitted against branch $defaultBranch.

Review the GitHub status checks of your pull request and try to solve the reported issues. If some tests are failing, try to see if they are failing because of this change.

When two Symfony core team members approve this change, it will be merged and you have become an official Symfony contributor.

I am going to sit back now and wait for the reviews.

// Carsonbot
TXT
);

        $event->setResponseData([
            'pull_request' => $pullRequestNumber,
            'new_contributor' => true,
        ]);
    }

    public static function getSubscribedEvents()
    {
        return [
            GitHubEvents::PULL_REQUEST => 'onPullRequest',
        ];
    }
}
