<?php

if ($argc < 2) {
    die('Please pass a GitHub token as argument');
}

require_once __DIR__ . '/vendor/autoload.php';

$client = new \Github\Client();
$token = $argv[1];
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);

$branchManager = new \Matks\PrestaShopModulesReleaseMonitor\BranchManager($client);

$modulesToProcess = require_once __DIR__ . '/modulesList.php';
$template = file_get_contents(__DIR__.'/src/template.tpl');

$tableRows = [];

foreach ($modulesToProcess as $moduleToProcess) {
    $repositoryName = $moduleToProcess;
    $data = $branchManager->getReleaseData($repositoryName);

    if ($data['ahead'] == 0) {
        $tableRows[] = '<tr>
              <th scope="row">1</th>
              <td><a href="https://github.com/prestashop/'.$repositoryName.'">'.$repositoryName.'</a></td>
              <td>NO</td>
              <td>0</td>
            </tr>';
    } else {
        $tableRows[] = '<tr>
              <th scope="row">1</th>
              <td><a href="https://github.com/prestashop/'.$repositoryName.'">'.$repositoryName.'</a></td>
              <td>YES</td>
              <td>'.$data['ahead'].'</td>
            </tr>';
    }
}

file_put_contents(__DIR__.'/docs/index.html', str_replace('{%%placeholder%%}', implode('', $tableRows), $template));