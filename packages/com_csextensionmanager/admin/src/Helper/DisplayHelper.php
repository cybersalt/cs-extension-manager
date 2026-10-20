<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Small pieces of admin markup shared by the views. Every string is
 * escaped here; callers echo the result as-is.
 *
 * @since  0.1.0
 */
final class DisplayHelper
{
    /**
     * @param   string  $text  Text.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Status badge for a catalog item.
     *
     * @param   array<string, mixed>  $item  Enriched item.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function statusBadge(array $item): string
    {
        [$class, $key] = match ((string) $item['status']) {
            StateHelper::STATUS_UPDATE      => ['bg-success', 'COM_CSEXTENSIONMANAGER_STATE_UPDATE'],
            StateHelper::STATUS_BROKEN      => ['bg-danger', 'COM_CSEXTENSIONMANAGER_STATE_BROKEN'],
            StateHelper::STATUS_CURRENT     => ['bg-primary', 'COM_CSEXTENSIONMANAGER_STATE_CURRENT'],
            StateHelper::STATUS_AVAILABLE   => ['bg-secondary', 'COM_CSEXTENSIONMANAGER_STATE_AVAILABLE'],
            default                         => ['bg-secondary', 'COM_CSEXTENSIONMANAGER_STATE_UNAVAILABLE'],
        };

        return '<span class="badge ' . $class . '">' . self::e(Text::_($key)) . '</span>';
    }

    /**
     * Free / Pro badge.
     *
     * @param   array<string, mixed>  $item  Item.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function tierBadge(array $item): string
    {
        return ($item['tier'] ?? 'free') === 'pro'
            ? '<span class="badge bg-warning text-dark">' . self::e(Text::_('COM_CSEXTENSIONMANAGER_TIER_PRO')) . '</span>'
            : '<span class="badge bg-success">' . self::e(Text::_('COM_CSEXTENSIONMANAGER_TIER_FREE')) . '</span>';
    }

    /**
     * "Package", "System plugin", "Component"...
     *
     * @param   array<string, mixed>  $item  Item or child row with type/folder.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function typeLabel(array $item): string
    {
        $type = (string) ($item['type'] ?? '');

        if ($type === 'plugin' && (string) ($item['folder'] ?? '') !== '') {
            return Text::sprintf('COM_CSEXTENSIONMANAGER_TYPE_PLUGIN_GROUP', ucfirst((string) $item['folder']));
        }

        $key = 'COM_CSEXTENSIONMANAGER_TYPE_' . strtoupper($type);

        return Text::_($key) !== $key ? Text::_($key) : ucfirst($type);
    }

    /**
     * Badge summarising the account / download ID.
     *
     * @param   string  $source  AccountHelper::SOURCE_*.
     * @param   string  $status  Verified state.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function accountBadge(string $source, string $status): string
    {
        if ($source === AccountHelper::SOURCE_NONE) {
            return '<span class="badge bg-secondary">' . self::e(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_BADGE_NONE')) . '</span>';
        }

        [$class, $key] = match ($status) {
            'active'       => ['bg-success', 'COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_ACTIVE'],
            'lapsed'       => ['bg-warning text-dark', 'COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_LAPSED'],
            'not_a_member' => ['bg-info text-dark', 'COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_NOT_A_MEMBER'],
            ''             => ['bg-secondary', 'COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_UNCHECKED'],
            default        => ['bg-danger', 'COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_PROBLEM'],
        };

        return '<span class="badge ' . $class . '">' . self::e(Text::_($key)) . '</span>';
    }

    /**
     * What the card's main button should do.
     *
     * @param   array<string, mixed>  $item        Enriched item.
     * @param   boolean               $canInstall  User may install.
     *
     * @return  array{kind: string, label: string}
     *
     * @since   0.1.0
     */
    public static function primaryAction(array $item, bool $canInstall): array
    {
        $status = (string) $item['status'];

        if (\in_array($status, [StateHelper::STATUS_CURRENT, StateHelper::STATUS_UNAVAILABLE], true)) {
            return ['kind' => $status, 'label' => ''];
        }

        if (!$canInstall) {
            return ['kind' => 'none', 'label' => ''];
        }

        if ($status !== StateHelper::STATUS_BROKEN && !$item['compatible']) {
            return ['kind' => 'incompatible', 'label' => Text::_('COM_CSEXTENSIONMANAGER_BTN_INCOMPATIBLE')];
        }

        if (!$item['entitled']) {
            return ['kind' => 'get', 'label' => Text::_($item['installed'] ? 'COM_CSEXTENSIONMANAGER_BTN_RENEW' : 'COM_CSEXTENSIONMANAGER_BTN_GET')];
        }

        return match ($status) {
            StateHelper::STATUS_UPDATE => ['kind' => 'update', 'label' => Text::sprintf('COM_CSEXTENSIONMANAGER_BTN_UPDATE_TO', $item['latest_version'])],
            StateHelper::STATUS_BROKEN => ['kind' => 'repair', 'label' => Text::_('COM_CSEXTENSIONMANAGER_BTN_REPAIR')],
            default                    => ['kind' => 'install', 'label' => Text::_($item['requires_missing'] !== [] ? 'COM_CSEXTENSIONMANAGER_BTN_INSTALL_WITH_PARENT' : 'COM_CSEXTENSIONMANAGER_BTN_INSTALL')],
        };
    }

    /**
     * The card's main button.
     *
     * @param   array<string, mixed>               $item    Enriched item.
     * @param   array{kind: string, label: string}  $action  From primaryAction().
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function primaryButton(array $item, array $action): string
    {
        $key = self::e((string) $item['key']);

        return match ($action['kind']) {
            'update'  => '<button type="button" class="btn btn-sm btn-success" data-csem-install="' . $key . '"><span class="icon-upload" aria-hidden="true"></span> ' . self::e($action['label']) . '</button>',
            'install' => '<button type="button" class="btn btn-sm btn-primary" data-csem-install="' . $key . '"><span class="icon-download" aria-hidden="true"></span> ' . self::e($action['label']) . '</button>',
            'repair'  => '<button type="button" class="btn btn-sm btn-danger" data-csem-install="' . $key . '"><span class="icon-wrench" aria-hidden="true"></span> ' . self::e($action['label']) . '</button>',
            'get'     => '<a class="btn btn-sm csem-btn-get" href="' . self::e(self::getUrl($item)) . '" target="_blank" rel="noopener noreferrer">' . self::e($action['label']) . '</a>',
            'incompatible' => '<button type="button" class="btn btn-sm btn-secondary" disabled>' . self::e($action['label']) . '</button>',
            StateHelper::STATUS_CURRENT => '<span class="small text-success"><span class="icon-check" aria-hidden="true"></span> ' . self::e(Text::_('COM_CSEXTENSIONMANAGER_UP_TO_DATE')) . '</span>',
            StateHelper::STATUS_UNAVAILABLE => '<span class="small text-body-secondary">' . self::e(Text::_('COM_CSEXTENSIONMANAGER_NOT_PUBLISHED')) . '</span>',
            default   => '',
        };
    }

    /**
     * Where "Get it" / "Renew" goes.
     *
     * @param   array<string, mixed>  $item  Item.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function getUrl(array $item): string
    {
        $account = AccountHelper::getState();

        if ($item['installed'] && $account['renewal_url'] !== '') {
            return $account['renewal_url'];
        }

        if ((string) ($item['info_url'] ?? '') !== '') {
            return (string) $item['info_url'];
        }

        return (string) ($item['product_url'] ?? '') !== '' ? (string) $item['product_url'] : SupportHelper::PLANS_URL;
    }

    /**
     * "Checked 12 minutes ago from cybersalt.com (update feeds)".
     *
     * @param   array<string, mixed>  $catalog  CatalogHelper::get() result.
     *
     * @return  string  Plain text.
     *
     * @since   0.1.0
     */
    public static function catalogLine(array $catalog): string
    {
        $host   = (string) parse_url(StoreClient::storeRoot(), PHP_URL_HOST);
        $source = Text::_('COM_CSEXTENSIONMANAGER_SOURCE_' . strtoupper((string) $catalog['source']));

        if ((int) $catalog['fetched'] <= 0) {
            return Text::sprintf('COM_CSEXTENSIONMANAGER_CATALOG_NEVER', $host);
        }

        $when = HTMLHelper::_('date.relative', gmdate('Y-m-d H:i:s', (int) $catalog['fetched']));

        return Text::sprintf('COM_CSEXTENSIONMANAGER_CATALOG_LINE', $when, $host, $source);
    }

    /**
     * Data and strings for media/js/admin.js.
     *
     * @param   Document                             $document    Document.
     * @param   array<string, array<string, mixed>>  $items       Enriched items.
     * @param   boolean                              $canInstall  User may install.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function scriptOptions(Document $document, array $items, bool $canInstall): void
    {
        $slim = [];

        foreach ($items as $key => $item) {
            $slim[$key] = [
                'title'     => (string) $item['title'],
                'status'    => (string) $item['status'],
                'installed' => (bool) $item['installed'],
                'installedVersion' => (string) $item['installed_version'],
                'latest'    => (string) $item['latest_version'],
                'requires'  => array_values((array) $item['requires_missing']),
                'self'      => !empty($item['self']),
                'entitled'  => (bool) $item['entitled'],
                'compatible' => (bool) $item['compatible'],
            ];
        }

        $base = 'index.php?option=com_csextensionmanager&task=';

        $document->addScriptOptions('com_csextensionmanager', [
            'token'      => Session::getFormToken(),
            'canInstall' => $canInstall,
            'urls'       => [
                'install'   => Route::_($base . 'extensions.install', false),
                'uninstall' => Route::_($base . 'extensions.uninstall', false),
                'toggle'    => Route::_($base . 'extensions.toggle', false),
                'changelog' => Route::_($base . 'extensions.changelog', false),
            ],
            'items'      => $slim,
        ]);

        foreach (
            [
            'COM_CSEXTENSIONMANAGER_JS_QUEUE_TITLE',
            'COM_CSEXTENSIONMANAGER_JS_QUEUE_RUNNING',
            'COM_CSEXTENSIONMANAGER_JS_QUEUE_DONE',
            'COM_CSEXTENSIONMANAGER_JS_QUEUE_DONE_ERRORS',
            'COM_CSEXTENSIONMANAGER_JS_WAITING',
            'COM_CSEXTENSIONMANAGER_JS_WORKING',
            'COM_CSEXTENSIONMANAGER_JS_INSTALLING',
            'COM_CSEXTENSIONMANAGER_JS_UNINSTALLING',
            'COM_CSEXTENSIONMANAGER_JS_DEPENDENCY',
            'COM_CSEXTENSIONMANAGER_JS_NETWORK_ERROR',
            'COM_CSEXTENSIONMANAGER_JS_SELF_RELOAD',
            'COM_CSEXTENSIONMANAGER_JS_CONFIRM_UNINSTALL',
            'COM_CSEXTENSIONMANAGER_JS_CONFIRM_UNINSTALL_DEPENDANTS',
            'COM_CSEXTENSIONMANAGER_JS_CONFIRM_VERSION',
            'COM_CSEXTENSIONMANAGER_JS_CHANGELOG_TITLE',
            'COM_CSEXTENSIONMANAGER_JS_CHANGELOG_EMPTY',
            'COM_CSEXTENSIONMANAGER_JS_NEW_SINCE',
            'COM_CSEXTENSIONMANAGER_JS_INSTALLED_HERE',
            'COM_CSEXTENSIONMANAGER_JS_INSTALL_THIS',
            'COM_CSEXTENSIONMANAGER_JS_LOADING',
            'COM_CSEXTENSIONMANAGER_JS_SELECTED',
            'COM_CSEXTENSIONMANAGER_JS_NOTHING_TO_UPDATE',
            'COM_CSEXTENSIONMANAGER_JS_TOGGLE_FAILED',
            'COM_CSEXTENSIONMANAGER_BTN_ENABLE',
            'COM_CSEXTENSIONMANAGER_BTN_DISABLE',
            'JCLOSE',
            'JCANCEL',
            ] as $string
        ) {
            Text::script($string);
        }
    }

    /**
     * The "Need a hand?" panel at the foot of every view (built-in support rule).
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function supportPanel(): string
    {
        $sentence = self::e(SupportHelper::getContactSentence());
        $title    = self::e(Text::_('COM_CSEXTENSIONMANAGER_SUPPORT_TITLE'));
        $links    = [];

        foreach (
            [
            [SupportHelper::DOCS_URL, 'COM_CSEXTENSIONMANAGER_SUPPORT_DOCS'],
            [SupportHelper::ISSUES_URL, 'COM_CSEXTENSIONMANAGER_SUPPORT_REPORT_BUG'],
            [SupportHelper::getUrl(), 'COM_CSEXTENSIONMANAGER_SUPPORT_GET_HELP'],
            ] as [$url, $label]
        ) {
            if ($url !== '') {
                $links[] = '<a href="' . self::e($url) . '" target="_blank" rel="noopener noreferrer">' . self::e(Text::_($label)) . '</a>';
            }
        }

        $version = self::e(Text::sprintf('COM_CSEXTENSIONMANAGER_VERSION_LINE', StoreClient::ownVersion()));

        return '<div class="card csem-support mt-4"><div class="card-body small">'
            . '<h2 class="h6">' . $title . '</h2>'
            . '<p class="mb-1">' . $sentence . '</p>'
            . '<p class="mb-0">' . implode(' &middot; ', $links) . ' &middot; <span class="text-body-secondary">' . $version . '</span></p>'
            . '</div></div>';
    }
}
