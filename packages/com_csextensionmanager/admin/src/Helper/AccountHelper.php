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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The site's Cybersalt download ID and what it unlocks.
 *
 * A download ID is `<installation_id>:<sha256 of the account email>`, the
 * format cs-release-manager issues. It can come from three places, first
 * match wins:
 *
 * 1. `manual`  - pasted into Options (e.g. from cybersalt.com "My licenses").
 * 2. `account` - this manager's own "activate with your email" flow.
 * 3. `mcpforj` - MCP for Joomla's Pro activation, reused so a site that
 *                already activated there doesn't register a second install
 *                (the store counts installs per account).
 *
 * Free extensions never need one.
 *
 * @since  0.1.0
 */
final class AccountHelper
{
    public const SOURCE_NONE    = '';
    public const SOURCE_MANUAL  = 'manual';
    public const SOURCE_ACCOUNT = 'account';
    public const SOURCE_MCPFORJ = 'mcpforj';

    /**
     * The store package the activation registers against. All-Access and the
     * per-add-on plans all hang off it on cybersalt.com.
     *
     * @var    string
     * @since  0.1.0
     */
    public const ANCHOR_ELEMENT = 'csmcpforjpro';

    /**
     * Re-check the download ID with the store at most this often.
     *
     * @var    integer
     * @since  0.1.0
     */
    private const VERIFY_EVERY = 86400;

    /**
     * The download ID in use, or ''.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function getDlid(): string
    {
        return self::resolve()['dlid'];
    }

    /**
     * Where the download ID came from (SOURCE_* constant).
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function getSource(): string
    {
        return self::resolve()['source'];
    }

    /**
     * Everything the account card needs.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    public static function getState(): array
    {
        $params   = ParamsHelper::get();
        $resolved = self::resolve();
        $verified = (string) $params->get('account_verified_for', '') === self::fingerprint($resolved['dlid']);

        return [
            'source'        => $resolved['source'],
            'dlid'          => $resolved['dlid'],
            'dlid_masked'   => self::mask($resolved['dlid']),
            'email'         => $resolved['source'] === self::SOURCE_ACCOUNT ? (string) $params->get('account_email', '') : $resolved['email'],
            'status'        => $verified ? (string) $params->get('account_status', '') : '',
            'message'       => $verified ? (string) $params->get('account_message', '') : '',
            'entitled'      => $verified ? self::entitledElements() : [],
            'verified_at'   => $verified ? (string) $params->get('account_verified_at', '') : '',
            'renewal_url'   => self::safeUrl((string) $params->get('account_renewal_url', '')),
            'signup_url'    => self::safeUrl((string) $params->get('account_signup_url', '')),
            'mcpforj_ready' => self::mcpforjDlid()['dlid'] !== '',
            'manual_invalid' => trim((string) $params->get('download_id', '')) !== '' && $resolved['source'] !== self::SOURCE_MANUAL,
        ];
    }

    /**
     * Does a string look like a Release Manager download ID?
     *
     * @param   string  $dlid  Candidate.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function isValidDlid(string $dlid): bool
    {
        return preg_match('/^\d{8,14}_[A-Za-z0-9._-]{1,80}:[a-f0-9]{64}$/', $dlid) === 1;
    }

    /**
     * Is this catalog item installable with the current download ID?
     * Free items always are. For Pro items an unverified ID gets the benefit
     * of the doubt; the store has the final say when the download starts.
     *
     * @param   array<string, mixed>  $item  Catalog item.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function isEntitled(array $item): bool
    {
        if (($item['tier'] ?? 'free') !== 'pro') {
            return true;
        }

        $state = self::getState();

        if ($state['dlid'] === '') {
            return false;
        }

        if ($state['status'] === '') {
            return true;
        }

        return $state['status'] === 'active' || \in_array((string) $item['element'], $state['entitled'], true);
    }

    /**
     * Elements the account may install (from the last verification).
     *
     * @return  string[]
     *
     * @since   0.1.0
     */
    public static function entitledElements(): array
    {
        $raw  = ParamsHelper::get()->get('account_entitled', '[]');
        $list = \is_array($raw) ? $raw : json_decode((string) $raw, true);

        return \is_array($list) ? array_values(array_filter(array_map('strval', $list))) : [];
    }

    /**
     * Activate this site with a cybersalt.com account email: register an
     * installation, then link the email. The store emails the account owner
     * either way.
     *
     * @param   string  $email  Account email.
     *
     * @return  array{ok: bool, message: string}
     *
     * @since   0.1.0
     */
    public static function activate(string $email): array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_BAD_EMAIL')];
        }

        $params         = ParamsHelper::get();
        $installationId = (string) $params->get('account_installation_id', '');

        if ($installationId === '') {
            $installationId = self::newInstallationId();
        }

        $register = StoreClient::postJson(StoreClient::apiUrl('register', [], 'json'), [
            'installation_id' => $installationId,
            'package_element' => self::ANCHOR_ELEMENT,
            'domain'          => self::domain(),
        ]);

        if (!\in_array($register['code'], [200, 201], true)) {
            return ['ok' => false, 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_REGISTER_FAILED', self::serverError($register))];
        }

        $hash = hash('sha256', $email);
        $link = StoreClient::postJson(StoreClient::apiUrl('linkemail', [], 'json'), [
            'installation_id' => $installationId,
            'email_hash'      => $hash,
            'email'           => $email,
        ]);

        if ($link['code'] !== 200 || !\is_array($link['json'])) {
            // Keep the registered id so a retry reuses it instead of piling up rows.
            ParamsHelper::save(['account_installation_id' => $installationId]);

            return ['ok' => false, 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_LINK_FAILED', self::serverError($link))];
        }

        $json = $link['json'];
        $dlid = $installationId . ':' . $hash;

        ParamsHelper::save([
            'account_installation_id' => $installationId,
            'account_email'           => $email,
            'account_email_hash'      => $hash,
            'account_status'          => (string) ($json['access_state'] ?? ''),
            'account_message'         => (string) ($json['message'] ?? ''),
            'account_entitled'        => json_encode(array_values((array) ($json['entitled_elements'] ?? []))),
            'account_renewal_url'     => (string) ($json['renewal_url'] ?? ''),
            'account_signup_url'      => (string) ($json['signup_url'] ?? ''),
            'account_verified_at'     => Factory::getDate()->toSql(),
            'account_verified_for'    => self::fingerprint($dlid),
        ]);

        self::syncUpdateSites();
        LogHelper::log(LogHelper::ACTION_ACCOUNT, LogHelper::STATUS_SUCCESS, '', '', '', '', Text::sprintf('COM_CSEXTENSIONMANAGER_LOG_ACCOUNT_ACTIVATED', $email));

        return ['ok' => true, 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_ACTIVATED', $email)];
    }

    /**
     * Ask the store what the current download ID unlocks, and remember it.
     *
     * @param   boolean  $force  Ignore the once-a-day throttle.
     *
     * @return  array{ok: bool, state: string, message: string}
     *
     * @since   0.1.0
     */
    public static function verify(bool $force = false): array
    {
        $dlid = self::getDlid();

        if ($dlid === '') {
            return ['ok' => false, 'state' => '', 'message' => Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_NO_DLID')];
        }

        $params = ParamsHelper::get();

        if (!$force && (string) $params->get('account_verified_for', '') === self::fingerprint($dlid)) {
            $last = strtotime((string) $params->get('account_verified_at', '') . ' UTC') ?: 0;

            if (time() - $last < self::VERIFY_EVERY) {
                return ['ok' => true, 'state' => (string) $params->get('account_status', ''), 'message' => (string) $params->get('account_message', '')];
            }
        }

        $result = StoreClient::getJson(StoreClient::apiUrl('verifyaccess', ['dlid' => $dlid], 'json'));
        $json   = $result['json'];

        if (!\is_array($json) || !isset($json['state'])) {
            return ['ok' => false, 'state' => '', 'message' => Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_VERIFY_FAILED', (string) $result['error'])];
        }

        $state = (string) $json['state'];

        ParamsHelper::save([
            'account_status'       => $state,
            'account_message'      => (string) ($json['message'] ?? ''),
            'account_entitled'     => json_encode(array_values((array) ($json['entitled_elements'] ?? []))),
            'account_renewal_url'  => (string) ($json['renewal_url'] ?? ''),
            'account_signup_url'   => (string) ($json['signup_url'] ?? ''),
            'account_verified_at'  => Factory::getDate()->toSql(),
            'account_verified_for' => self::fingerprint($dlid),
        ]);

        return ['ok' => \in_array($state, ['active', 'lapsed', 'not_a_member'], true), 'state' => $state, 'message' => (string) ($json['message'] ?? '')];
    }

    /**
     * Forget this manager's own activation (the manual field and MCP for J's
     * activation are left alone) and take its ID off the update sites.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function deactivate(): void
    {
        $old = self::getDlid();

        ParamsHelper::save([
            'account_installation_id' => null,
            'account_email'           => null,
            'account_email_hash'      => null,
            'account_status'          => null,
            'account_message'         => null,
            'account_entitled'        => null,
            'account_renewal_url'     => null,
            'account_signup_url'      => null,
            'account_verified_at'     => null,
            'account_verified_for'    => null,
        ]);

        // Whatever wins now (manual / MCP for J / nothing) goes onto the update sites.
        self::syncUpdateSites($old);
        LogHelper::log(LogHelper::ACTION_ACCOUNT, LogHelper::STATUS_SUCCESS, '', '', '', '', Text::_('COM_CSEXTENSIONMANAGER_LOG_ACCOUNT_DEACTIVATED'));
    }

    /**
     * Put the current download ID on every Cybersalt update site, in
     * `extra_query` (Joomla adds it to downloads) and in `location` (so the
     * store sees who's asking and can put the ID in the download URL it
     * returns). Rows that aren't Cybersalt feeds but carry the ID lose it.
     *
     * @param   string  $previous  A download ID being retired, to scrub as well.
     *
     * @return  integer  Rows changed.
     *
     * @since   0.1.0
     */
    public static function syncUpdateSites(string $previous = ''): int
    {
        if ((int) ParamsHelper::get()->get('sync_update_sites', 1) !== 1) {
            return 0;
        }

        $dlid       = self::getDlid();
        $extraQuery = $dlid === '' ? '' : 'dlid=' . $dlid;
        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $written    = 0;

        try {
            $rows = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName(['update_site_id', 'location', 'extra_query']))
                    ->from($db->quoteName('#__update_sites'))
                    ->where($db->quoteName('location') . ' LIKE ' . $db->quote('%com_csreleasemanager%'))
            )->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return 0;
        }

        foreach ($rows as $row) {
            $location = (string) $row->location;
            $isFeed   = StoreClient::isStoreUrl($location, ['api.updatexml']);

            if (!$isFeed) {
                // Never leave an ID on a URL that isn't ours.
                $carries = false;

                foreach (array_filter([$dlid, $previous]) as $id) {
                    $carries = $carries || str_contains($location, $id) || str_contains((string) $row->extra_query, $id);
                }

                if (!$carries) {
                    continue;
                }

                $newLocation = StoreClient::withQueryParam($location, 'dlid', '');
                $newExtra    = '';
            } else {
                $newLocation = StoreClient::withQueryParam($location, 'dlid', $dlid);
                $newExtra    = $extraQuery;
            }

            if ($newLocation === $location && $newExtra === (string) $row->extra_query) {
                continue;
            }

            $id = (int) $row->update_site_id;

            $db->setQuery(
                $db->createQuery()
                    ->update($db->quoteName('#__update_sites'))
                    ->set($db->quoteName('location') . ' = :location')
                    ->set($db->quoteName('extra_query') . ' = :extra')
                    ->where($db->quoteName('update_site_id') . ' = :id')
                    ->bind(':location', $newLocation)
                    ->bind(':extra', $newExtra)
                    ->bind(':id', $id, ParameterType::INTEGER)
            )->execute();

            $written++;
        }

        return $written;
    }

    /**
     * `YmdHis_<host>_<6 hex>`, the id format the store expects.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function newInstallationId(): string
    {
        $host = preg_replace('/[^A-Za-z0-9.-]/', '', self::domain()) ?: 'site';

        return gmdate('YmdHis') . '_' . substr($host, 0, 60) . '_' . bin2hex(random_bytes(3));
    }

    /**
     * Pick the download ID: manual, own activation, MCP for J.
     *
     * @return  array{dlid: string, source: string, email: string}
     *
     * @since   0.1.0
     */
    private static function resolve(): array
    {
        static $memo = [];

        $params      = ParamsHelper::get();
        $fingerprint = md5($params->toString());

        return $memo[$fingerprint] ??= self::resolveUncached();
    }

    /**
     * @return  array{dlid: string, source: string, email: string}
     *
     * @since   0.1.0
     */
    private static function resolveUncached(): array
    {
        $params = ParamsHelper::get();
        $manual = trim((string) $params->get('download_id', ''));

        if ($manual !== '' && self::isValidDlid($manual)) {
            return ['dlid' => $manual, 'source' => self::SOURCE_MANUAL, 'email' => ''];
        }

        $installationId = (string) $params->get('account_installation_id', '');
        $hash           = (string) $params->get('account_email_hash', '');

        if ($installationId !== '' && $hash !== '') {
            return ['dlid' => $installationId . ':' . $hash, 'source' => self::SOURCE_ACCOUNT, 'email' => (string) $params->get('account_email', '')];
        }

        if ((int) $params->get('use_mcpforj_activation', 1) === 1) {
            $mcp = self::mcpforjDlid();

            if ($mcp['dlid'] !== '') {
                return ['dlid' => $mcp['dlid'], 'source' => self::SOURCE_MCPFORJ, 'email' => $mcp['email']];
            }
        }

        return ['dlid' => '', 'source' => self::SOURCE_NONE, 'email' => ''];
    }

    /**
     * MCP for Joomla's Pro activation, if it has one.
     *
     * @return  array{dlid: string, email: string}
     *
     * @since   0.1.0
     */
    private static function mcpforjDlid(): array
    {
        $mcp            = ParamsHelper::readComponentParams('com_csmcpforj');
        $installationId = (string) $mcp->get('pro_installation_id', '');
        $hash           = (string) $mcp->get('pro_email_hash', '');

        if ($installationId === '' || $hash === '') {
            return ['dlid' => '', 'email' => ''];
        }

        return ['dlid' => $installationId . ':' . $hash, 'email' => (string) $mcp->get('pro_email', '')];
    }

    /**
     * Short fingerprint so a stored verification is only trusted for the ID it was made for.
     *
     * @param   string  $dlid  Download ID.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function fingerprint(string $dlid): string
    {
        return $dlid === '' ? '' : substr(hash('sha256', $dlid), 0, 16);
    }

    /**
     * `20261020…_www.example.com_9fef96:3f2a…c1d0` with the middle hidden.
     *
     * @param   string  $dlid  Download ID.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function mask(string $dlid): string
    {
        if ($dlid === '') {
            return '';
        }

        [$id, $hash] = array_pad(explode(':', $dlid, 2), 2, '');

        return $id . ':' . substr($hash, 0, 4) . '…' . substr($hash, -4);
    }

    /**
     * @param   string  $url  URL from the store.
     *
     * @return  string  The URL when it's http(s), '' otherwise.
     *
     * @since   0.1.0
     */
    private static function safeUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    /**
     * This site's host name.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function domain(): string
    {
        return strtolower((string) Uri::getInstance(Uri::root())->getHost());
    }

    /**
     * The store's error text, or the HTTP status.
     *
     * @param   array{code: int, json: array<string, mixed>|null, error: string|null}  $result  postJson() result.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function serverError(array $result): string
    {
        $json = $result['json'] ?? null;

        if (\is_array($json)) {
            foreach (['message', 'error'] as $key) {
                if (!empty($json[$key]) && \is_string($json[$key])) {
                    return $json[$key];
                }
            }
        }

        return (string) ($result['error'] ?? ('HTTP ' . $result['code']));
    }
}
