<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Dashboard\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\TemplatesHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_csextensionmanager');
$wa->useStyle('com_csextensionmanager.admin')->useScript('com_csextensionmanager.admin');

HTMLHelper::_('bootstrap.modal');

DisplayHelper::scriptOptions($this->getDocument(), $this->items, $this->canInstall);

$url = static fn (string $query): string => Route::_('index.php?option=com_csextensionmanager&' . $query, false);

$templateCount = TemplatesHelper::count($this->templates['brands']);
$brandNames    = implode(' · ', array_column($this->templates['brands'], 'name'));
$updates       = (int) $this->counts['update'];
$last          = $this->recent[0] ?? null;

$tiles = [
    [
        'href'  => $url('view=extensions'),
        'icon'  => 'icon-puzzle-piece',
        'title' => Text::_('COM_CSEXTENSIONMANAGER_SUBMENU_EXTENSIONS'),
        'value' => (int) $this->counts['installed'],
        'unit'  => Text::_('COM_CSEXTENSIONMANAGER_DASH_INSTALLED'),
        'line'  => $updates > 0
            ? Text::plural('COM_CSEXTENSIONMANAGER_DASH_UPDATES_N', $updates)
            : Text::sprintf('COM_CSEXTENSIONMANAGER_DASH_MORE_N', (int) $this->counts['available']),
        'tone'  => $updates > 0 ? 'success' : 'primary',
    ],
    [
        'href'  => $url('view=templates'),
        'icon'  => 'icon-paint-brush',
        'title' => Text::_('COM_CSEXTENSIONMANAGER_SUBMENU_TEMPLATES'),
        'value' => $templateCount,
        'unit'  => Text::_('COM_CSEXTENSIONMANAGER_DASH_TEMPLATES'),
        'line'  => $brandNames,
        'tone'  => 'brand',
    ],
    [
        'href'  => $url('view=health'),
        'icon'  => 'icon-heart',
        'title' => Text::_('COM_CSEXTENSIONMANAGER_SUBMENU_HEALTH'),
        'value' => $this->issueCount,
        'unit'  => Text::_('COM_CSEXTENSIONMANAGER_DASH_ISSUES'),
        'line'  => Text::_($this->issueCount === 0
            ? 'COM_CSEXTENSIONMANAGER_DASH_HEALTHY'
            : ($this->issueNotesOnly ? 'COM_CSEXTENSIONMANAGER_DASH_ISSUES_NOTES' : 'COM_CSEXTENSIONMANAGER_DASH_ISSUES_FIX')),
        'tone'  => $this->issueTone,
    ],
    [
        'href'  => $url('view=logs'),
        'icon'  => 'icon-list',
        'title' => Text::_('COM_CSEXTENSIONMANAGER_SUBMENU_LOGS'),
        'value' => null,
        'unit'  => '',
        'line'  => $last !== null
            ? Text::sprintf('COM_CSEXTENSIONMANAGER_DASH_LAST', Text::_('COM_CSEXTENSIONMANAGER_ACTION_' . strtoupper((string) $last->action)), (string) $last->title, HTMLHelper::_('date.relative', (string) $last->created))
            : Text::_('COM_CSEXTENSIONMANAGER_DASH_NO_HISTORY'),
        'tone'  => 'secondary',
    ],
];
?>
<div class="csem csem-dashboard" id="csem">

    <?php echo DisplayHelper::navBar('dashboard', ['extensions' => $updates, 'health' => $this->issueCount], ['health' => DisplayHelper::healthBadgeClass($this->issueTone)]); ?>

    <div class="row g-3 mb-4">
        <?php foreach ($tiles as $tile) : ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="card h-100 text-decoration-none csem-tile csem-tone-<?php echo $tile['tone']; ?>" href="<?php echo $this->escape($tile['href']); ?>">
                    <div class="card-body d-flex gap-3 align-items-start">
                        <span class="csem-tile-icon <?php echo $tile['icon']; ?>" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <h2 class="h5 mb-1"><?php echo $this->escape($tile['title']); ?></h2>
                            <?php if ($tile['value'] !== null) : ?>
                                <p class="mb-1"><span class="csem-tile-value"><?php echo (int) $tile['value']; ?></span> <span class="text-body-secondary"><?php echo $this->escape($tile['unit']); ?></span></p>
                            <?php endif; ?>
                            <p class="small text-body-secondary mb-0"><?php echo $this->escape($tile['line']); ?></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h5 mb-0 flex-grow-1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SECTION_ATTENTION')); ?></h2>
                    <?php if ($this->canInstall && $updates > 0) : ?>
                        <button type="button" class="btn btn-sm btn-success" data-csem-update-all>
                            <span class="icon-upload" aria-hidden="true"></span>
                            <?php echo $this->escape(Text::plural('COM_CSEXTENSIONMANAGER_BTN_UPDATE_ALL_N', $updates)); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($this->attention === []) : ?>
                    <div class="card-body">
                        <p class="mb-2"><span class="icon-check text-success" aria-hidden="true"></span>
                            <?php echo $this->escape(Text::plural('COM_CSEXTENSIONMANAGER_DASH_ALL_CURRENT_N', (int) $this->counts['installed'])); ?></p>
                        <a href="<?php echo $this->escape($url('view=extensions')); ?>#filter=available"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DASH_BROWSE_EXTENSIONS')); ?></a>
                    </div>
                <?php else : ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($this->attention as $item) : ?>
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                                <img class="csem-icon csem-icon-sm" src="<?php echo $this->escape(CatalogHelper::iconSrc($item)); ?>" width="28" height="28" alt="" loading="lazy">
                                <div class="flex-grow-1 min-w-0">
                                    <strong><?php echo $this->escape($item['title']); ?></strong>
                                    <span class="small text-body-secondary"><?php echo $this->escape($item['installed_version']); ?> → <?php echo $this->escape($item['latest_version']); ?></span>
                                </div>
                                <?php echo DisplayHelper::primaryButton($item, DisplayHelper::primaryAction($item, $this->canInstall)); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($this->recent !== []) : ?>
                <section class="card">
                    <div class="card-header d-flex align-items-center gap-2">
                        <h2 class="h5 mb-0 flex-grow-1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DASH_RECENT')); ?></h2>
                        <a class="small" href="<?php echo $this->escape($url('view=logs')); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DASH_RECENT_ALL')); ?></a>
                    </div>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($this->recent as $row) : ?>
                            <li class="list-group-item d-flex flex-wrap gap-2">
                                <span class="badge <?php echo $row->status === 'success' ? 'bg-success' : ($row->status === 'error' ? 'bg-danger' : 'bg-warning text-dark'); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACTION_' . strtoupper((string) $row->action))); ?></span>
                                <span class="flex-grow-1"><?php echo $this->escape(trim($row->title . ' ' . $row->to_version)); ?></span>
                                <span class="text-body-secondary"><?php echo $this->escape(HTMLHelper::_('date.relative', (string) $row->created)); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>

        <div class="col-12 col-lg-5">
            <?php include JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/extensions/default_account.php'; ?>

            <?php if (!$this->emailsOn && $this->canConfigure) : ?>
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center gap-2">
                        <span class="icon-envelope" aria-hidden="true"></span>
                        <span class="flex-grow-1 small"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMAILS_OFFER')); ?></span>
                        <a class="btn btn-sm btn-primary" href="<?php echo $this->escape($url('task=extensions.enableemails&' . Session::getFormToken() . '=1')); ?>">
                            <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMAILS_BUTTON')); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($this->featured !== []) : ?>
        <section class="card mb-4">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <div class="flex-grow-1">
                    <h2 class="h5 mb-0"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DASH_TEMPLATES_TITLE')); ?></h2>
                    <p class="small text-body-secondary fw-normal mb-0"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DASH_TEMPLATES_DESC')); ?></p>
                </div>
                <a class="btn btn-sm btn-primary" href="<?php echo $this->escape($url('view=templates')); ?>">
                    <?php echo $this->escape(Text::plural('COM_CSEXTENSIONMANAGER_DASH_TEMPLATES_ALL_N', $templateCount)); ?>
                </a>
            </div>
            <div class="card-body">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3">
                    <?php foreach ($this->featured as $tpl) : ?>
                        <div class="col">
                            <?php include JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/templates/default_card.php'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <p class="small text-body-secondary"><?php echo $this->escape(DisplayHelper::catalogLine($this->catalog)); ?></p>

    <?php echo DisplayHelper::supportPanel(); ?>
</div>

<?php include JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/extensions/default_modals.php'; ?>
