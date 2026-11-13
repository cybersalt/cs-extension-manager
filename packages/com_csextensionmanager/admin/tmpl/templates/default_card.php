<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * One template card. Included with $tpl set (a TemplatesHelper::flatten()
 * row); used by the Templates view and the dashboard. Every link opens the
 * brand site in a new tab.
 *
 * @var \Joomla\CMS\MVC\View\HtmlView $this
 * @var array<string, mixed>          $tpl
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$csemNewTab = ' <span class="visually-hidden">' . $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_OPENS_NEW')) . '</span>';
?>
<div class="card h-100 csem-tpl-card">
    <a class="csem-tpl-shot" href="<?php echo $this->escape($tpl['demo_url'] !== '' ? $tpl['demo_url'] : $tpl['url']); ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
        <?php if ($tpl['image'] !== '') : ?>
            <img src="<?php echo $this->escape($tpl['image']); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
        <?php else : ?>
            <span class="csem-tpl-noshot"><?php echo $this->escape($tpl['name']); ?></span>
        <?php endif; ?>
    </a>
    <div class="card-body pb-2">
        <h3 class="h6 card-title mb-0"><?php echo $this->escape($tpl['name']); ?></h3>
        <p class="small text-body-secondary mb-1"><?php echo $this->escape($tpl['brand_name']); ?></p>
        <?php if ($tpl['tagline'] !== '') : ?>
            <p class="small mb-2"><?php echo $this->escape($tpl['tagline']); ?></p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-1">
            <?php foreach ($tpl['joomla'] as $version) : ?>
                <span class="badge bg-info-subtle text-info-emphasis"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_TEMPLATES_JOOMLA', $version)); ?></span>
            <?php endforeach; ?>
            <?php if ($tpl['shop'] !== '') : ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis"><?php echo $this->escape($tpl['shop']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <?php if ($tpl['demo_url'] !== '') : ?>
            <a class="btn btn-sm btn-primary" href="<?php echo $this->escape($tpl['demo_url']); ?>" target="_blank" rel="noopener">
                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_DEMO')); ?><?php echo $csemNewTab; ?>
            </a>
        <?php endif; ?>
        <a class="btn btn-sm btn-secondary" href="<?php echo $this->escape($tpl['url']); ?>" target="_blank" rel="noopener">
            <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TEMPLATES_DETAILS')); ?><?php echo $csemNewTab; ?>
        </a>
    </div>
</div>
