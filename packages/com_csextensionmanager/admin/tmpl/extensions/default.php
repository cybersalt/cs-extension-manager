<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Extensions\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_csextensionmanager');
$wa->useStyle('com_csextensionmanager.admin')->useScript('com_csextensionmanager.admin');

HTMLHelper::_('bootstrap.modal');
HTMLHelper::_('bootstrap.dropdown');

DisplayHelper::scriptOptions($this->getDocument(), $this->items, $this->canInstall);
Text::script('COM_CSEXTENSIONMANAGER_SHOWING_N');

$healthUrl = Route::_('index.php?option=com_csextensionmanager&view=health', false);

// Stat cards: every card filters the list (Cybersalt "hotlinked stats" rule).
$cards = [
    ['filter' => 'installed', 'count' => $this->counts['installed'], 'label' => 'COM_CSEXTENSIONMANAGER_STAT_INSTALLED', 'tone' => ''],
    ['filter' => 'update',    'count' => $this->counts['update'],    'label' => 'COM_CSEXTENSIONMANAGER_STAT_UPDATES',   'tone' => $this->counts['update'] > 0 ? 'text-success' : ''],
    ['filter' => 'available', 'count' => $this->counts['available'], 'label' => 'COM_CSEXTENSIONMANAGER_STAT_AVAILABLE', 'tone' => ''],
    ['filter' => 'addon',     'count' => $this->counts['addons'],    'label' => 'COM_CSEXTENSIONMANAGER_STAT_ADDONS',    'tone' => ''],
];

// Sortable columns: [sort key, label, extra th classes].
$columns = [
    ['name',     'COM_CSEXTENSIONMANAGER_COL_EXTENSION', ''],
    ['status',   'COM_CSEXTENSIONMANAGER_COL_STATUS', ''],
    ['version',  'COM_CSEXTENSIONMANAGER_COL_INSTALLED', 'd-none d-md-table-cell'],
    ['released', 'COM_CSEXTENSIONMANAGER_COL_LATEST', 'd-none d-md-table-cell'],
];
?>
<div class="csem" id="csem">

    <?php echo DisplayHelper::navBar('extensions', ['extensions' => (int) $this->counts['update'], 'health' => $this->issueCount], ['health' => DisplayHelper::healthBadgeClass($this->issueTone)]); ?>

    <?php if ($this->selfUpdate !== null && $this->canInstall) : ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3" role="alert">
            <span class="icon-warning" aria-hidden="true"></span>
            <div class="flex-grow-1">
                <strong><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_SELF_UPDATE_TITLE', $this->selfUpdate['latest_version'])); ?></strong>
                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELF_UPDATE_BODY')); ?>
            </div>
            <button type="button" class="btn btn-success" data-csem-install="<?php echo $this->escape($this->selfUpdate['key']); ?>">
                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELF_UPDATE_BUTTON')); ?>
            </button>
        </div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
        <?php foreach ($cards as $card) : ?>
            <div class="col-6 col-lg">
                <a href="#filter=<?php echo $this->escape($card['filter']); ?>" class="card text-decoration-none h-100 csem-stat-card" data-csem-filter-link="<?php echo $this->escape($card['filter']); ?>">
                    <div class="card-body text-center py-2">
                        <div class="h3 mb-0 <?php echo $card['tone']; ?>"><?php echo (int) $card['count']; ?></div>
                        <div class="small text-body-secondary"><?php echo $this->escape(Text::_($card['label'])); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        <div class="col-6 col-lg">
            <a href="<?php echo $this->escape($healthUrl); ?>" class="card text-decoration-none h-100 csem-stat-card">
                <div class="card-body text-center py-2">
                    <div class="h3 mb-0 csem-text-<?php echo $this->escape($this->issueTone); ?>"><?php echo (int) $this->issueCount; ?></div>
                    <div class="small text-body-secondary"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STAT_ISSUES')); ?></div>
                </div>
            </a>
        </div>
    </div>

    <?php if ($this->catalog['errors'] !== []) : ?>
        <div class="alert alert-warning">
            <strong><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_CATALOG_ERRORS')); ?></strong>
            <ul class="mb-0 small">
                <?php foreach (array_slice($this->catalog['errors'], 0, 8) as $error) : ?>
                    <li><?php echo $this->escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="csem-toolbar card card-body mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-4">
                <label class="visually-hidden" for="csem-search"><?php echo $this->escape(Text::_('JSEARCH_FILTER')); ?></label>
                <div class="input-group">
                    <input type="search" class="form-control" id="csem-search" placeholder="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SEARCH_PLACEHOLDER')); ?>" autocomplete="off">
                    <button type="button" class="btn btn-secondary" data-csem-clear-filters><?php echo $this->escape(Text::_('JSEARCH_FILTER_CLEAR')); ?></button>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label class="visually-hidden" for="csem-filter-status"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_STATUS')); ?></label>
                <select class="form-select" id="csem-filter-status">
                    <option value=""><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_STATUS_ANY')); ?></option>
                    <option value="update"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STATE_UPDATE')); ?></option>
                    <option value="installed"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STATE_INSTALLED')); ?></option>
                    <option value="available"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STATE_AVAILABLE')); ?></option>
                    <option value="broken"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STATE_BROKEN')); ?></option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="visually-hidden" for="csem-filter-group"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_GROUP')); ?></label>
                <select class="form-select" id="csem-filter-group">
                    <option value=""><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_GROUP_ANY')); ?></option>
                    <option value="product"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_GROUP_PRODUCT')); ?></option>
                    <option value="addon"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_GROUP_ADDON')); ?></option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="visually-hidden" for="csem-filter-tier"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_TIER')); ?></label>
                <select class="form-select" id="csem-filter-tier">
                    <option value=""><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_TIER_ANY')); ?></option>
                    <option value="free"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TIER_FREE')); ?></option>
                    <option value="pro"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TIER_PRO')); ?></option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="csem-filter-relevant">
                    <label class="form-check-label small" for="csem-filter-relevant"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FILTER_RELEVANT')); ?></label>
                </div>
            </div>
        </div>

        <?php if ($this->canInstall) : ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 pt-2 border-top">
                <button type="button" class="btn btn-sm btn-secondary" data-csem-select="update"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_UPDATES')); ?></button>
                <button type="button" class="btn btn-sm btn-secondary" data-csem-select="visible"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_VISIBLE')); ?></button>
                <button type="button" class="btn btn-sm btn-secondary" data-csem-select="none"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_NONE')); ?></button>
                <span class="small text-body-secondary ms-1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_SHIFT_HINT')); ?></span>
                <div class="d-flex flex-wrap gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-success" data-csem-run-selected disabled>
                        <span class="icon-download" aria-hidden="true"></span>
                        <span data-csem-selected-label><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_RUN_SELECTED', 0)); ?></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-csem-reinstall-selected disabled>
                        <span class="icon-loop" aria-hidden="true"></span>
                        <span data-csem-reinstall-label><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_REINSTALL_SELECTED', 0)); ?></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-csem-uninstall-selected disabled>
                        <span class="icon-trash" aria-hidden="true"></span>
                        <span data-csem-uninstall-label><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_UNINSTALL_SELECTED', 0)); ?></span>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <p class="small text-body-secondary mb-2" data-csem-showing aria-live="polite"></p>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 csem-table">
                <caption class="visually-hidden"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TABLE_CAPTION')); ?></caption>
                <thead>
                    <tr>
                        <th scope="col" class="csem-col-select">
                            <?php if ($this->canInstall) : ?>
                                <input type="checkbox" class="form-check-input csem-select-all" data-csem-select-all aria-label="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_ALL_SHOWN')); ?>" title="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_ALL_SHOWN')); ?>">
                            <?php else : ?>
                                <span class="visually-hidden"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_COL_SELECT')); ?></span>
                            <?php endif; ?>
                        </th>
                        <?php foreach ($columns as [$sort, $label, $class]) : ?>
                            <th scope="col" class="<?php echo $class; ?>" aria-sort="none">
                                <button type="button" class="csem-sort" data-csem-sort="<?php echo $sort; ?>">
                                    <?php echo $this->escape(Text::_($label)); ?>
                                    <span class="csem-sort-icon" aria-hidden="true"></span>
                                </button>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col" class="text-end"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_COL_ACTIONS')); ?></th>
                    </tr>
                </thead>
                <?php foreach ($this->items as $item) : ?>
                    <?php $this->currentItem = $item; ?>
                    <?php echo $this->loadTemplate('row'); ?>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="alert alert-info d-none" data-csem-empty>
        <span class="icon-info-circle" aria-hidden="true"></span>
        <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMPTY_FILTERED')); ?>
    </div>

    <p class="small text-body-secondary mt-3">
        <?php echo $this->escape(DisplayHelper::catalogLine($this->catalog)); ?>
        <?php if ($this->emailsOn) : ?>
            <br>
            <span class="icon-envelope" aria-hidden="true"></span>
            <?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_EMAILS_ON', $this->emailRecipients !== '' ? $this->emailRecipients : '-')); ?>
            <a href="<?php echo $this->escape(Route::_('index.php?option=com_config&view=component&component=com_csextensionmanager', false)); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMAILS_MANAGE')); ?></a>
        <?php endif; ?>
    </p>

    <?php echo DisplayHelper::supportPanel(); ?>
</div>

<?php echo $this->loadTemplate('modals'); ?>
