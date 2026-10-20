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
use Joomla\CMS\Session\Session;

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_csextensionmanager');
$wa->useStyle('com_csextensionmanager.admin')->useScript('com_csextensionmanager.admin');

HTMLHelper::_('bootstrap.modal');
HTMLHelper::_('bootstrap.dropdown');
HTMLHelper::_('bootstrap.collapse');

DisplayHelper::scriptOptions($this->getDocument(), $this->items, $this->canInstall);

$sections = [
    'attention' => ['COM_CSEXTENSIONMANAGER_SECTION_ATTENTION', 'COM_CSEXTENSIONMANAGER_SECTION_ATTENTION_DESC'],
    'installed' => ['COM_CSEXTENSIONMANAGER_SECTION_INSTALLED', 'COM_CSEXTENSIONMANAGER_SECTION_INSTALLED_DESC'],
    'products'  => ['COM_CSEXTENSIONMANAGER_SECTION_PRODUCTS', 'COM_CSEXTENSIONMANAGER_SECTION_PRODUCTS_DESC'],
    'addons'    => ['COM_CSEXTENSIONMANAGER_SECTION_ADDONS', 'COM_CSEXTENSIONMANAGER_SECTION_ADDONS_DESC'],
];

$healthUrl = Route::_('index.php?option=com_csextensionmanager&view=health', false);
$refresh   = Route::_('index.php?option=com_csextensionmanager&task=extensions.refresh&' . Session::getFormToken() . '=1', false);

// Stat cards: every card filters the list (Cybersalt "hotlinked stats" rule).
$cards = [
    ['filter' => 'installed', 'count' => $this->counts['installed'], 'label' => 'COM_CSEXTENSIONMANAGER_STAT_INSTALLED', 'tone' => ''],
    ['filter' => 'update',    'count' => $this->counts['update'],    'label' => 'COM_CSEXTENSIONMANAGER_STAT_UPDATES',   'tone' => $this->counts['update'] > 0 ? 'text-success' : ''],
    ['filter' => 'available', 'count' => $this->counts['available'], 'label' => 'COM_CSEXTENSIONMANAGER_STAT_AVAILABLE', 'tone' => ''],
    ['filter' => 'addon',     'count' => $this->counts['addons'],    'label' => 'COM_CSEXTENSIONMANAGER_STAT_ADDONS',    'tone' => ''],
];
?>
<div class="csem" id="csem">

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
                    <div class="h3 mb-0 <?php echo $this->issueCount > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo (int) $this->issueCount; ?></div>
                    <div class="small text-body-secondary"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STAT_ISSUES')); ?></div>
                </div>
            </a>
        </div>
    </div>

    <?php echo $this->loadTemplate('account'); ?>

    <?php if (!$this->emailsOn && $this->canConfigure) : ?>
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
            <span class="icon-envelope" aria-hidden="true"></span>
            <span class="flex-grow-1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMAILS_OFFER')); ?></span>
            <a class="btn btn-sm btn-primary" href="<?php echo $this->escape(Route::_('index.php?option=com_csextensionmanager&task=extensions.enableemails&' . Session::getFormToken() . '=1', false)); ?>">
                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMAILS_BUTTON')); ?>
            </a>
        </div>
    <?php endif; ?>

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
                <button type="button" class="btn btn-sm btn-success ms-auto" data-csem-run-selected disabled>
                    <span class="icon-download" aria-hidden="true"></span>
                    <span data-csem-selected-label><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_RUN_SELECTED', 0)); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php foreach ($sections as $section => [$titleKey, $descKey]) : ?>
        <?php if ($this->sections[$section] === []) {
            continue;
        } ?>
        <section class="csem-section mb-4" data-csem-section="<?php echo $this->escape($section); ?>">
            <h2 class="h4 mb-1"><?php echo $this->escape(Text::_($titleKey)); ?>
                <span class="badge bg-secondary align-middle" data-csem-section-count><?php echo count($this->sections[$section]); ?></span>
            </h2>
            <p class="small text-body-secondary mb-2"><?php echo $this->escape(Text::_($descKey)); ?></p>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3">
                <?php foreach ($this->sections[$section] as $item) : ?>
                    <?php $this->currentItem = $item; ?>
                    <?php $this->currentSection = $section; ?>
                    <?php echo $this->loadTemplate('card'); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="alert alert-info d-none" data-csem-empty>
        <span class="icon-info-circle" aria-hidden="true"></span>
        <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_EMPTY_FILTERED')); ?>
    </div>

    <p class="small text-body-secondary mt-3">
        <?php echo $this->escape(DisplayHelper::catalogLine($this->catalog)); ?>
        &middot;
        <a href="<?php echo $this->escape($refresh); ?>" class="csem-busy-link"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_REFRESH')); ?></a>
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
