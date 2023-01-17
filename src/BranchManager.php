<?php

namespace App\PrestaShopModulesReleaseMonitor;

use DateTime;
use Exception;
use Github\Client;

class BranchManager
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $repositoryName
     * @return array|null Repository data
     *
     * Inspired by https://github.com/PrestaShop/presthubot ModuleChecker::findReleaseStatus()
     */
    public function getReleaseData($repositoryName)
    {
        // Try to get data about latest release of this module
        $latestRelease = null;
        try {
            $release = $this->client->api('repo')->releases()->latest(
                'prestashop',
                $repositoryName,
            );
            if (!empty($release)) {
                $latestRelease = [
                    'date' => (new DateTime($release['created_at']))->format('Y-m-d H:i:s'),
                    'name' => $release['name'],
                    'tag' => $release['tag_name'],
                    'url' => $release['html_url'],
                    'date_published' => (new DateTime($release['published_at']))->format('Y-m-d H:i:s'),
                ];
            }
        } catch (Exception $e) {
        }

        // Milestone information
        $milestoneInformation = [
            'last' => [],
            'next' => [],
            'old' => [],
        ];

        // Get all milestones from this repository
        $milestones = $this->client->api('issue')->milestones()->all(
            'prestashop',
            $repositoryName,
            ['state' => 'all']
        );

        // Try to fetch milestone for current release
        if (!empty($latestRelease)) {
            // Get milestone name to search for
            $milestoneVersion = preg_replace('/[^0-9.]/', '', $latestRelease['name']);
            foreach ($milestones as $milestone) {
                // If we have a milestone with the same name as last release tag
                if ($milestone['title'] == $milestoneVersion) {
                    $milestoneInformation['last'][] = [
                        'title' => $milestone['title'],
                        'state' => $milestone['state'],
                        'url' => $milestone['html_url'],
                    ];

                // Try to find next milestone higher than the last released version
                } elseif (version_compare($milestone['title'], $milestoneVersion, '>') && $milestone['state'] == 'open') {
                    $milestoneInformation['next'][] = [
                        'title' => $milestone['title'],
                        'state' => $milestone['state'],
                        'url' => $milestone['html_url'],
                    ];

                // We list some old milestones that are still open
                } elseif ($milestone['state'] == 'open') {
                    $milestoneInformation['old'][] = [
                        'title' => $milestone['title'],
                        'state' => $milestone['state'],
                        'url' => $milestone['html_url'],
                    ];
                }
            }
        // If module was not released yet
        } else {
            foreach ($milestones as $milestone) {
                if ($milestone['state'] == 'open') {
                    $milestoneInformation['next'][] = [
                        'title' => $milestone['title'],
                        'state' => $milestone['state'],
                        'url' => $milestone['html_url'],
                    ];
                }
            }
        }

        // Get branch data, we need to know how many commits it's ahead etc.
        $references = $this->client->api('gitData')->references()->branches('prestashop', $repositoryName);

        $devBranchData = $masterBranchData = [];
        foreach ($references as $branchID => $branchData) {
            $branchName = $branchData['ref'];

            if ($branchName === 'refs/heads/dev') {
                $devBranchData = $branchData;
            }
            if ($branchName === 'refs/heads/develop') {
                $devBranchData = $branchData;
            }
            if ($branchName === 'refs/heads/master') {
                $masterBranchData = $branchData;
                $usedBranch = 'master';
            }
            if ($branchName === 'refs/heads/main') {
                $masterBranchData = $branchData;
                $usedBranch = 'main';
            }
        }

        $devLastCommitSha = $devBranchData['object']['sha'];
        $masterLastCommitSha = $masterBranchData['object']['sha'];

        $comparison = $this->client->api('repo')->commits()->compare(
            'prestashop',
            $repositoryName,
            $masterLastCommitSha,
            $devLastCommitSha
        );

        // Get next release PR information
        $openPullRequests = $this->client->api('pull_request')->all('prestashop', $repositoryName, array('state' => 'open', 'base' => $usedBranch));
        if (!empty($openPullRequests)) {
            // QA assigned
            $assignee = isset($openPullRequests[0]['assignee']['login']) ? $openPullRequests[0]['assignee']['login'] : '';

            // Waiting for QA?
            $waitingForQa = false;
            foreach ($openPullRequests[0]['labels'] as $label) {
                if ($label['name'] == 'waiting for QA') {
                    $waitingForQa = true;
                }
            }

            $openPullRequest = [
                'link' => $openPullRequests[0]['html_url'],
                'number' => $openPullRequests[0]['number'],
                'title' => $openPullRequests[0]['title'],
                'assignee' => $assignee,
                'waitingForQa' => $waitingForQa,
            ];
        } else {
            $openPullRequest = false;
        }

        return [
            'behind' => $comparison['behind_by'],
            'ahead' => $comparison['ahead_by'],
            'latestRelease' => $latestRelease,
            'pullRequest' => $openPullRequest,
            'milestoneInformation' => $milestoneInformation,
            'moduleFileVersion' => $this->getModuleFileVersion($repositoryName),
            'configFileVersion' => $this->getConfigFileVersion($repositoryName),
        ];
    }

    public function getModuleFileVersion($repositoryName)
    {
        $data = @file_get_contents('https://raw.githubusercontent.com/PrestaShop/' . $repositoryName . '/dev/' . $repositoryName . '.php');
        if (empty($data)) {
            return null;
        }

        $data = explode('$this->version', $data)[1];
        $data = explode(';', $data)[0];
        $data = preg_replace('/[^0-9.]/', '', $data);
        if (empty($data)) {
            return null;
        }

        return $data;
    }

    public function getConfigFileVersion($repositoryName)
    {
        $data = @file_get_contents('https://raw.githubusercontent.com/PrestaShop/' . $repositoryName . '/dev/config.xml');
        if (empty($data)) {
            return null;
        }

        $data = explode('<version>', $data)[1];
        $data = explode('</version>', $data)[0];
        $data = preg_replace('/[^0-9.]/', '', $data);
        if (empty($data)) {
            return null;
        }

        return $data;
    }
}
