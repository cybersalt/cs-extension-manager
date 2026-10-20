<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;

/**
 * Talks to the Cybersalt store (cs-release-manager on cybersalt.com).
 *
 * Every URL the manager fetches or installs from is built here or checked by
 * isStoreUrl(), so nothing from a request or a feed can point the installer
 * somewhere else.
 *
 * @since  0.1.0
 */
final class StoreClient
{
    public const DEFAULT_STORE = 'https://www.cybersalt.com';

    /**
     * Hosts that always count as the Cybersalt store, whatever store_url says.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const CYBERSALT_HOSTS = ['cybersalt.com', 'www.cybersalt.com'];

    /**
     * Store root (scheme + host, no trailing slash). https only.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function storeRoot(): string
    {
        $url   = trim((string) ParamsHelper::get()->get('store_url', self::DEFAULT_STORE));
        $parts = parse_url($url);

        if (!\is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return self::DEFAULT_STORE;
        }

        $root = 'https://' . strtolower((string) $parts['host']);

        if (!empty($parts['port'])) {
            $root .= ':' . (int) $parts['port'];
        }

        return $root;
    }

    /**
     * Release Manager API URL.
     *
     * @param   string                $task    API task without the `api.` prefix (updatexml, changelogxml, catalog...).
     * @param   array<string, mixed>  $query   Extra query parameters.
     * @param   string                $format  raw or json.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function apiUrl(string $task, array $query = [], string $format = 'raw'): string
    {
        $base = [
            'option' => 'com_csreleasemanager',
            'task'   => 'api.' . $task,
            'format' => $format,
        ];

        return self::storeRoot() . '/index.php?' . http_build_query($base + $query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The update feed for one element (the same URL a Cybersalt manifest declares).
     *
     * @param   string  $element  Extension element.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function updateXmlUrl(string $element): string
    {
        return self::apiUrl('updatexml', ['element' => $element]);
    }

    /**
     * @param   string  $element  Extension element.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function changelogUrl(string $element): string
    {
        return self::apiUrl('changelogxml', ['element' => $element]);
    }

    /**
     * Is this an https URL on the Cybersalt store, for a Release Manager API
     * task in $tasks (any task when null)?
     *
     * @param   string         $url    URL to check (HTML entities are decoded first).
     * @param   string[]|null  $tasks  Allowed `task` values, e.g. ['api.download'].
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function isStoreUrl(string $url, ?array $tasks = null): bool
    {
        $parts = parse_url(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5));

        if (!\is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host    = strtolower((string) ($parts['host'] ?? ''));
        $store   = strtolower((string) parse_url(self::storeRoot(), PHP_URL_HOST));
        $allowed = array_unique(array_merge(self::CYBERSALT_HOSTS, [$store]));

        if (!\in_array($host, $allowed, true)) {
            return false;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        if (($query['option'] ?? '') !== 'com_csreleasemanager') {
            return false;
        }

        return $tasks === null || \in_array((string) ($query['task'] ?? ''), $tasks, true);
    }

    /**
     * Add (or replace) one query parameter.
     *
     * @param   string  $url    URL.
     * @param   string  $name   Parameter name.
     * @param   string  $value  Value ('' removes the parameter).
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function withQueryParam(string $url, string $name, string $value): string
    {
        $url = (string) preg_replace('/([?&])' . preg_quote($name, '/') . '=[^&#]*(&|$)/', '$1', $url);
        $url = rtrim($url, '?&');

        if ($value === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . rawurlencode($name) . '=' . rawurlencode($value);
    }

    /**
     * GET one URL.
     *
     * @param   string  $url  URL (must be https).
     *
     * @return  array{code: int, body: string, error: string|null}
     *
     * @since   0.1.0
     */
    public static function get(string $url): array
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return ['code' => 0, 'body' => '', 'error' => 'Only https URLs are fetched.'];
        }

        try {
            $response = HttpFactory::getHttp(self::httpOptions())->get($url, [], self::timeout());
        } catch (\Throwable $e) {
            return ['code' => 0, 'body' => '', 'error' => $e->getMessage()];
        }

        $code = (int) $response->getStatusCode();

        return [
            'code'  => $code,
            'body'  => (string) $response->getBody(),
            'error' => $code === 200 ? null : 'HTTP ' . $code,
        ];
    }

    /**
     * GET several URLs at once (curl_multi when available, one by one
     * otherwise). Ten update feeds take about as long as one.
     *
     * @param   array<string, string>  $urls  Key => URL.
     *
     * @return  array<string, array{code: int, body: string, error: string|null}>  Same keys.
     *
     * @since   0.1.0
     */
    public static function getMany(array $urls): array
    {
        if ($urls === []) {
            return [];
        }

        if (!\function_exists('curl_multi_init') || \count($urls) === 1) {
            return array_map([self::class, 'get'], $urls);
        }

        $multi   = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $key => $url) {
            if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                $results[$key] = ['code' => 0, 'body' => '', 'error' => 'Only https URLs are fetched.'];
                continue;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => min(10, self::timeout()),
                CURLOPT_TIMEOUT        => self::timeout(),
                CURLOPT_USERAGENT      => self::userAgent(),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_ENCODING       => '',
            ]);

            $caBundle = self::caBundle();

            if ($caBundle !== '') {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }

            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {
            $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $body  = (string) curl_multi_getcontent($ch);
            $error = curl_error($ch);

            $results[$key] = [
                'code'  => $code,
                'body'  => $body,
                'error' => $code === 200 ? null : ($error !== '' ? $error : 'HTTP ' . $code),
            ];

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        // Keep the caller's key order.
        $ordered = [];

        foreach (array_keys($urls) as $key) {
            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }

    /**
     * POST a JSON body and decode the JSON answer.
     *
     * @param   string                $url      URL (must be a store URL).
     * @param   array<string, mixed>  $payload  Body.
     *
     * @return  array{code: int, json: array<string, mixed>|null, error: string|null}
     *
     * @since   0.1.0
     */
    public static function postJson(string $url, array $payload): array
    {
        if (!self::isStoreUrl($url)) {
            return ['code' => 0, 'json' => null, 'error' => 'Not a store URL.'];
        }

        try {
            $response = HttpFactory::getHttp(self::httpOptions())->post(
                $url,
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                self::timeout()
            );
        } catch (\Throwable $e) {
            return ['code' => 0, 'json' => null, 'error' => $e->getMessage()];
        }

        $json = json_decode((string) $response->getBody(), true);

        return [
            'code'  => (int) $response->getStatusCode(),
            'json'  => \is_array($json) ? $json : null,
            'error' => \is_array($json) ? null : 'HTTP ' . (int) $response->getStatusCode(),
        ];
    }

    /**
     * GET a JSON document.
     *
     * @param   string  $url  URL.
     *
     * @return  array{code: int, json: array<string, mixed>|null, error: string|null}
     *
     * @since   0.1.0
     */
    public static function getJson(string $url): array
    {
        $result = self::get($url);
        $json   = $result['body'] !== '' ? json_decode($result['body'], true) : null;

        return [
            'code'  => $result['code'],
            'json'  => \is_array($json) ? $json : null,
            'error' => $result['error'] ?? (\is_array($json) ? null : 'Response was not JSON.'),
        ];
    }

    /**
     * @return  integer  Seconds.
     *
     * @since   0.1.0
     */
    public static function timeout(): int
    {
        return max(3, min(60, (int) ParamsHelper::get()->get('http_timeout', 10)));
    }

    /**
     * @return  string
     *
     * @since   0.1.0
     */
    public static function userAgent(): string
    {
        $host = (string) Uri::getInstance()->getHost();

        return 'CybersaltExtensionManager/' . self::ownVersion() . ' Joomla/' . (new Version())->getShortVersion() . ' (' . $host . ')';
    }

    /**
     * Installed version of this component.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function ownVersion(): string
    {
        static $version = null;

        if ($version === null) {
            $row     = InstalledHelper::find('component', '', 'com_csextensionmanager');
            $version = $row !== null ? InstalledHelper::version($row) : '0.0.0';
        }

        return $version;
    }

    /**
     * @return  Registry
     *
     * @since   0.1.0
     */
    private static function httpOptions(): Registry
    {
        return new Registry(['userAgent' => self::userAgent()]);
    }

    /**
     * Joomla's bundled CA list, so curl_multi checks certificates the same
     * way Joomla's own HTTP client does on hosts with an old system bundle.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function caBundle(): string
    {
        if (class_exists(\Composer\CaBundle\CaBundle::class)) {
            try {
                return (string) \Composer\CaBundle\CaBundle::getBundledCaBundlePath();
            } catch (\Throwable $e) {
                return '';
            }
        }

        return '';
    }
}
