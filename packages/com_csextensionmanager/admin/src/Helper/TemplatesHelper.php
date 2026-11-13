<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * The Templates tab: Energize Themes and VirtueMart Templates.
 *
 * Read from `templates.json` on cybersalt.com, so a new template shows up
 * without a manager release; the bundled `admin/catalog/templates.json` is
 * used until that file answers. Nothing here is installed: every link opens
 * a brand site, and only links to Cybersalt's own sites are kept.
 *
 * @since  0.1.0
 */
final class TemplatesHelper
{
    /**
     * Path of the feed on the store host.
     *
     * @var    string
     * @since  0.1.0
     */
    public const FEED_PATH = '/images/csextensionmanager/templates.json';

    /**
     * Hosts (and their subdomains) a link or image may point to.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const LINK_HOSTS = ['energizethemes.com', 'virtuemarttemplates.net', 'cybersalt.com'];

    /**
     * Cache row key. Bump the suffix when the shape changes.
     *
     * @var    string
     * @since  0.1.0
     */
    private const CACHE_KEY = 'templates.v2';

    private const MAX_BRANDS    = 6;
    private const MAX_TEMPLATES = 200;
    private const MAX_TOPICS    = 30;

    /**
     * @var    array<string, mixed>|null
     * @since  0.1.0
     */
    private static ?array $memo = null;

    /**
     * The brands and their templates.
     *
     * @param   boolean  $refresh  Ignore the cache.
     *
     * @return  array{brands: array<int, array<string, mixed>>, topics: array<string, string>, fetched: int, source: string, error: string|null}
     *
     * @since   0.1.0
     */
    public static function get(bool $refresh = false): array
    {
        if (!$refresh && self::$memo !== null) {
            return self::$memo;
        }

        $cached = self::getCached();
        $hours  = max(1, min(168, (int) ParamsHelper::get()->get('cache_hours', 6)));

        if (!$refresh && $cached !== null && time() - $cached['fetched'] < $hours * 3600) {
            return self::$memo = $cached;
        }

        $result = StoreClient::getJson(StoreClient::storeRoot() . self::FEED_PATH);
        $parsed = $result['json'] !== null ? self::parse($result['json']) : ['brands' => [], 'topics' => []];

        if ($parsed['brands'] !== []) {
            $fresh = $parsed + ['fetched' => time(), 'source' => 'store', 'error' => null];
            self::store($fresh);

            return self::$memo = $fresh;
        }

        // Feed missing or unusable: last good copy, else the bundled one.
        // Saved with a fresh time so the store is asked again only when the
        // cache runs out, not on every page load.
        $fallback = ($cached !== null ? ['brands' => $cached['brands'], 'topics' => $cached['topics']] : self::bundled()) + [
            'fetched' => time(),
            'source'  => $cached !== null ? 'cache' : 'bundled',
            'error'   => $result['error'] ?? 'No templates in the feed.',
        ];

        if ($fallback['brands'] !== []) {
            self::store($fallback);
        }

        return self::$memo = $fallback;
    }

    /**
     * Brands plus the topic labels the templates use.
     *
     * @param   array<string, mixed>  $json  Decoded feed.
     *
     * @return  array{brands: array<int, array<string, mixed>>, topics: array<string, string>}
     *
     * @since   0.1.0
     */
    public static function parse(array $json): array
    {
        $brands = self::sanitise($json);
        $used   = [];

        foreach ($brands as $brand) {
            foreach ($brand['templates'] as $tpl) {
                $used += array_fill_keys($tpl['tags'], true);
            }
        }

        // Feed order, only topics some template has, never an empty label.
        $topics = [];

        foreach ((array) ($json['topics'] ?? []) as $slug => $label) {
            $slug  = self::slug($slug);
            $label = self::text($label, 40);

            if ($slug !== '' && $label !== '' && isset($used[$slug]) && \count($topics) < self::MAX_TOPICS) {
                $topics[$slug] = $label;
            }
        }

        // A tag with no label isn't shown as a filter.
        foreach ($brands as &$brand) {
            foreach ($brand['templates'] as &$tpl) {
                $tpl['tags'] = array_values(array_filter($tpl['tags'], static fn (string $t): bool => isset($topics[$t])));
            }
        }

        unset($brand, $tpl);

        return ['brands' => $brands, 'topics' => $topics];
    }

    /**
     * Number of templates across all brands.
     *
     * @param   array<int, array<string, mixed>>  $brands  Brands from get().
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public static function count(array $brands): int
    {
        return array_sum(array_map(static fn (array $b): int => \count($b['templates']), $brands));
    }

    /**
     * Every template in one list, each with its brand's key and name, sorted by name.
     *
     * @param   array<int, array<string, mixed>>  $brands  Brands from get().
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   0.1.0
     */
    public static function flatten(array $brands): array
    {
        $all = [];

        foreach ($brands as $brand) {
            foreach ($brand['templates'] as $tpl) {
                $all[] = $tpl + ['brand' => $brand['key'], 'brand_name' => $brand['name']];
            }
        }

        usort($all, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']) ?: strcmp($a['brand'], $b['brand']));

        return $all;
    }

    /**
     * Keep only well-formed brands and templates whose links stay on
     * Cybersalt's sites.
     *
     * @param   array<string, mixed>  $json  Decoded feed.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   0.1.0
     */
    public static function sanitise(array $json): array
    {
        $brands = [];

        foreach (\array_slice((array) ($json['brands'] ?? []), 0, self::MAX_BRANDS) as $brand) {
            if (!\is_array($brand)) {
                continue;
            }

            $key  = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) ($brand['key'] ?? '')));
            $name = self::text($brand['name'] ?? '', 80);
            $url  = self::link($brand['url'] ?? '');

            if ($key === '' || $name === '' || $url === '') {
                continue;
            }

            $templates = [];

            foreach (\array_slice((array) ($brand['templates'] ?? []), 0, self::MAX_TEMPLATES) as $tpl) {
                if (!\is_array($tpl)) {
                    continue;
                }

                $tplName = self::text($tpl['name'] ?? '', 80);
                $tplUrl  = self::link($tpl['url'] ?? '');

                if ($tplName === '' || $tplUrl === '') {
                    continue;
                }

                $joomla = array_values(array_filter(
                    array_map(static fn ($v): string => \is_scalar($v) ? (string) $v : '', (array) ($tpl['joomla'] ?? [])),
                    static fn (string $v): bool => (bool) preg_match('/^[3-9]$/', $v)
                ));

                $tags = array_values(array_unique(array_filter(array_map(
                    static fn ($v): string => self::slug($v),
                    \array_slice((array) ($tpl['tags'] ?? []), 0, 8)
                ))));

                $templates[] = [
                    'name'     => $tplName,
                    'url'      => $tplUrl,
                    'demo_url' => self::link($tpl['demo_url'] ?? ''),
                    'image'    => self::link($tpl['image'] ?? ''),
                    'tagline'  => self::text($tpl['tagline'] ?? '', 140),
                    'shop'     => self::text($tpl['shop'] ?? '', 40),
                    'joomla'   => $joomla,
                    'tags'     => $tags,
                ];
            }

            if ($templates === []) {
                continue;
            }

            $brands[] = [
                'key'         => $key,
                'name'        => $name,
                'url'         => $url,
                'plans_url'   => self::link($brand['plans_url'] ?? ''),
                'logo'        => self::link($brand['logo'] ?? ''),
                'description' => self::text($brand['description'] ?? '', 300),
                'templates'   => $templates,
            ];
        }

        return $brands;
    }

    /**
     * An https URL on one of LINK_HOSTS (or a subdomain), else ''.
     *
     * @param   mixed  $url  Candidate.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function link($url): string
    {
        $url = trim(\is_string($url) ? $url : '');

        if ($url === '' || \strlen($url) > 500 || preg_match('/[\s\\\\<>"\']/', $url)) {
            return '';
        }

        $parts = parse_url($url);

        if (!\is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return '';
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        foreach (self::LINK_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return $url;
            }
        }

        return '';
    }

    /**
     * A topic slug: lowercase letters, digits and hyphens, else ''.
     *
     * @param   mixed  $value  Candidate.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function slug($value): string
    {
        $value = \is_scalar($value) ? strtolower((string) $value) : '';

        return preg_match('/^[a-z0-9][a-z0-9-]{0,30}$/', $value) ? $value : '';
    }

    /**
     * Plain one-line text, trimmed to $max characters.
     *
     * @param   mixed    $value  Candidate.
     * @param   integer  $max    Length limit.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function text($value, int $max): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags(\is_scalar($value) ? (string) $value : '')));

        return mb_substr($value, 0, $max);
    }

    /**
     * The copy shipped with the component.
     *
     * @return  array{brands: array<int, array<string, mixed>>, topics: array<string, string>}
     *
     * @since   0.1.0
     */
    private static function bundled(): array
    {
        $file = JPATH_ADMINISTRATOR . '/components/com_csextensionmanager/catalog/templates.json';
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return \is_array($json) ? self::parse($json) : ['brands' => [], 'topics' => []];
    }

    /**
     * @return  array{brands: array<int, array<string, mixed>>, topics: array<string, string>, fetched: int, source: string, error: string|null}|null
     *
     * @since   0.1.0
     */
    private static function getCached(): ?array
    {
        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $row = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName(['fetched', 'data']))
                    ->from($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = ' . $db->quote(self::CACHE_KEY))
            )->loadObject();
        } catch (\Throwable $e) {
            return null;
        }

        $data = $row ? json_decode((string) $row->data, true) : null;

        if (!\is_array($data) || empty($data['brands']) || !\is_array($data['brands'])) {
            return null;
        }

        return [
            'brands'  => $data['brands'],
            'topics'  => \is_array($data['topics'] ?? null) ? $data['topics'] : [],
            'fetched' => (int) (strtotime((string) $row->fetched . ' UTC') ?: 0),
            'source'  => (string) ($data['source'] ?? 'store'),
            'error'   => isset($data['error']) ? (string) $data['error'] : null,
        ];
    }

    /**
     * @param   array<string, mixed>  $result  Fresh result.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function store(array $result): void
    {
        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $key     = self::CACHE_KEY;
            $fetched = Factory::getDate($result['fetched'])->toSql();
            $data    = (string) json_encode(
                ['brands' => $result['brands'], 'topics' => $result['topics'], 'source' => $result['source'], 'error' => $result['error']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = :key')
                    ->bind(':key', $key)
            )->execute();

            $db->setQuery(
                $db->createQuery()
                    ->insert($db->quoteName('#__csextensionmanager_cache'))
                    ->columns($db->quoteName(['cache_key', 'fetched', 'data']))
                    ->values(':key, :fetched, :data')
                    ->bind(':key', $key)
                    ->bind(':fetched', $fetched)
                    ->bind(':data', $data)
            )->execute();
        } catch (\Throwable $e) {
            // Uncached just means the next page load asks the store again.
        }
    }
}
