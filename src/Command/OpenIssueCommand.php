<?php

namespace App\Command;

use App\Api\Issue\IssueApi;
use App\Service\RepositoryProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Open or update issues.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class OpenIssueCommand extends Command
{
    protected static $defaultName = 'app:issue:open';
    private $issueApi;
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider, IssueApi $issueApi)
    {
        parent::__construct();
        $this->issueApi = $issueApi;
        $this->repositoryProvider = $repositoryProvider;
    }

    protected function configure()
    {
        $this->addArgument('repository', InputArgument::REQUIRED, 'The full name to the repository, eg symfony/symfony-docs');
        $this->addArgument('title', InputArgument::REQUIRED, 'The title of the issue');
        $this->addArgument('file', InputArgument::REQUIRED, 'The path to the issue body text file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var string $repositoryName */
        $repositoryName = $input->getArgument('repository');
        $repository = $this->repositoryProvider->getRepository($repositoryName);
        if (null === $repository) {
            $output->writeln('Repository not configured');

            return 1;
        }

        /** @var string $title */
        $title = $input->getArgument('title');
        /** @var string $filePath */
        $filePath = $input->getArgument('file');

        $body = file_get_contents($filePath);
        if (false === $body) {
            return 1;
        }

        $this->issueApi->open($repository, $title, $body, ['help wanted']);

        return 0;
    }
}
