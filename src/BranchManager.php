<?php


namespace Matks\PrestaShopModulesReleaseMonitor;

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
     */
    public function getReleaseData($repositoryName)
    {
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

        return [
            'behind' => $comparison['behind_by'],
            'ahead' => $comparison['ahead_by'],
        ];
    }


}
