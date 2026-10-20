<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Health\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\HealthHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_csextensionmanager');
$wa->useStyle('com_csextensionmanager.admin')->useScript('com_csextensionmanager.admin');

HTMLHelper::_('bootstrap.modal');
Text::script('COM_CSEXTENSIONMANAGER_JS_WORKING');

$fixUrl    = Route::_('index.php?option=com_csextensionmanager&task=health.fix', false);
$fixAllUrl = Route::_('index.php?option=com_csextensionmanager&task=health.fixall', false);
$needsInstall = ['reinstall', 'install_parent', 'enable_extension'];

$severityLabel = [
    HealthHelper::SEVERITY_DANGER  => ['bg-danger', 'COM_CSEXTENSIONMANAGER_SEVERITY_DANGER'],
    HealthHelper::SEVERITY_WARNING => ['bg-warning text-dark', 'COM_CSEXTENSIONMANAGER_SEVERITY_WARNING'],
    HealthHelper::SEVERITY_INFO    => ['bg-primary', 'COM_CSEXTENSIONMANAGER_SEVERITY_INFO'],
];
?>
<div class="csem csem-health" id="csem">

    <div class="row g-2 mb-3">
        <div class="col-6 col-lg">
            <div class="card h-100"><div class="card-body text-center py-2">
                <div class="h3 mb-0"><?php echo (int) $this->installedCount; ?></div>
                <div class="small text-body-secondary"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STAT_CHECKED')); ?></div>
            </div></div>
        </div>
        <?php foreach ($severityLabel as $severity => [$class, $label]) : ?>
            <div class="col-6 col-lg">
                <a class="card text-decoration-none h-100 csem-stat-card" href="#csem-sev-<?php echo $this->escape($severity); ?>">
                    <div class="card-body text-center py-2">
                        <div class="h3 mb-0 <?php echo $this->severityCounts[$severity] > 0 && $severity !== HealthHelper::SEVERITY_INFO ? 'text-' . $severity : ''; ?>"><?php echo (int) $this->severityCounts[$severity]; ?></div>
                        <div class="small text-body-secondary"><?php echo $this->escape(Text::_($label)); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($this->issues === []) : ?>
        <div class="alert alert-success">
            <span class="icon-check" aria-hidden="true"></span>
            <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_HEALTH_ALL_GOOD')); ?>
        </div>
    <?php else : ?>
        <?php if ($this->canFix && $this->safeFixCount > 0) : ?>
            <form method="post" action="<?php echo $this->escape($fixAllUrl); ?>" class="mb-3 csem-busy-form">
                <button type="submit" class="btn btn-success">
                    <span class="icon-wrench" aria-hidden="true"></span>
                    <?php echo $this->escape(Text::plural('COM_CSEXTENSIONMANAGER_HEALTH_FIXALL_N', $this->safeFixCount)); ?>
                </button>
                <span class="small text-body-secondary ms-2"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_HEALTH_FIXALL_DESC')); ?></span>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>

        <?php $lastSeverity = ''; ?>
        <?php foreach ($this->issues as $issue) : ?>
            <?php if ($issue['severity'] !== $lastSeverity) : ?>
                <?php $lastSeverity = $issue['severity']; ?>
                <h2 class="h5 mt-3" id="csem-sev-<?php echo $this->escape($lastSeverity); ?>"><?php echo $this->escape(Text::_($severityLabel[$lastSeverity][1])); ?></h2>
            <?php endif; ?>
            <div class="card mb-2 csem-issue csem-issue-<?php echo $this->escape($issue['severity']); ?>">
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge <?php echo $severityLabel[$issue['severity']][0]; ?>"><?php echo $this->escape(Text::_($severityLabel[$issue['severity']][1])); ?></span>
                            <strong><?php echo $this->escape($issue['title']); ?></strong>
                        </div>
                        <p class="small mb-0 mt-1 text-break"><?php echo $this->escape($issue['message']); ?></p>
                    </div>
                    <?php if ($issue['fix'] !== '' && $this->canFix && (!in_array($issue['fix'], $needsInstall, true) || $this->canInstall)) : ?>
                        <form method="post" action="<?php echo $this->escape($fixUrl); ?>" class="csem-busy-form">
                            <input type="hidden" name="issue" value="<?php echo $this->escape($issue['id']); ?>">
                            <button type="submit" class="btn btn-sm <?php echo in_array($issue['fix'], $needsInstall, true) ? 'btn-primary' : 'btn-success'; ?>">
                                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_FIX_' . strtoupper($issue['fix']))); ?>
                            </button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php elseif ($issue['link'] !== '') : ?>
                        <a class="btn btn-sm btn-secondary" href="<?php echo $this->escape($issue['link']); ?>"<?php echo str_starts_with($issue['link'], 'http') ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                            <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_HEALTH_OPEN')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="small text-body-secondary mt-3"><?php echo $this->escape(DisplayHelper::catalogLine($this->catalog)); ?></p>

    <?php echo DisplayHelper::supportPanel(); ?>
</div>

<div class="modal fade csem-modal" id="csemBusyModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border mb-3" role="status" aria-hidden="true"></div>
                <p class="mb-0" data-csem-busy-text><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_JS_WORKING')); ?></p>
            </div>
        </div>
    </div>
</div>
