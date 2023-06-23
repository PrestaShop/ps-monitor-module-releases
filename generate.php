<?php

$timeStart = microtime(true);
require_once __DIR__ . '/vendor/autoload.php';

// Get token from parameters, CLI or GET
$token = null;
if (isset($argc) && !empty($argv[1])) {
    $separator = "\n";
    echo 'Using CLI token' . $separator;
    $token = $argv[1];
}
if (!empty($_GET['token'])) {
    $separator = "<br/>";
    echo 'Using GET token' . $separator;
    $token = $_GET['token'];
}
if (empty($token)) {
    die('Please pass a GitHub token as argument in the CLI, or using "token" get parameter.');
}

// Initialize github client and login
$client = new \Github\Client();
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);
echo 'Login successful' . $separator;

$branchManager = new \App\PrestaShopModulesReleaseMonitor\BranchManager($client);

function getModules($client): array
{
    // Modules that are present in the repo, but we want to ignore
    $moduleBlacklist = [
        'gamification',
        'ps_emailsmanager',
        'ps_buybuttonlite',
    ];

    $contents = $client->api('repo')->contents()->show('PrestaShop', 'PrestaShop-modules');
    $modules = [];
    foreach ($contents as $content) {
        if (!empty($content['download_url'])) {
            continue;
        }
        if (in_array($content['name'], $moduleBlacklist)) {
            continue;
        }
        $modules[] = $content['name'];
    }

    return $modules;
}

function getClassByNbCommitsAhead(int $nbCommitsAhead): string
{
    switch ($nbCommitsAhead) {
        case ($nbCommitsAhead == 0):
            $trClass = 'success';
            break;
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

echo 'Fetching list of modules' . $separator;
$modulesToProcess = getModules($client);
echo 'Module list initialized, found ' . count($modulesToProcess) . ' modules' . $separator;
$modulesToProcess = [
    'productcomments',
    'ps_linklist',
];

// Data we will use to render an overview
$notifications = [
    'not_published' => [],
    'unclosed_milestone' => [],
    'no_next_milestone' => [],
    'old_milestones' => [],
    'multiple_milestones' => [],
    'version_mismatch' => [],
    'wrong_version' => [],
    'missing_zip' => [],
];

$template = file_get_contents(__DIR__.'/src/template.tpl');
$tableRows = [];
$i = 1;

// Let's go through all prestashop modules and process data we need
foreach ($modulesToProcess as $k => $moduleName) {
    echo 'Processing ' . $moduleName . ' (' . ($k + 1) . '/' . count($modulesToProcess) . ')' . $separator;

    // Get data about repository
    $data = $branchManager->getReleaseData($moduleName);

    // Process info about last release
    $lastReleaseInformation = [];
    if (!empty($data['latestRelease'])) {
        $lastReleaseInformation[] = 'Name: <a href="' . $data['latestRelease']['url'] . '">' . $data['latestRelease']['name'] . '</a>';
        $lastReleaseInformation[] = 'Tag: ' . $data['latestRelease']['tag'];
        if (empty($data['latestRelease']['date_published'])) {
            $lastReleaseInformation[] = '<strong style="color:red;">Release not published yet!</strong>';
            $notifications['not_published'][] = $moduleName;
        }
        if ($data['latestRelease']['built_zip'] === false) {
            $lastReleaseInformation[] = '<strong style="color:red;">Release is missing built ZIP!</strong>';
            $notifications['missing_zip'][] = $moduleName;
        }
    } else {
        $lastReleaseInformation[] = 'No release yet';
    }

    // Add info about milestones to last release
    $lastMilestone = '';
    if (!empty($data['milestoneInformation']['last'])) {
        $lastMilestone = 'Milestone: <a href="' . $data['milestoneInformation']['last'][0]['url'] . '">
        ' . $data['milestoneInformation']['last'][0]['title'] . '</a>';
        if ($data['milestoneInformation']['last'][0]['state'] == 'open') {
            $lastMilestone .= ', <strong style="color:red;">NOT CLOSED!</strong>';
            $notifications['unclosed_milestone'][] = $moduleName;
        }
    } else {
        $lastMilestone = 'Not found';
    }
    $lastReleaseInformation[] = $lastMilestone;

    // Last release date
    $lastReleaseDate = (!empty($data['latestRelease']) ? $data['latestRelease']['date'] : 'NA');

    // Needs release?
    $needsRelease = ($data['ahead'] > 0 ? 'YES' : 'NO');

    // Next release information
    $nextReleaseInformation = [];
    if (!empty($data['pullRequest'])) {
        $nextReleaseInformation[] =
            'PR: <a href="'. $data['pullRequest']['link'] . '">
            #' . $data['pullRequest']['number'] . ' - ' . $data['pullRequest']['title'] .
            '</a>';
        if ($data['pullRequest']['waitingForQa']) {
            $nextReleaseInformation[] = '<strong style="color:green;">Status: Waiting for QA</strong>';
        } else {
            $nextReleaseInformation[] = '<strong style="color:red;">Status: Needs action</strong>';
        }
        if (!empty($data['pullRequest']['assignee'])) {
            $nextReleaseInformation[] = 'Assignee: ' . $data['pullRequest']['assignee'];
        }
    }

    // Add info about next milestones
    if (!empty($data['milestoneInformation']['next'])) {
        $tmp = [];
        foreach ($data['milestoneInformation']['next'] as $m) {
            $tmp[] = '<a href="' . $m['url'] . '">' . $m['title'] . '</a>';
        }
        $nextReleaseInformation[] = 'Milestone(s): ' . implode(", ", $tmp);

        // If there are multiple milestones
        if (count($data['milestoneInformation']['next']) > 1) {
            $nextReleaseInformation[] = '<strong style="color:red;">Multiple milestones open.</strong>';
            $notifications['multiple_milestones'][] = $moduleName;
        }
    } else {
        $nextReleaseInformation[] = '<strong style="color:red;">Milestone not found!</strong>';
        $notifications['no_next_milestone'][] = $moduleName;
    }

    // Info about old milestones
    if (!empty($data['milestoneInformation']['old'])) {
        $notifications['old_milestones'][] = $moduleName;
    }

    // Add info about versions
    $versions = [];
    if (!empty($data['moduleFileVersion'])) {
        $versions[] = 'PHP - ' . $data['moduleFileVersion'];
    }
    if (!empty($data['configFileVersion'])) {
        $versions[] = 'XML - ' . $data['configFileVersion'];
    }
    $nextReleaseInformation[] = 'Versions: ' . implode(', ', $versions);

    // Check if versions match
    if ($data['moduleFileVersion'] != $data['configFileVersion']) {
        $nextReleaseInformation[] = '<strong style="color:red;">Version mismatch!</strong>';
        $notifications['version_mismatch'][] = $moduleName;
    }

    // Check if versions match any old or new milestone
    if (!empty($data['moduleFileVersion'])) {
        $matchesMilestone = false;
        if (!empty($data['milestoneInformation']['last'])) {
            foreach ($data['milestoneInformation']['last'] as $m) {
                if ($m['title'] == $data['moduleFileVersion']) {
                    $matchesMilestone = true;
                }
            }
        }
        if (!empty($data['milestoneInformation']['next'])) {
            foreach ($data['milestoneInformation']['next'] as $m) {
                if ($m['title'] == $data['moduleFileVersion']) {
                    $matchesMilestone = true;
                }
            }
        }
        if ($matchesMilestone === false) {
            $nextReleaseInformation[] = '<strong style="color:red;">Wrong version!</strong>';
            $notifications['wrong_version'][] = $moduleName;
        }
    }

    // Render the table row
    $tableRows[] = [
        'html' => '<tr class="' . getClassByNbCommitsAhead($data['ahead']) . '">
            <th scope="row">'.$i.'</th>
            <td><a href="https://github.com/prestashop/' . $moduleName . '">' . $moduleName . '</a></td>
            <td>' . $needsRelease . '</td>
            <td>' . $data['ahead'] . '</td>
            <td>' . $lastReleaseDate . '</td>
            <td>' . implode('<br/>', $lastReleaseInformation) . '</td>
            <td>' . implode('<br/>', $nextReleaseInformation) . '</td>
        </tr>',
        'ahead' => $data['ahead'],
    ];

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

// Process notifications
$notifications_html = '';
if (!empty($notifications['version_mismatch'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['version_mismatch']) . '</strong> have different module versions in the module file and config.xml, it\'s required to unify it.
    </div>';
}
if (!empty($notifications['wrong_version'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Versions of modules <strong>' . implode(', ', $notifications['wrong_version']) . '</strong> don\'t match the last or any current open milestone.
    </div>';
}
if (!empty($notifications['missing_zip'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['missing_zip']) . '</strong> don\'t have a proper built ZIP attached.
    </div>';
}
if (!empty($notifications['not_published'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['not_published']) . '</strong> have releases created, but not published.
    </div>';
}
if (!empty($notifications['unclosed_milestone'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['unclosed_milestone']) . '</strong> have unclosed milestones, close them. Check that there were no PRs added to these milestones after release.
    </div>';
}
if (!empty($notifications['no_next_milestone'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['no_next_milestone']) . '</strong> don\'t have next milestones. You should create them.
    </div>';
}
if (!empty($notifications['old_milestones'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['old_milestones']) . '</strong> have some old unclosed milestones.
    </div>';
}
if (!empty($notifications['multiple_milestones'])) {
    $notifications_html .= '<div class="alert alert-warning" role="alert">
        Modules <strong>' . implode(', ', $notifications['multiple_milestones']) . '</strong> have multiple milestones. This should not be the case in case of modules repositories.
    </div>';
}

echo 'Rendering finished, saving to <a href="docs/index.html">index.html</a>' . $separator;

file_put_contents(
    __DIR__ . '/docs/index.html',
    str_replace(
        [
            '{%%tableBody%%}',
            '{%%latestUpdateDate%%}',
            '{%%notifications%%}',
        ],
        [
            implode('', $tableContent),
            date('l, j F Y H:i'),
            $notifications_html,
        ],
        $template
    )
);

echo 'Generated in ' . (microtime(true) - $timeStart) . ' seconds' . $separator;
die();
