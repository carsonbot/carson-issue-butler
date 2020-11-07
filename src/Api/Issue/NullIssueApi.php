<?php

namespace App\Api\Issue;

use App\Model\Repository;

class NullIssueApi implements IssueApi
{
    public function open(Repository $repository, string $title, string $body, array $labels)
    {
    }

    public function commentOnIssue(Repository $repository, $issueNumber, string $commentBody)
    {
    }

    public function close(Repository $repository, $issueNumber)
    {
    }
}
