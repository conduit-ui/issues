<?php

declare(strict_types=1);

namespace Examples;

use ConduitUi\GitHubConnector\Connector;
use ConduitUI\GithubIssues\Contracts\ManagesIssueAssigneesInterface;
use ConduitUI\GithubIssues\Contracts\ManagesIssueLabelsInterface;
use ConduitUI\GithubIssues\Contracts\ManagesIssuesInterface;
use ConduitUI\GithubIssues\Traits\ManagesIssueAssignees;
use ConduitUI\GithubIssues\Traits\ManagesIssueLabels;
use ConduitUI\GithubIssues\Traits\ManagesIssues;

/**
 * Example composite service that combines issue management with other GitHub functionality
 * Your service could also include traits for repositories, pull requests, etc.
 */
class CompositeGithubService implements ManagesIssueAssigneesInterface, ManagesIssueLabelsInterface, ManagesIssuesInterface
{
    use ManagesIssueAssignees;
    use ManagesIssueLabels;
    use ManagesIssues;
    // use ManagesRepositories; // From another package
    // use ManagesPullRequests; // From another package
    // use ManagesProjects; // From another package

    public function __construct(
        private readonly Connector $connector
    ) {}

    /**
     * Custom business logic combining multiple GitHub operations
     */
    public function triageIssue(string $owner, string $repo, int $issueNumber, array $config): void
    {
        $issue = $this->getIssue($owner, $repo, $issueNumber);

        // Auto-assign based on labels
        if ($config['auto_assign'] ?? false) {
            foreach ($issue->labels as $label) {
                if (isset($config['label_assignees'][$label->name])) {
                    $this->assignIssue($owner, $repo, $issueNumber, $config['label_assignees'][$label->name]);
                    break;
                }
            }
        }

        // Add priority labels based on keywords
        if ($config['auto_priority'] ?? false) {
            $title = strtolower($issue->title);
            $body = strtolower($issue->body ?? '');

            if (str_contains($title, 'urgent') || str_contains($body, 'urgent')) {
                $this->addLabel($owner, $repo, $issueNumber, 'priority:high');
            } elseif (str_contains($title, 'bug') || str_contains($body, 'bug')) {
                $this->addLabel($owner, $repo, $issueNumber, 'type:bug');
            }
        }
    }

    /**
     * Bulk operations across multiple issues
     */
    public function bulkUpdateIssues(string $owner, string $repo, array $issueNumbers, array $updates): void
    {
        foreach ($issueNumbers as $issueNumber) {
            if (isset($updates['labels'])) {
                $this->replaceAllLabels($owner, $repo, $issueNumber, $updates['labels']);
            }

            if (isset($updates['assignees'])) {
                $this->addAssignees($owner, $repo, $issueNumber, $updates['assignees']);
            }

            if (isset($updates['state'])) {
                if ($updates['state'] === 'closed') {
                    $this->closeIssue($owner, $repo, $issueNumber);
                } elseif ($updates['state'] === 'open') {
                    $this->reopenIssue($owner, $repo, $issueNumber);
                }
            }
        }
    }
}
