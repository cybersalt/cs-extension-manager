<?php
/**
 * Builds https://www.cybersalt.com/images/csextensionmanager/templates.json, the template list the Cybersalt
 * Extension Manager shows on its Templates screen, from the two template sites' own catalogs:
 *
 *   https://www.energizethemes.com/?tmpl=catalog-feed          (templates/et2026/catalog-feed.php)
 *   https://www.virtuemarttemplates.net/?tmpl=catalog-feed     (templates/vmt2026/catalog-feed.php)
 *
 * Runs nightly from greensudo's crontab:  php ~/csem-templates/build.php  (add --dry-run to only print).
 *
 * Safety: a site whose feed fails, isn't JSON, or suddenly lists far fewer templates keeps its last good list
 * (state/<site>.json), so one bad night never empties the Templates screen. The file on cybersalt.com is only
 * replaced when the content changed. Every run appends one line to build.log.
 *
 * Types: each template's type is its site's own "Template types" (ET) / "Shop types" (VMT) tag, keyed by the
 * tag alias. Retag a template on its site and the Extension Manager follows the next night.
 */

declare(strict_types=1);

const DEST     = '/home/csaltcom/public_html/images/csextensionmanager/templates.json';
const DEST_DIR = '/home/csaltcom/public_html/images/csextensionmanager';
const UA       = 'CybersaltTemplatesFeed/1.0 (+https://www.cybersalt.com)';

// A site's count may drop by at most this share in one night before we distrust the feed.
const MAX_DROP = 0.25;

$sites = [
    'et' => [
        'feed'        => 'https://www.energizethemes.com/?tmpl=catalog-feed',
        'name'        => 'Energize Themes',
        'url'         => 'https://www.energizethemes.com/',
        'plans_url'   => 'https://www.energizethemes.com/pricing',
        'description' => 'Responsive Joomla templates for Joomla 6 and Joomla 5, including VirtueMart and HikaShop shop templates.',
    ],
    'vmt' => [
        'feed'        => 'https://www.virtuemarttemplates.net/?tmpl=catalog-feed',
        'name'        => 'VirtueMart Templates',
        'url'         => 'https://www.virtuemarttemplates.net/',
        'plans_url'   => 'https://www.virtuemarttemplates.net/all-subscriptions.html',
        'description' => 'Responsive VirtueMart shop templates for Joomla 5, each with a live demo and a Quickstart install package.',
    ],
];

$dir    = __DIR__;
$dryRun = in_array('--dry-run', $argv, true);
$notes  = [];

@mkdir($dir . '/state', 0700);

function fetch(string $url): array
{
    $ch = curl_init($url . (str_contains($url, '?') ? '&' : '?') . 't=' . time()); // past Joomla's page cache
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code !== 200) {
        return [null, 'HTTP ' . $code . ($err !== '' ? ' ' . $err : '')];
    }

    $json = json_decode($body, true);

    if (!is_array($json) || !isset($json['templates']) || !is_array($json['templates'])) {
        return [null, 'not a catalog feed'];
    }

    return [$json, null];
}

$brands = [];
$topics = [];

foreach ($sites as $key => $site) {
    $stateFile = $dir . '/state/' . $key . '.json';
    $previous  = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : null;
    [$feed, $error] = fetch($site['feed']);

    $templates = [];

    foreach ((array) ($feed['templates'] ?? []) as $t) {
        if (!is_array($t) || ($t['name'] ?? '') === '' || ($t['url'] ?? '') === '') {
            continue;
        }

        $templates[] = [
            'name'     => (string) $t['name'],
            'url'      => (string) $t['url'],
            'demo_url' => (string) ($t['demo_url'] ?? ''),
            'image'    => (string) ($t['image'] ?? ''),
            'tagline'  => (string) ($t['tagline'] ?? ''),
            'joomla'   => array_values(array_map('strval', (array) ($t['joomla'] ?? []))),
            'shop'     => (string) ($t['shop'] ?? ''),
            'tags'     => ($t['type'] ?? '') !== '' ? [(string) $t['type']] : [],
        ];
    }

    $types = [];

    foreach ((array) ($feed['types'] ?? []) as $type) {
        if (is_array($type) && ($type['key'] ?? '') !== '' && ($type['title'] ?? '') !== '') {
            $types[] = ['key' => (string) $type['key'], 'title' => (string) $type['title'], 'url' => (string) ($type['url'] ?? '')];
        }
    }

    $prevCount = is_array($previous) ? count($previous['templates']) : 0;

    if ($error === null && $templates === []) {
        $error = 'no templates';
    }

    if ($error === null && $prevCount > 0 && count($templates) < $prevCount * (1 - MAX_DROP)) {
        $error = sprintf('only %d templates (was %d)', count($templates), $prevCount);
    }

    if ($error !== null) {
        if (!is_array($previous)) {
            $notes[] = $key . ': ' . $error . ', no earlier list, left out';
            continue;
        }

        $notes[]   = $key . ': ' . $error . ', kept last good list (' . $prevCount . ')';
        $templates = $previous['templates'];
        $types     = $previous['types'];
    } else {
        $notes[] = $key . ': ' . count($templates);

        if (!$dryRun) {
            file_put_contents($stateFile, json_encode(['templates' => $templates, 'types' => $types], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    foreach ($types as $type) {
        $topics[$type['key']] ??= $type['title'];
    }

    $brands[] = [
        'key'         => $key,
        'name'        => $site['name'],
        'url'         => $site['url'],
        'plans_url'   => $site['plans_url'],
        'description' => $site['description'],
        'types'       => $types,
        'templates'   => $templates,
    ];
}

$log = static function (string $line) use ($dir): void {
    file_put_contents($dir . '/build.log', gmdate('Y-m-d H:i:s') . 'Z ' . $line . "\n", FILE_APPEND);
};

if ($brands === []) {
    $log('NOTHING TO PUBLISH; ' . implode('; ', $notes));
    fwrite(STDERR, "No site answered and there is no saved list.\n");
    exit(1);
}

// What we last published (greensudo can't read inside csaltcom's home, so keep our own copy).
$content   = ['topics' => $topics, 'brands' => $brands];
$published = $dir . '/state/published.json';
$current   = is_file($published) ? json_decode((string) file_get_contents($published), true) : null;
$same      = $current === $content;
$json    = json_encode(['updated' => gmdate('Y-m-d'), 'source' => 'green:~greensudo/csem-templates/build.php'] + $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($dryRun) {
    echo $json, "\n", ($same ? 'unchanged' : 'would publish'), '; ', implode('; ', $notes), "\n";
    exit(0);
}

if ($same) {
    $log('unchanged; ' . implode('; ', $notes));
    exit(0);
}

// greensudo may copy /tmp/* into csaltcom's images folder and chown it (sudoers), nothing wider.
$tmpDir = '/tmp/csem-templates-' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0755);
file_put_contents($tmpDir . '/templates.json', $json);
chmod($tmpDir . '/templates.json', 0644);

$ok = true;
exec('sudo -n /bin/cp -rp ' . escapeshellarg($tmpDir . '/templates.json') . ' ' . escapeshellarg(DEST) . ' 2>&1', $out, $rc);
$ok = $ok && $rc === 0;
exec('sudo -n /bin/chown -R csaltcom:csaltcom ' . escapeshellarg(DEST_DIR) . ' 2>&1', $out, $rc2);
$ok = $ok && $rc2 === 0;

unlink($tmpDir . '/templates.json');
rmdir($tmpDir);

if ($ok) {
    file_put_contents($published, json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$log(($ok ? 'PUBLISHED ' : 'PUBLISH FAILED ' . implode(' ', $out) . ' ') . strlen($json) . ' bytes; ' . implode('; ', $notes));
exit($ok ? 0 : 1);
