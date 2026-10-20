<?php

/**
 * Minimal test runner for the network-free helpers (no Joomla needed).
 *
 *     php tests/run.php
 *
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

\define('_JEXEC', 1);
\define('JVERSION', '6.1.4');

$src = __DIR__ . '/../packages/com_csextensionmanager/admin/src/Helper/';

require $src . 'FeedParser.php';
require $src . 'StateHelper.php';
require $src . 'StoreClient.php';

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\FeedParser;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StoreClient;

$failures = 0;
$count    = 0;

$check = static function (string $name, bool $ok) use (&$failures, &$count): void {
    $count++;

    if (!$ok) {
        $failures++;
        echo "FAIL  {$name}\n";
    } else {
        echo "ok    {$name}\n";
    }
};

$fixture = static fn (string $name): string => (string) file_get_contents(__DIR__ . '/fixtures/' . $name);

// --- parseUpdate ---------------------------------------------------------
$u = FeedParser::parseUpdate($fixture('updatexml-package.xml'));
$check('package feed parses', \is_array($u) && $u['available'] === true);
$check('package version', $u['version'] === '0.2.1');
$check('package element', $u['element'] === 'pkg_cspageprotector');
$check('package download url decoded', str_contains($u['download_url'], '&element=pkg_cspageprotector&version=0.2.1'));
$check('package sha256', $u['sha256'] === '299185008a8afdc26c0dfa16f115f2469dbb079bbdd58d43ff46bda77464b780');
$check('package targetplatform', $u['targetplatform'] === '(5|6)\.[0-9]+');
$check('package php minimum', $u['php_minimum'] === '8.1');
$check('package stable', $u['stability'] === 'stable');
$check('package not denied', $u['denied'] === '');

$p = FeedParser::parseUpdate($fixture('updatexml-plugin.xml'));
$check('plugin folder', $p['folder'] === 'system' && $p['type'] === 'plugin');

$e = FeedParser::parseUpdate('<?xml version="1.0" encoding="utf-8"?><updates></updates>');
$check('empty feed = not available', $e === ['available' => false]);

$d = FeedParser::parseUpdate($fixture('updatexml-denied.xml'));
$check('denied reason extracted', $d['denied'] === 'Your membership has expired.');
$check('denied info url', $d['info_url'] === 'https://www.cybersalt.com/membership-plans');
$check('beta tag', $d['stability'] === 'beta');

$check('HTML is not a feed', FeedParser::parseUpdate('<html><body>Error</body></html>') === null);
$check('garbage is not a feed', FeedParser::parseUpdate('Not found') === null);
$check('XXE entity not expanded', !str_contains((string) json_encode(FeedParser::parseUpdate($fixture('updatexml-xxe.xml'))), 'root:'));

// --- parseChangelog --------------------------------------------------------
$c = FeedParser::parseChangelog($fixture('changelogxml.xml'));
$check('changelog parses', \is_array($c) && \count($c) === 3);
$check('changelog newest first', $c[0]['version'] === '0.2.1' && $c[2]['version'] === '0.1.0');
$check('changelog items', \count($c[1]['items']) === 3 && $c[1]['items'][0]['type'] === 'change');
$check('changelog typed items', $c[0]['items'][1]['type'] === 'fix');
$check('changelog of a feed is null', FeedParser::parseChangelog($fixture('updatexml-package.xml')) === null);

// --- matchesPlatform --------------------------------------------------------
$check('platform 5|6 matches 6.1.4', FeedParser::matchesPlatform('(5|6)\.[0-9]+', '6.1.4'));
$check('platform 5|6 matches 5.4.10', FeedParser::matchesPlatform('(5|6)\.[0-9]+', '5.4.10'));
$check('platform 5|6 rejects 4.4.9', !FeedParser::matchesPlatform('(5|6)\.[0-9]+', '4.4.9'));
$check('platform 5 rejects 6.0.0', !FeedParser::matchesPlatform('5\.[0-9]+', '6.0.0'));
$check('platform empty matches all', FeedParser::matchesPlatform('', '4.0.0'));
$check('platform bad regex does not match', !FeedParser::matchesPlatform('(5|6', '6.0.0'));

// --- updateType ---------------------------------------------------------------
$check('patch', StateHelper::updateType('1.2.3', '1.2.4') === 'patch');
$check('minor', StateHelper::updateType('1.2.3', '1.3.0') === 'minor');
$check('major', StateHelper::updateType('1.9.9', '2.0.0') === 'major');
$check('short versions', StateHelper::updateType('1.2', '1.2.1') === 'patch');
$check('suffixes ignored', StateHelper::updateType('1.2.3-beta', '1.2.4') === 'patch');

// --- isTrustedHost / isStoreUrl ------------------------------------------------
$dl = 'https://www.cybersalt.com/index.php?option=com_csreleasemanager&task=api.userdownload&format=raw&element=x';
$check('store download trusted', StoreClient::isStoreUrl($dl, ['api.userdownload']));
$check('bare domain trusted', StoreClient::isTrustedHost('https://cybersalt.com/index.php'));
$check('explicit 443 trusted', StoreClient::isTrustedHost('https://www.cybersalt.com:443/x'));
$check('wrong task refused', !StoreClient::isStoreUrl($dl, ['api.download']));
$check('http refused', !StoreClient::isTrustedHost('http://www.cybersalt.com/index.php'));
$check('other port refused', !StoreClient::isTrustedHost('https://www.cybersalt.com:8443/index.php'));
$check('userinfo refused', !StoreClient::isTrustedHost('https://www.cybersalt.com@evil.example/index.php'));
$check('userinfo on store refused', !StoreClient::isTrustedHost('https://user:pass@www.cybersalt.com/index.php'));
$check('lookalike suffix refused', !StoreClient::isTrustedHost('https://www.cybersalt.com.evil.example/index.php'));
$check('subdomain refused', !StoreClient::isTrustedHost('https://evil.cybersalt.com/index.php'));
$check('entity-encoded refused', !StoreClient::isTrustedHost('https://www.cybersalt.com/index.php?option=com_csreleasemanager&amp;task=api.download'));
$check('backslash refused', !StoreClient::isTrustedHost('https://evil.example\\@www.cybersalt.com/'));
$check('whitespace refused', !StoreClient::isTrustedHost("https://www.cybersalt.com /x"));
$check('other option refused', !StoreClient::isStoreUrl('https://www.cybersalt.com/index.php?option=com_content&task=api.download'));

// --- withQueryParam -------------------------------------------------------------
$check('adds param', StoreClient::withQueryParam('https://a.test/i.php?x=1', 'dlid', 'abc') === 'https://a.test/i.php?x=1&dlid=abc');
$check('replaces param', StoreClient::withQueryParam('https://a.test/i.php?dlid=old&x=1', 'dlid', 'new') === 'https://a.test/i.php?x=1&dlid=new');
$check('removes param', StoreClient::withQueryParam('https://a.test/i.php?x=1&dlid=old', 'dlid', '') === 'https://a.test/i.php?x=1');
$check('encodes value', StoreClient::withQueryParam('https://a.test/i.php', 'v', 'a&b') === 'https://a.test/i.php?v=a%26b');

echo "\n{$count} checks, {$failures} failed\n";

exit($failures === 0 ? 0 : 1);
