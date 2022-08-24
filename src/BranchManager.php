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
     * @return string|null null if failed to find base branch
     *
     * Inspired by https://github.com/PrestaShop/presthubot ModuleChecker::findReleaseStatus()
     */
    public function getReleaseData($repositoryName)
    {
        try {
            $release = $this->client->api('repo')->releases()->latest('prestashop', $repositoryName);
            $date = new DateTime($release['created_at']);
            $releaseDate = $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $releaseDate = 'NA';
        }

        $references = $this->client->api('gitData')->references()->branches('prestashop', $repositoryName);

        $devBranchData = $masterBranchData = [];
        foreach ($references as $branchID => $branchData) {
            $branchName = $branchData['ref'];

            if ($branchName === 'refs/heads/dev') {
                $devBranchData = $branchData;
                $devBranchUsed = 'dev';
            }
            if ($branchName === 'refs/heads/develop') {
                $devBranchData = $branchData;
                $devBranchUsed = 'develop';
            }
            if ($branchName === 'refs/heads/master') {
                $masterBranchData = $branchData;
                $mainBranchUsed = 'master';
            }
            if ($branchName === 'refs/heads/main') {
                $masterBranchData = $branchData;
                $mainBranchUsed = 'main';
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

        $openPullRequests = $this->client->api('pull_request')->all('prestashop', $repositoryName, array('state' => 'open', 'base' => $mainBranchUsed));

        if ($openPullRequests) {
            $assignee = isset($openPullRequests[0]['assignee']['login']) ? $openPullRequests[0]['assignee']['login'] : '';
            $openPullRequest = ['link' => $openPullRequests[0]['html_url'], 'number' => $openPullRequests[0]['number'], 'assignee' => $assignee];
        } else {
            $openPullRequest = false;
        }

        // Do not count dependabot commits since last release
        // sleep() for rate limit see : https://docs.github.com/en/rest/search#rate-limit
        $dependabotPrs = $this->client->api('search')->issues('is:pr is:merged repo:PrestaShop/' . $repositoryName . ' author:app/dependabot created:>' . $release['created_at']);
        sleep(10);

        return [
            'behind' => $comparison['behind_by'],
            'ahead' => ($comparison['ahead_by']-$dependabotPrs['total_count']),
            'releaseDate' => $releaseDate,
            'pullRequest' => $openPullRequest,
        ];
    }
}
