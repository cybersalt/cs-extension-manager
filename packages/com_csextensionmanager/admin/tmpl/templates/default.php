<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Templates\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_csextensionmanager');
$wa->useStyle('com_csextensionmanager.admin')->useScript('com_csextensionmanager.admin');

Text::script('COM_CSEXTENSIONMANAGER_TEMPLATES_SHOWING');

$newTab = ' <span class="visually-hidden">' . $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_OPENS_NEW')) . '</span>';
$total  = \count($this->all);
$source = $this->templates['source'] === 'bundled' || (int) $this->templates['fetched'] <= 0
    ? Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_SOURCE_BUNDLED')
    : Text::sprintf('COM_CSEXTENSIONMANAGER_TEMPLATES_SOURCE_STORE', HTMLHelper::_('date.relative', gmdate('Y-m-d H:i:s', (int) $this->templates['fetched'])));

// The shop extensions and Joomla versions that appear, for the filters.
$shops    = [];
$versions = [];

foreach ($this->all as $tpl) {
    foreach (['virtuemart' => 'VirtueMart', 'hikashop' => 'HikaShop'] as $value => $label) {
        if (stripos($tpl['shop'], $value) !== false) {
            $shops[$value] = $label;
        }
    }

    $versions += array_combine($tpl['joomla'], $tpl['joomla']) ?: [];
}

krsort($versions);
?>
<div class="csem csem-templates" id="csem">

    <?php echo DisplayHelper::navBar('templates'); ?>

    <?php if ($total === 0) : ?>
        <div class="alert alert-info"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_NONE')); ?></div>
    <?php else : ?>

        <div class="row g-3 mb-3">
            <?php foreach ($this->templates['brands'] as $brand) : ?>
                <div class="col-12 col-lg-6">
                    <div class="card h-100 csem-brand">
                        <div class="card-body d-flex flex-wrap align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <h2 class="h5 mb-0"><?php echo $this->escape($brand['name']); ?>
                                    <span class="badge bg-secondary align-middle"><?php echo \count($brand['templates']); ?></span>
                                </h2>
                                <?php if ($brand['description'] !== '') : ?>
                                    <p class="small text-body-secondary mb-0"><?php echo $this->escape($brand['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-sm btn-secondary" href="<?php echo $this->escape($brand['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_VISIT_SITE')); ?><?php echo $newTab; ?>
                                </a>
                                <?php if ($brand['plans_url'] !== '') : ?>
                                    <a class="btn btn-sm csem-btn-get" href="<?php echo $this->escape($brand['plans_url']); ?>" target="_blank" rel="noopener">
                                        <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_PLANS')); ?><?php echo $newTab; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card card-body mb-3 csem-tpl-filters">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-4">
                    <label class="visually-hidden" for="csem-tpl-search"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_SEARCH')); ?></label>
                    <input type="search" class="form-control" id="csem-tpl-search" placeholder="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_SEARCH')); ?>" autocomplete="off">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="csem-tpl-brand"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_BRAND')); ?></label>
                    <select class="form-select" id="csem-tpl-brand" data-csem-tpl-filter="brand">
                        <option value=""><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_BRAND_ANY')); ?></option>
                        <?php foreach ($this->templates['brands'] as $brand) : ?>
                            <option value="<?php echo $this->escape($brand['key']); ?>"><?php echo $this->escape($brand['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="csem-tpl-joomla"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_JOOMLA')); ?></label>
                    <select class="form-select" id="csem-tpl-joomla" data-csem-tpl-filter="joomla">
                        <option value=""><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_JOOMLA_ANY')); ?></option>
                        <?php foreach ($versions as $version) : ?>
                            <option value="<?php echo $this->escape($version); ?>"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_TEMPLATES_JOOMLA', $version)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="csem-tpl-shop"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_SHOP')); ?></label>
                    <select class="form-select" id="csem-tpl-shop" data-csem-tpl-filter="shop">
                        <option value=""><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_SHOP_ANY')); ?></option>
                        <option value="none"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_FILTER_SHOP_NONE')); ?></option>
                        <?php foreach ($shops as $value => $label) : ?>
                            <option value="<?php echo $this->escape($value); ?>"><?php echo $this->escape($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="csem-tpl-sort"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SORT_BY')); ?></label>
                    <select class="form-select" id="csem-tpl-sort">
                        <option value="name"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SORT_NAME_AZ')); ?></option>
                        <option value="-name"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SORT_NAME_ZA')); ?></option>
                        <option value="brand"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SORT_BRAND')); ?></option>
                        <option value="joomla"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SORT_JOOMLA_NEWEST')); ?></option>
                    </select>
                </div>
            </div>

            <?php if ($this->templates['topics'] !== []) : ?>
                <div class="csem-topics d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" role="group" aria-label="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_TOPICS')); ?>">
                    <span class="small fw-semibold me-1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_TOPICS')); ?></span>
                    <button type="button" class="btn btn-sm csem-topic active" data-csem-topic="" aria-pressed="true"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_TOPIC_ALL')); ?></button>
                    <?php foreach ($this->templates['topics'] as $slug => $label) : ?>
                        <button type="button" class="btn btn-sm csem-topic" data-csem-topic="<?php echo $this->escape($slug); ?>" aria-pressed="false">
                            <?php echo $this->escape($label); ?> <span class="csem-topic-count"><?php echo (int) ($this->topicCounts[$slug] ?? 0); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <p class="mb-0 small text-body-secondary flex-grow-1" data-csem-tpl-showing aria-live="polite"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_TEMPLATES_SHOWING', $total, $total)); ?></p>
            <button type="button" class="btn btn-sm btn-link" data-csem-tpl-clear><?php echo $this->escape(Text::_('JSEARCH_FILTER_CLEAR')); ?></button>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-3" data-csem-tpl-grid>
            <?php foreach ($this->all as $tpl) : ?>
                <?php
                $shopKey = [];

                foreach (['virtuemart', 'hikashop'] as $value) {
                    if (stripos($tpl['shop'], $value) !== false) {
                        $shopKey[] = $value;
                    }
                }
                ?>
                <div class="col" data-csem-tpl
                     data-name="<?php echo $this->escape(mb_strtolower($tpl['name'])); ?>"
                     data-brand="<?php echo $this->escape($tpl['brand']); ?>"
                     data-joomla="<?php echo $this->escape(implode(' ', $tpl['joomla'])); ?>"
                     data-shop="<?php echo $this->escape($shopKey !== [] ? implode(' ', $shopKey) : 'none'); ?>"
                     data-tags="<?php echo $this->escape(implode(' ', $tpl['tags'])); ?>"
                     data-search="<?php echo $this->escape(mb_strtolower(implode(' ', [$tpl['name'], $tpl['tagline'], $tpl['shop'], $tpl['brand_name']]))); ?>">
                    <?php include __DIR__ . '/default_card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-info d-none mt-3" data-csem-tpl-empty>
            <span class="icon-info-circle" aria-hidden="true"></span>
            <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_EMPTY')); ?>
        </div>

        <p class="small text-body-secondary mt-3 mb-0"><?php echo $this->escape($source); ?></p>
    <?php endif; ?>

    <?php echo DisplayHelper::supportPanel(); ?>
</div>
