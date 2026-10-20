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
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Install, update, reinstall, downgrade, uninstall, enable and disable.
 *
 * Downloads only ever come from the store (StoreClient::isStoreUrl), the
 * latest version is checked against the SHA-256 in the update feed, and the
 * unpacked manifest has to be the extension that was asked for before
 * Joomla's installer sees it.
 *
 * @since  0.1.0
 */
final class InstallHelper
{
    /**
     * Seconds allowed for a package download.
     *
     * @var    integer
     * @since  0.1.0
     */
    private const DOWNLOAD_TIMEOUT = 120;

    /**
     * Install (or update / reinstall / downgrade) one catalog item.
     *
     * @param   array<string, mixed>  $item     Catalog item (enriched or not).
     * @param   string                $version  A specific version, '' for the latest.
     *
     * @return  array{ok: bool, message: string, messages: array<int, array{type: string, text: string}>, action: string, from: string, to: string}
     *
     * @since   0.1.0
     */
    public static function install(array $item, string $version = ''): array
    {
        $start = microtime(true);
        $title = (string) $item['title'];
        $row   = InstalledHelper::find((string) $item['type'], (string) $item['folder'], (string) $item['element']);
        $from  = $row !== null ? InstalledHelper::version($row) : '';

        $fail = static function (string $message, string $details = '') use ($item, $title, $from, $version, $start): array {
            LogHelper::log(
                $from === '' ? LogHelper::ACTION_INSTALL : LogHelper::ACTION_UPDATE,
                LogHelper::STATUS_ERROR,
                (string) $item['element'],
                $title,
                $from,
                $version,
                $message,
                $details,
                (int) round((microtime(true) - $start) * 1000)
            );

            return ['ok' => false, 'message' => $message, 'messages' => [], 'action' => '', 'from' => $from, 'to' => ''];
        };

        if ($version !== '' && !preg_match('/^[0-9][0-9A-Za-z.+-]{0,49}$/', $version)) {
            return $fail(Text::_('COM_CSEXTENSIONMANAGER_INSTALL_BAD_VERSION'));
        }

        // A fresh look at the feed: the latest version, its hash, and whether
        // the store will hand it to this site.
        $dlid = AccountHelper::getDlid();
        $feed = self::freshFeed((string) $item['element'], $dlid);

        if ($feed !== null && !empty($feed['available'])) {
            $item = CatalogHelper::applyFeed($item, $feed);
        }

        if ($feed !== null && empty($feed['available']) && $version === '') {
            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_NOT_PUBLISHED', $title));
        }

        $latest = (string) ($item['latest_version'] ?? '');
        $target = $version !== '' ? $version : $latest;

        if ($target === '' || (string) ($item['download_url'] ?? '') === '') {
            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_NO_URL', $title));
        }

        if ($target === $latest) {
            [$compatible, $reason] = StateHelper::compatibility($item);

            if (!$compatible) {
                return $fail($reason);
            }
        }

        if (($item['denied'] ?? '') !== '') {
            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_DENIED', $title, (string) $item['denied']));
        }

        if (($item['tier'] ?? 'free') === 'pro' && $dlid === '') {
            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_NEEDS_DLID', $title));
        }

        $url = (string) $item['download_url'];

        if (!StoreClient::isStoreUrl($url, ['api.download', 'api.userdownload'])) {
            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_UNTRUSTED_URL', $title));
        }

        $url = StoreClient::withQueryParam($url, 'version', $target);

        if (str_contains($url, 'task=api.download') && $dlid !== '') {
            $url = StoreClient::withQueryParam($url, 'dlid', $dlid);
        }

        // 1. Download.
        $download = self::download($url, (string) $item['element']);

        if ($download['error'] !== null) {
            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_DOWNLOAD_FAILED', $title, $download['error']));
        }

        $file = $download['file'];

        // 2. Same bytes the store published?
        $expected = $target === $latest ? strtolower((string) ($item['sha256'] ?? '')) : '';

        if ($expected !== '' && !hash_equals($expected, (string) hash_file('sha256', $file))) {
            @unlink($file);

            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_HASH_MISMATCH', $title));
        }

        // 3. Unpack and check it's the extension we asked for.
        $package = InstallerHelper::unpack($file, true);

        if (!$package || empty($package['extractdir']) || empty($package['type'])) {
            InstallerHelper::cleanupInstall($file, $package['extractdir'] ?? '');

            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_UNPACK_FAILED', $title));
        }

        $identity = self::manifestIdentity((string) $package['extractdir']);

        if (!self::matches($item, $identity)) {
            InstallerHelper::cleanupInstall($file, (string) $package['extractdir']);

            return $fail(
                Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_WRONG_PACKAGE', $title),
                'Expected ' . $item['type'] . ' ' . $item['element'] . ', got ' . ($identity['type'] ?? '?') . ' ' . ($identity['element'] ?? '?')
            );
        }

        // 4. Install. Extension install scripts echo their own HTML cards;
        //    swallow them so they don't end up in a JSON response.
        $app = Factory::getApplication();
        $app->getMessageQueue(true);
        $ok     = false;
        $output = '';
        $error  = '';

        ob_start();

        try {
            $installer = Installer::getInstance();
            $ok        = (bool) $installer->install((string) $package['extractdir']);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        } finally {
            $output = (string) ob_get_clean();
        }

        InstallerHelper::cleanupInstall($file, (string) $package['extractdir']);
        self::afterInstall();

        $messages = self::takeMessages();

        if (!$ok) {
            $detail = $error !== '' ? $error : implode("\n", array_column($messages, 'text'));

            return $fail(Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_FAILED', $title, $detail), self::plain($output));
        }

        AccountHelper::syncUpdateSites();
        self::forgetUpdates($item);

        $newRow = InstalledHelper::find((string) $item['type'], (string) $item['folder'], (string) $item['element']);
        $to     = $newRow !== null ? InstalledHelper::version($newRow) : $target;
        $action = self::actionFor($from, $to);

        $message = Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_OK_' . strtoupper($action), $title, $to, $from);

        LogHelper::log(
            $action,
            LogHelper::STATUS_SUCCESS,
            (string) $item['element'],
            $title,
            $from,
            $to,
            $message,
            implode("\n", array_column($messages, 'text')),
            (int) round((microtime(true) - $start) * 1000)
        );

        return ['ok' => true, 'message' => $message, 'messages' => $messages, 'action' => $action, 'from' => $from, 'to' => $to];
    }

    /**
     * Uninstall one catalog item through Joomla's installer.
     *
     * @param   array<string, mixed>  $item  Catalog item.
     *
     * @return  array{ok: bool, message: string, messages: array<int, array{type: string, text: string}>}
     *
     * @since   0.1.0
     */
    public static function uninstall(array $item): array
    {
        $start = microtime(true);
        $title = (string) $item['title'];
        $row   = InstalledHelper::find((string) $item['type'], (string) $item['folder'], (string) $item['element']);

        if ($row === null) {
            return ['ok' => false, 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_NOT_INSTALLED', $title), 'messages' => []];
        }

        if (!empty($item['self'])) {
            return ['ok' => false, 'message' => Text::_('COM_CSEXTENSIONMANAGER_UNINSTALL_SELF'), 'messages' => []];
        }

        if ((int) $row->protected === 1 || (int) $row->locked === 1) {
            return ['ok' => false, 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_UNINSTALL_PROTECTED', $title), 'messages' => []];
        }

        $from = InstalledHelper::version($row);
        $app  = Factory::getApplication();
        $app->getMessageQueue(true);
        $ok    = false;
        $error = '';

        ob_start();

        try {
            $ok = (bool) Installer::getInstance()->uninstall((string) $row->type, (int) $row->extension_id, (int) $row->client_id);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        } finally {
            ob_end_clean();
        }

        self::afterInstall();
        $messages = self::takeMessages();

        if (!$ok) {
            $message = Text::sprintf('COM_CSEXTENSIONMANAGER_UNINSTALL_FAILED', $title, $error !== '' ? $error : implode(' ', array_column($messages, 'text')));
            LogHelper::log(LogHelper::ACTION_UNINSTALL, LogHelper::STATUS_ERROR, (string) $item['element'], $title, $from, '', $message, '', (int) round((microtime(true) - $start) * 1000));

            return ['ok' => false, 'message' => $message, 'messages' => $messages];
        }

        self::forgetUpdates($item);

        $message = Text::sprintf('COM_CSEXTENSIONMANAGER_UNINSTALL_OK', $title, $from);
        LogHelper::log(LogHelper::ACTION_UNINSTALL, LogHelper::STATUS_SUCCESS, (string) $item['element'], $title, $from, '', $message, implode("\n", array_column($messages, 'text')), (int) round((microtime(true) - $start) * 1000));

        return ['ok' => true, 'message' => $message, 'messages' => $messages];
    }

    /**
     * Enable or disable an item or one of its package's children.
     *
     * @param   array<string, mixed>  $item         Enriched catalog item.
     * @param   int                   $extensionId  The item's own id or a child's id.
     * @param   boolean               $enabled      New state.
     *
     * @return  array{ok: bool, message: string}
     *
     * @since   0.1.0
     */
    public static function setEnabled(array $item, int $extensionId, bool $enabled): array
    {
        $allowed = [(int) ($item['extension_id'] ?? 0)];

        foreach ((array) ($item['children'] ?? []) as $child) {
            $allowed[] = (int) $child['id'];
        }

        $row = InstalledHelper::byId($extensionId);

        if ($extensionId <= 0 || !\in_array($extensionId, $allowed, true) || $row === null) {
            return ['ok' => false, 'message' => Text::_('COM_CSEXTENSIONMANAGER_TOGGLE_NOT_ALLOWED')];
        }

        // Switching off the manager's own parts from inside the manager only ends badly.
        if (!empty($item['self']) || (int) $row->protected === 1 || (string) $row->type === 'package') {
            return ['ok' => false, 'message' => Text::_('COM_CSEXTENSIONMANAGER_TOGGLE_NOT_ALLOWED')];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $value = $enabled ? 1 : 0;

        $db->setQuery(
            $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = :enabled')
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':enabled', $value, ParameterType::INTEGER)
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->execute();

        foreach (['com_plugins', 'com_modules', '_system'] as $group) {
            ParamsHelper::cleanCacheGroup($group);
        }

        $name    = InstalledHelper::label($row);
        $message = Text::sprintf($enabled ? 'COM_CSEXTENSIONMANAGER_ENABLED_OK' : 'COM_CSEXTENSIONMANAGER_DISABLED_OK', $name);

        LogHelper::log(
            $enabled ? LogHelper::ACTION_ENABLE : LogHelper::ACTION_DISABLE,
            LogHelper::STATUS_SUCCESS,
            (string) $row->element,
            $name,
            InstalledHelper::version($row),
            '',
            $message
        );

        return ['ok' => true, 'message' => $message];
    }

    /**
     * The changelog for an item, newest version first.
     *
     * @param   array<string, mixed>  $item  Catalog item.
     *
     * @return  array<int, array<string, mixed>>|null  Null when the store couldn't be reached.
     *
     * @since   0.1.0
     */
    public static function changelog(array $item): ?array
    {
        $response = StoreClient::get(StoreClient::changelogUrl((string) $item['element']));

        return $response['code'] === 200 ? FeedParser::parseChangelog($response['body']) : null;
    }

    /**
     * install / update / downgrade / reinstall.
     *
     * @param   string  $from  Version before ('' = not installed).
     * @param   string  $to    Version after.
     *
     * @return  string  LogHelper::ACTION_* constant.
     *
     * @since   0.1.0
     */
    public static function actionFor(string $from, string $to): string
    {
        if ($from === '') {
            return LogHelper::ACTION_INSTALL;
        }

        $cmp = version_compare($to, $from);

        return $cmp > 0 ? LogHelper::ACTION_UPDATE : ($cmp < 0 ? LogHelper::ACTION_DOWNGRADE : LogHelper::ACTION_REINSTALL);
    }

    /**
     * The update feed right now (bypasses the catalog cache).
     *
     * @param   string  $element  Element.
     * @param   string  $dlid     Download ID.
     *
     * @return  array<string, mixed>|null
     *
     * @since   0.1.0
     */
    private static function freshFeed(string $element, string $dlid): ?array
    {
        $url = StoreClient::updateXmlUrl($element);

        if ($dlid !== '') {
            $url = StoreClient::withQueryParam($url, 'dlid', $dlid);
        }

        $response = StoreClient::get($url);

        return $response['code'] === 200 ? FeedParser::parseUpdate($response['body']) : null;
    }

    /**
     * Download a package into Joomla's tmp folder, following up to three
     * https redirects, and keep the store's error text when it says no.
     *
     * @param   string  $url      Store download URL.
     * @param   string  $element  Element (for the file name).
     *
     * @return  array{file: string, error: string|null}
     *
     * @since   0.1.0
     */
    private static function download(string $url, string $element): array
    {
        $http = HttpFactory::getHttp(new Registry(['userAgent' => StoreClient::userAgent()]));

        for ($hop = 0; $hop < 4; $hop++) {
            try {
                $response = $http->get($url, [], self::DOWNLOAD_TIMEOUT);
            } catch (\Throwable $e) {
                return ['file' => '', 'error' => $e->getMessage()];
            }

            $code    = (int) $response->getStatusCode();
            $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);

            if (\in_array($code, [301, 302, 303, 307, 308], true) && !empty($headers['location'])) {
                $next = \is_array($headers['location']) ? (string) reset($headers['location']) : (string) $headers['location'];

                if (strtolower((string) parse_url($next, PHP_URL_SCHEME)) !== 'https') {
                    return ['file' => '', 'error' => Text::_('COM_CSEXTENSIONMANAGER_INSTALL_INSECURE_REDIRECT')];
                }

                $url = $next;
                continue;
            }

            if ($code !== 200) {
                $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $response->getBody())));

                return ['file' => '', 'error' => 'HTTP ' . $code . ($text !== '' ? ': ' . mb_substr($text, 0, 300) : '')];
            }

            $body = (string) $response->getBody();

            if (!str_starts_with($body, "PK")) {
                return ['file' => '', 'error' => Text::_('COM_CSEXTENSIONMANAGER_INSTALL_NOT_ZIP')];
            }

            $tmp  = rtrim((string) Factory::getApplication()->get('tmp_path', JPATH_ROOT . '/tmp'), '/\\');
            $name = preg_replace('/[^A-Za-z0-9_-]/', '', $element) ?: 'package';
            $file = $tmp . '/csem_' . $name . '_' . bin2hex(random_bytes(4)) . '.zip';

            if (@file_put_contents($file, $body) === false) {
                return ['file' => '', 'error' => Text::sprintf('COM_CSEXTENSIONMANAGER_INSTALL_TMP_NOT_WRITABLE', $tmp)];
            }

            return ['file' => $file, 'error' => null];
        }

        return ['file' => '', 'error' => Text::_('COM_CSEXTENSIONMANAGER_INSTALL_TOO_MANY_REDIRECTS')];
    }

    /**
     * Type and element of the extension in an unpacked folder.
     *
     * @param   string  $dir  Extract folder.
     *
     * @return  array{type: string, element: string}|null
     *
     * @since   0.1.0
     */
    private static function manifestIdentity(string $dir): ?array
    {
        foreach (glob(rtrim($dir, '/\\') . '/*.xml') ?: [] as $file) {
            $previous = libxml_use_internal_errors(true);
            $xml      = simplexml_load_file($file, \SimpleXMLElement::class, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$xml instanceof \SimpleXMLElement || $xml->getName() !== 'extension') {
                continue;
            }

            $type    = strtolower((string) $xml['type']);
            $element = '';

            if ($type === 'package') {
                $name    = trim((string) $xml->packagename);
                $element = $name !== '' ? 'pkg_' . $name : '';
            } elseif (trim((string) ($xml->element ?? '')) !== '') {
                $element = trim((string) $xml->element);
            } elseif ($type === 'plugin') {
                foreach ($xml->files->children() as $child) {
                    if ((string) $child['plugin'] !== '') {
                        $element = (string) $child['plugin'];
                        break;
                    }
                }
            } elseif ($type === 'module') {
                foreach ($xml->files->children() as $child) {
                    if ((string) $child['module'] !== '') {
                        $element = (string) $child['module'];
                        break;
                    }
                }
            } elseif ($type === 'component') {
                $name    = strtolower(trim((string) $xml->name));
                $element = str_starts_with($name, 'com_') ? $name : 'com_' . $name;
            }

            return ['type' => $type, 'element' => strtolower($element)];
        }

        return null;
    }

    /**
     * Is the unpacked manifest the catalog item?
     *
     * @param   array<string, mixed>                       $item      Catalog item.
     * @param   array{type: string, element: string}|null  $identity  From manifestIdentity().
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    private static function matches(array $item, ?array $identity): bool
    {
        if ($identity === null || $identity['type'] !== strtolower((string) $item['type'])) {
            return false;
        }

        // Element unknown from the manifest (old-style component name): type match is enough.
        return $identity['element'] === '' || $identity['element'] === strtolower((string) $item['element']);
    }

    /**
     * Joomla keeps rows in #__updates until its next check, so an update we
     * just installed would still be listed in System → Update → Extensions.
     *
     * @param   array<string, mixed>  $item  Catalog item.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function forgetUpdates(array $item): void
    {
        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $element = (string) $item['element'];
            $type    = (string) $item['type'];
            $query   = $db->createQuery()
                ->delete($db->quoteName('#__updates'))
                ->where($db->quoteName('element') . ' = :element')
                ->where($db->quoteName('type') . ' = :type')
                ->bind(':element', $element)
                ->bind(':type', $type);

            if ($type === 'plugin') {
                $folder = (string) $item['folder'];
                $query->where($db->quoteName('folder') . ' = :folder')->bind(':folder', $folder);
            }

            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Joomla's next update check rewrites the table anyway.
        }
    }

    /**
     * New namespaces must resolve on the next request: drop the PSR-4 map
     * (Joomla rebuilds it) and reset OPcache.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function afterInstall(): void
    {
        $map = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

        if (is_file($map)) {
            @unlink($map);
        }

        if (\function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /**
     * Messages Joomla's installer queued, cleared from the queue.
     *
     * @return  array<int, array{type: string, text: string}>
     *
     * @since   0.1.0
     */
    private static function takeMessages(): array
    {
        $out = [];

        foreach (Factory::getApplication()->getMessageQueue(true) as $message) {
            $text = self::plain((string) ($message['message'] ?? ''));

            if ($text !== '') {
                $out[] = ['type' => (string) ($message['type'] ?? 'message'), 'text' => $text];
            }
        }

        return $out;
    }

    /**
     * HTML to one line of plain text.
     *
     * @param   string  $html  HTML.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function plain(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
