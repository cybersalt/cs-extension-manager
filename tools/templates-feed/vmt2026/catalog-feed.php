<?php
/**
 * vmt2026: the template catalog as JSON, for the Cybersalt Extension Manager's Templates screen.
 *
 *   https://www.virtuemarttemplates.net/?tmpl=catalog-feed
 *
 * Read nightly by green:~greensudo/csem-templates/build.php, which merges it with Energize Themes'
 * feed into https://www.cybersalt.com/images/csextensionmanager/templates.json. Same data as the home
 * catalog (VmtCatalog::products()), public fields only; Joomla 3-only templates are left out.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/helper/catalog.php';

$app  = Factory::getApplication();
$db   = Factory::getDbo();
$root = rtrim(Uri::root(), '/');
$abs  = static fn (string $u): string => ($u === '' || preg_match('#^https?://#i', $u)) ? $u : $root . '/' . ltrim($u, '/');

$types = [];
foreach (VmtCatalog::topics() as $t) {
    if ($t->group === 'shop') {
        $types[(string) $t->title] = ['key' => (string) $t->alias, 'title' => (string) $t->title, 'url' => $abs(VmtCatalog::topicUrl((int) $t->id))];
    }
}

$items = array_filter(VmtCatalog::products(), static fn (array $it): bool => !$it['legacy_j3']);

// The one-line description: the product page's "tagline" field, else the Product description in the article's
// core schema.org row (what Google reads), else the article's meta description.
$tagline = [];
$schema  = [];
$meta    = [];
if ($items) {
    $ids = array_map('intval', array_keys($items));
    $q   = $db->getQuery(true)
        ->select([$db->quoteName('v.item_id'), $db->quoteName('v.value')])
        ->from($db->quoteName('#__fields_values', 'v'))
        ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON f.id = v.field_id')
        ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
        ->where($db->quoteName('f.name') . ' = ' . $db->quote('tagline'))
        ->whereIn($db->quoteName('v.item_id'), $ids);
    $tagline = $db->setQuery($q)->loadAssocList('item_id', 'value');
    try {
        $q = $db->getQuery(true)
            ->select($db->quoteName(['itemId', 'schema']))
            ->from($db->quoteName('#__schemaorg'))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'))
            ->whereIn($db->quoteName('itemId'), $ids);
        foreach ($db->setQuery($q)->loadObjectList() as $r) {
            $s = json_decode((string) $r->schema, true);
            if (is_array($s) && !empty($s['description']) && is_string($s['description'])) {
                $schema[(int) $r->itemId] = $s['description'];
            }
        }
    } catch (\Throwable $e) {
        // No schemaorg table: the meta description fills in.
    }
    $q = $db->getQuery(true)
        ->select($db->quoteName(['id', 'metadesc']))
        ->from($db->quoteName('#__content'))
        ->whereIn($db->quoteName('id'), $ids);
    $meta = $db->setQuery($q)->loadAssocList('id', 'metadesc');
}

$templates = [];
foreach ($items as $id => $it) {
    $templates[] = [
        'name'     => $it['name'],
        'url'      => $abs($it['url']),
        'demo_url' => $it['demo'],
        'image'    => $abs($it['img']),
        'tagline'  => trim((string) ($tagline[$id] ?? '')) ?: trim((string) ($schema[$id] ?? '')) ?: trim((string) ($meta[$id] ?? '')),
        'joomla'   => ['5'],
        'shop'     => 'VirtueMart',
        'type'     => $types[$it['shop_group']]['key'] ?? '',
        'released' => $it['released'],
    ];
}

$app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
$app->setHeader('X-Robots-Tag', 'noindex', true);

echo json_encode(
    ['site' => 'vmt', 'generated' => gmdate('c'), 'types' => array_values($types), 'templates' => $templates],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
