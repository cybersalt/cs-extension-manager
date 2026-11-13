<?php
/**
 * et2026: the template catalog as JSON, for the Cybersalt Extension Manager's Templates screen.
 *
 *   https://www.energizethemes.com/?tmpl=catalog-feed
 *
 * Read nightly by green:~greensudo/csem-templates/build.php, which merges it with VirtueMart Templates'
 * feed into https://www.cybersalt.com/images/csextensionmanager/templates.json. Same data as the home
 * wall (EtCatalog::available()), public fields only: no prices, plans or holds.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/helper/catalog.php';

$app  = Factory::getApplication();
$root = rtrim(Uri::root(), '/');
$abs  = static fn (string $u): string => ($u === '' || preg_match('#^https?://#i', $u)) ? $u : $root . '/' . ltrim($u, '/');

$types = [];
foreach (EtCatalog::typeTopics() as $id => $t) {
    $types[(int) $id] = ['key' => (string) $t->alias, 'title' => (string) $t->title, 'url' => $abs(EtCatalog::typeUrl($t))];
}

$templates = [];
foreach (EtCatalog::available() as $it) {
    // Joomla versions: every cart edition's, or the release's own for templates without a shop.
    $joomla = array_values(array_unique(array_filter(array_map(static fn ($e) => (string) $e['joomla'], $it['editions']))));
    if (!$joomla) {
        $joomla = ['j6' => ['6'], 'j5' => ['5']][$it['j_key']] ?? [];
    }
    rsort($joomla);

    $templates[] = [
        'name'     => $it['name'],
        'url'      => $abs($it['url']),
        'demo_url' => $it['demo'],
        'image'    => $abs($it['img']),
        'tagline'  => $it['for'] !== '' ? $it['for'] : $it['tagline'],
        'joomla'   => $joomla,
        'shop'     => implode(' + ', $it['cart_labels']),
        'type'     => $types[$it['type_id']]['key'] ?? '',
        'released' => $it['released'],
    ];
}

$app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
$app->setHeader('X-Robots-Tag', 'noindex', true);

echo json_encode(
    ['site' => 'et', 'generated' => gmdate('c'), 'types' => array_values($types), 'templates' => $templates],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
