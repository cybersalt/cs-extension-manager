<?php

/**
 * @package    pkg_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

return new class () implements InstallerScriptInterface {
    /**
     * Minimum PHP version (Joomla 5's floor).
     */
    private const MIN_PHP = '8.1.0';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        // Our scheduled task would fail every run once the plugin is gone.
        $this->safely(fn () => $this->removeTasks(Factory::getContainer()->get(DatabaseInterface::class)));

        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $lang = Factory::getApplication()->getLanguage();
        $lang->load('pkg_csextensionmanager.sys', JPATH_ADMINISTRATOR, null, true);
        $lang->load('pkg_csextensionmanager.sys', $adapter->getParent()->getPath('source'), null, true);

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $this->error(Text::sprintf('PKG_CSEXTENSIONMANAGER_ERROR_PHP', self::MIN_PHP, PHP_VERSION), \sprintf('Cybersalt Extension Manager needs PHP %s or later (this server runs %s).', self::MIN_PHP, PHP_VERSION));

            return false;
        }

        if (version_compare(JVERSION, '5.0.0', '<')) {
            $this->error(Text::sprintf('PKG_CSEXTENSIONMANAGER_ERROR_JOOMLA', JVERSION), \sprintf('Cybersalt Extension Manager needs Joomla 5 or 6 (this site runs %s).', JVERSION));

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // Joomla also calls postflight() on uninstall - no card, no side effects then.
        if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        // The package .sys.ini was copied after the Language object was built
        // (Joomla-Brain gotcha #24) - load it so the card isn't raw constants.
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('pkg_csextensionmanager.sys', JPATH_ADMINISTRATOR, null, true);
        $lang->load('com_csextensionmanager', JPATH_ADMINISTRATOR, null, true);

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // New namespaces must resolve on the next request.
        $map = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

        if (is_file($map)) {
            @unlink($map);
        }

        // The component's classes, before Joomla's PSR-4 map knows about them.
        \JLoader::registerNamespace(
            'Cybersalt\\Component\\Csextensionmanager\\Administrator',
            JPATH_ADMINISTRATOR . '/components/com_csextensionmanager/src'
        );

        $ns = 'Cybersalt\\Component\\Csextensionmanager\\Administrator\\Helper\\';

        if ($type !== 'update') {
            foreach (['installer', 'quickicon', 'task'] as $folder) {
                $this->safely(fn () => $this->enablePlugin($db, $folder, 'csextensionmanager'));
            }

            $this->safely(fn () => $this->seedPermissions($db));

            // Email updates are opt-in (Tim, 2026-10-20): the task is created
            // switched off and the card offers to switch it on.
            $this->safely(fn () => ($ns . 'TaskHelper')::create(false));
        }

        // Put whatever download ID the site already has (e.g. MCP for Joomla's)
        // onto the Cybersalt update sites, including the one just installed.
        $mcpforj = false;
        $this->safely(function () use (&$mcpforj, $ns) {
            $helper  = $ns . 'AccountHelper';
            $mcpforj = $helper::getSource() === $helper::SOURCE_MCPFORJ;
            $helper::syncUpdateSites();
        });

        $emailsOn = true;
        $this->safely(function () use (&$emailsOn, $ns) {
            $emailsOn = ($ns . 'TaskHelper')::isEnabled();
        });

        $this->renderInstallCard($type, $mcpforj, $emailsOn);

        // Joomla shows the manifest description in its own box above the card,
        // and the card already leads with it.
        $adapter->getParent()->message = '';

        return true;
    }

    /**
     * Enable a plugin if it's installed and currently disabled.
     */
    private function enablePlugin(DatabaseInterface $db, string $folder, string $element): bool
    {
        $db->setQuery(
            $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = :folder')
                ->where($db->quoteName('element') . ' = :element')
                ->where($db->quoteName('enabled') . ' = 0')
                ->bind(':folder', $folder)
                ->bind(':element', $element)
        )->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Managers and Administrators may look; installing stays with Super Users
     * (and anyone the site owner grants it to), unless the site already has rules.
     */
    private function seedPermissions(DatabaseInterface $db): void
    {
        $asset = $db->setQuery(
            $db->createQuery()
                ->select([$db->quoteName('id'), $db->quoteName('rules')])
                ->from($db->quoteName('#__assets'))
                ->where($db->quoteName('name') . ' = ' . $db->quote('com_csextensionmanager'))
        )->loadObject();

        if (!$asset) {
            return;
        }

        $rules = json_decode((string) $asset->rules, true) ?: [];

        if (isset($rules['csextensionmanager.view'])) {
            return;
        }

        $groups = $db->setQuery(
            $db->createQuery()
                ->select([$db->quoteName('id'), $db->quoteName('title')])
                ->from($db->quoteName('#__usergroups'))
                ->whereIn($db->quoteName('title'), ['Manager', 'Administrator'], ParameterType::STRING)
        )->loadAssocList('title', 'id');

        $view = [];

        foreach ($groups as $id) {
            $view[(string) $id] = 1;
        }

        $rules['csextensionmanager.view']    = (object) $view;
        $rules['csextensionmanager.install'] = (object) [];
        $rules['csextensionmanager.fix']     = (object) [];

        $json    = json_encode($rules);
        $assetId = (int) $asset->id;

        $db->setQuery(
            $db->createQuery()
                ->update($db->quoteName('#__assets'))
                ->set($db->quoteName('rules') . ' = :rules')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':rules', $json)
                ->bind(':id', $assetId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Remove this package's scheduled tasks.
     */
    private function removeTasks(DatabaseInterface $db): void
    {
        $ids = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__scheduler_tasks'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('csextensionmanager.check'))
        )->loadColumn() ?: [];

        if ($ids === []) {
            return;
        }

        $table = Factory::getApplication()->bootComponent('com_scheduler')->getMVCFactory()->createTable('Task', 'Administrator');

        foreach ($ids as $id) {
            $table->delete((int) $id);
        }
    }

    /**
     * Run a non-essential install step; never let it fail the install.
     */
    private function safely(callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            // The health check points at anything that didn't happen.
        }
    }

    /**
     * Error message during preflight, before our language file may be there.
     */
    private function error(string $translated, string $fallback): void
    {
        $text = str_starts_with($translated, 'PKG_') ? $fallback : $translated;

        Factory::getApplication()->enqueueMessage(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), 'error');
    }

    /**
     * Cybersalt post-install card (JOOMLA-EXTENSION-WISHLIST.md spec).
     */
    private function renderInstallCard(string $type, bool $mcpforj, bool $emailsOn): void
    {
        $e = static fn (string $key): string => htmlspecialchars(Text::_($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title       = $type === 'update' ? $e('PKG_CSEXTENSIONMANAGER_CARD_TITLE_UPDATE') : $e('PKG_CSEXTENSIONMANAGER_CARD_TITLE_INSTALL');
        // Description carries the vendor <strong><a> prefix, so it's not escaped (Brain spec).
        $description = Text::_('PKG_CSEXTENSIONMANAGER_XML_DESCRIPTION');
        $nextSteps   = $type === 'update' ? '' : '<p class="mb-3">' . $e('PKG_CSEXTENSIONMANAGER_CARD_NEXT_STEPS') . '</p>';
        // Opt-in for release emails: a GET link with the token, because the card sits inside the installer's form.
        $emailsUrl   = htmlspecialchars('index.php?option=com_csextensionmanager&task=extensions.enableemails&' . Session::getFormToken() . '=1', ENT_QUOTES, 'UTF-8');
        $taskNote    = $emailsOn ? '' : '<div class="alert alert-info mb-3">' . $e('PKG_CSEXTENSIONMANAGER_CARD_EMAILS_OFFER')
            . ' <a class="btn btn-sm btn-primary ms-1" href="' . $emailsUrl . '">' . $e('PKG_CSEXTENSIONMANAGER_CARD_EMAILS_BUTTON') . '</a></div>';
        $mcpNote     = $mcpforj && $type !== 'update' ? '<p class="mb-3">' . $e('PKG_CSEXTENSIONMANAGER_CARD_MCPFORJ') . '</p>' : '';

        $btnOpen    = $e('PKG_CSEXTENSIONMANAGER_CARD_OPEN');
        $btnHealth  = $e('PKG_CSEXTENSIONMANAGER_CARD_HEALTH');
        $btnOptions = $e('PKG_CSEXTENSIONMANAGER_CARD_OPTIONS');
        $support    = $e('PKG_CSEXTENSIONMANAGER_CARD_SUPPORT');
        $reportBug  = $e('PKG_CSEXTENSIONMANAGER_CARD_REPORT_BUG');

        $openUrl    = 'index.php?option=com_csextensionmanager&amp;view=extensions';
        $healthUrl  = 'index.php?option=com_csextensionmanager&amp;view=health';
        $optionsUrl = 'index.php?option=com_config&amp;view=component&amp;component=com_csextensionmanager';
        $issuesUrl  = 'https://github.com/cybersalt/cs-extension-manager/issues';
        $logoUrl    = htmlspecialchars(Uri::root() . 'media/com_csextensionmanager/images/logo.svg', ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<style>
.cs-install-card {
    --cs-header-bg: #fff;
    --cs-header-title: #0102E1;
    --cs-header-border: rgba(0, 0, 0, 0.1);
}
html[data-bs-theme="dark"] .cs-install-card,
html[data-color-scheme="dark"] .cs-install-card {
    --cs-header-bg: #1f2937;
    --cs-header-title: #FE9904;
    --cs-header-border: rgba(255, 255, 255, 0.1);
}
.cs-install-card .cs-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    background-color: var(--cs-header-bg);
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid var(--cs-header-border);
}
.cs-install-card .cs-card-header img {
    height: 56px;
    width: 56px;
    flex: 0 0 56px;
}
html[data-bs-theme="dark"] .cs-install-card .cs-card-header img,
html[data-color-scheme="dark"] .cs-install-card .cs-card-header img {
    background-color: #fff;
    border-radius: 50%;
    padding: 6px;
    box-sizing: content-box;
}
.cs-install-card .cs-card-header h3 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--cs-header-title);
}
.cs-install-card a.cs-cybersalt-btn,
.cs-install-card a.cs-cybersalt-btn:link,
.cs-install-card a.cs-cybersalt-btn:visited {
    background-color: #dc6b1a;
    border-color: #dc6b1a;
    color: #fff !important;
}
.cs-install-card a.cs-cybersalt-btn:hover,
.cs-install-card a.cs-cybersalt-btn:focus,
.cs-install-card a.cs-cybersalt-btn:active {
    background-color: #b85614;
    border-color: #b85614;
    color: #fff !important;
}
</style>
<div class="card my-3 cs-install-card">
    <div class="card-header cs-card-header">
        <img src="{$logoUrl}" alt="" />
        <h3>{$title}</h3>
    </div>
    <div class="card-body">
        <p class="lead mb-3">{$description}</p>
        {$nextSteps}
        {$mcpNote}
        {$taskNote}
        <p class="mb-0 d-flex flex-wrap gap-2">
            <a class="btn btn-sm cs-cybersalt-btn" href="{$openUrl}">{$btnOpen}</a>
            <a class="btn btn-sm cs-cybersalt-btn" href="{$healthUrl}">{$btnHealth}</a>
            <a class="btn btn-sm cs-cybersalt-btn" href="{$optionsUrl}">{$btnOptions}</a>
        </p>
        <hr>
        <p class="text-body-secondary small mb-0">
            {$support}
            &middot;
            <a href="https://www.cybersalt.com" target="_blank" rel="noopener noreferrer">Cybersalt</a>
            &middot;
            <a href="mailto:support@cybersalt.com">support@cybersalt.com</a>
            &middot;
            <a href="{$issuesUrl}" target="_blank" rel="noopener noreferrer">{$reportBug}</a>
        </p>
    </div>
</div>
HTML;
    }
};
