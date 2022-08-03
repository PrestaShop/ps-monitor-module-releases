<?php

if ($argc < 2) {
    die('Please pass a GitHub token as argument');
}

$timeStart = microtime(true);

require_once __DIR__ . '/vendor/autoload.php';

$client = new \Github\Client();
$token = $argv[1];
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);

$branchManager = new \App\PrestaShopModulesReleaseMonitor\BranchManager($client);

function getModules($client): array
{
    $contents = $client->api('repo')->contents()->show('PrestaShop', 'PrestaShop-modules');

    $modules = [];
    foreach ($contents as $content) {
        if (!empty($content['download_url'])) {
            continue;
        }
        $modules[] = $content['name'];
    }

    return $modules;
}

function getClassByNbCommitsAhead(int $nbCommitsAhead): string
{
    switch ($nbCommitsAhead) {
        case ($nbCommitsAhead > 0 && $nbCommitsAhead <= 25):
            $trClass = 'light';
            break;
        case ($nbCommitsAhead > 25 && $nbCommitsAhead <= 100):
            $trClass = 'warning';
            break;
        case ($nbCommitsAhead > 100):
            $trClass = 'danger';
            break;
        default:
            $trClass = 'default';
            break;
    }

    return 'table-' . $trClass;
}

$modulesToProcess = getModules($client);
$template = file_get_contents(__DIR__.'/src/template.tpl');

$tableRows = [];
$i = 1;


foreach ($modulesToProcess as $moduleToProcess) {
    $repositoryName = $moduleToProcess;
    $data = $branchManager->getReleaseData($repositoryName);
    $nbCommitsAhead = $data['ahead'];

    $trClass = getClassByNbCommitsAhead($nbCommitsAhead);

    if ($nbCommitsAhead == 0) {
        $tableRows[] = [
            'html' => '<tr class="table-success">
              <th scope="row">'.$i.'</th>
              <td><a href="https://github.com/prestashop/'.$repositoryName.'">'.$repositoryName.'</a></td>
              <td>NO</td>
              <td>0</td>
            </tr>',
            'ahead' => 0,
        ];
    } else {
        $tableRows[] = [
            'html' =>'<tr class="'.$trClass.'">
              <th scope="row">'.$i.'</th>
              <td><a href="https://github.com/prestashop/'.$repositoryName.'">'.$repositoryName.'</a></td>
              <td>YES</td>
              <td>'.$data['ahead'].'</td>
            </tr>',
            'ahead' => $data['ahead'],
        ];
    }

    uasort($tableRows, function ($a, $b) {
        if ($a['ahead'] == $b['ahead']) {
            return 0;
        }

        return ($a['ahead'] > $b['ahead']) ? -1 : 1;
    });

    $tableContent = array_map(function ($row) {
        return $row['html'];
    }, $tableRows);

    $i++;
}

file_put_contents(
    __DIR__ . '/docs/index.html',
    str_replace(
        [
            '{%%placeholder%%}',
            '{%%latestUpdateDate%%}',
        ],
        [
            implode('', $tableContent),
            date('l, j F Y H:i'),
        ],
        $template
    )
);

die('Generated in ' . (microtime(true) - $timeStart) . ' seconds');
