<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Parses the Release Manager's update and changelog XML.
 *
 * Network-free and side-effect-free, so it can be unit tested.
 *
 * @since  0.1.0
 */
final class FeedParser
{
    /**
     * The `<update>` entry of an api.updatexml response.
     *
     * An empty `<updates/>` (no published version) returns `available => false`.
     * When the server refuses the download ID it says so in the description
     * ("UPDATE NOT AVAILABLE TO THIS SITE: <reason>") and adds an infourl;
     * both come back as `denied` / `info_url`.
     *
     * @param   string  $xml  Response body.
     *
     * @return  array<string, mixed>|null  Null when the body isn't update XML.
     *
     * @since   0.1.0
     */
    public static function parseUpdate(string $xml): ?array
    {
        $doc = self::load($xml);

        if ($doc === null || $doc->getName() !== 'updates') {
            return null;
        }

        $update = $doc->update[0] ?? null;

        if ($update === null) {
            return ['available' => false];
        }

        $description = trim((string) $update->description);
        $denied      = '';

        if (preg_match('/UPDATE NOT AVAILABLE TO THIS SITE:\s*(.+)$/u', $description, $m)) {
            $denied = trim($m[1]);
        }

        $tags = [];

        foreach ($update->tags->tag ?? [] as $tag) {
            $tags[] = strtolower(trim((string) $tag));
        }

        $downloadUrl = '';

        foreach ($update->downloads->downloadurl ?? [] as $download) {
            $downloadUrl = trim((string) $download);

            if ($downloadUrl !== '') {
                break;
            }
        }

        $platform = $update->targetplatform ?? null;

        return [
            'available'      => true,
            'name'           => trim((string) $update->name),
            'element'        => trim((string) $update->element),
            'type'           => trim((string) $update->type),
            'folder'         => trim((string) ($update->folder ?? '')),
            'client'         => trim((string) ($update->client ?? '')),
            'version'        => trim((string) $update->version),
            'download_url'   => $downloadUrl,
            'sha256'         => strtolower(trim((string) ($update->sha256 ?? ''))),
            'targetplatform' => $platform !== null ? trim((string) $platform['version']) : '',
            'php_minimum'    => trim((string) ($update->php_minimum ?? '')),
            'stability'      => \in_array('stable', $tags, true) || $tags === [] ? 'stable' : (string) $tags[0],
            'denied'         => $denied,
            'info_url'       => isset($update->infourl) ? trim((string) $update->infourl) : '',
        ];
    }

    /**
     * An api.changelogxml response, newest version first.
     *
     * @param   string  $xml  Response body.
     *
     * @return  array<int, array{version: string, items: array<int, array{type: string, text: string}>}>|null  Null when it isn't changelog XML.
     *
     * @since   0.1.0
     */
    public static function parseChangelog(string $xml): ?array
    {
        $doc = self::load($xml);

        if ($doc === null || $doc->getName() !== 'changelogs') {
            return null;
        }

        $versions = [];

        foreach ($doc->changelog as $entry) {
            $version = trim((string) $entry->version);

            if ($version === '') {
                continue;
            }

            $items = [];

            foreach ($entry->children() as $section) {
                // <change>, <fix>, <addition>, <security>... (Joomla's changelog format)
                foreach ($section->item ?? [] as $item) {
                    $text = trim(strip_tags((string) $item));

                    if ($text !== '') {
                        $items[] = ['type' => $section->getName(), 'text' => $text];
                    }
                }
            }

            $versions[] = ['version' => $version, 'items' => $items];
        }

        usort($versions, static fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        return $versions;
    }

    /**
     * Does this Joomla version match an update feed's targetplatform regex?
     *
     * Mirrors Joomla's own check (`^` + regex against JVERSION). An empty
     * pattern matches everything.
     *
     * @param   string  $pattern  e.g. `(5|6)\.[0-9]+`.
     * @param   string  $version  Joomla version, defaults to the running one.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function matchesPlatform(string $pattern, string $version = JVERSION): bool
    {
        if ($pattern === '' || $pattern === '.*') {
            return true;
        }

        $regex = '/^' . str_replace('/', '\/', $pattern) . '/';

        return @preg_match($regex, $version) === 1;
    }

    /**
     * Parse XML without network access or entity expansion.
     *
     * @param   string  $xml  XML.
     *
     * @return  \SimpleXMLElement|null
     *
     * @since   0.1.0
     */
    private static function load(string $xml): ?\SimpleXMLElement
    {
        $xml = trim($xml);

        if ($xml === '' || $xml[0] !== '<') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $doc instanceof \SimpleXMLElement ? $doc : null;
    }
}
