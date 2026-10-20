<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * One extension card. Reads $this->currentItem / $this->currentSection.
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Extensions\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\SupportHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$item    = $this->currentItem;
$key     = (string) $item['key'];
$status  = (string) $item['status'];
$domId   = 'csem-' . preg_replace('/[^a-z0-9]+/', '-', $key);
$action  = DisplayHelper::primaryAction($item, $this->canInstall);
$search  = strtolower(implode(' ', [$item['title'], $item['element'], $item['description'], $item['target']['display_name'] ?? '']));
$relevant = $item['group'] !== CatalogHelper::GROUP_ADDON ? '' : ($item['installed'] || $item['target_installed'] ? '1' : '0');
$selectable = $this->canInstall && in_array($action['kind'], ['install', 'update', 'repair'], true);
?>
<div class="col" data-csem-card
     id="<?php echo $this->escape($domId); ?>"
     data-key="<?php echo $this->escape($key); ?>"
     data-status="<?php echo $this->escape($status); ?>"
     data-installed="<?php echo $item['installed'] ? '1' : '0'; ?>"
     data-group="<?php echo $this->escape($item['group']); ?>"
     data-tier="<?php echo $this->escape($item['tier']); ?>"
     data-relevant="<?php echo $relevant; ?>"
     data-search="<?php echo $this->escape($search); ?>">
    <div class="card h-100 csem-card csem-status-<?php echo $this->escape($status); ?>">
        <div class="card-body">
            <div class="d-flex gap-3 align-items-start">
                <?php if ($selectable) : ?>
                    <input type="checkbox" class="form-check-input mt-1 csem-select" data-group="<?php echo $this->escape($this->currentSection); ?>" value="<?php echo $this->escape($key); ?>" aria-label="<?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_SELECT_ITEM', $item['title'])); ?>">
                <?php endif; ?>
                <img class="csem-icon" src="<?php echo $this->escape(CatalogHelper::iconSrc($item)); ?>" width="48" height="48" alt="" loading="lazy">
                <div class="flex-grow-1 min-w-0">
                    <h3 class="h6 card-title mb-1"><?php echo $this->escape($item['title']); ?></h3>
                    <div class="d-flex flex-wrap gap-1">
                        <?php echo DisplayHelper::statusBadge($item); ?>
                        <?php echo DisplayHelper::tierBadge($item); ?>
                        <span class="badge bg-secondary"><?php echo $this->escape(DisplayHelper::typeLabel($item)); ?></span>
                        <?php if ($item['is_new']) : ?>
                            <span class="badge bg-info text-dark"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BADGE_NEW')); ?></span>
                        <?php endif; ?>
                        <?php if ($item['is_experimental']) : ?>
                            <span class="badge bg-warning text-dark"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BADGE_EXPERIMENTAL')); ?></span>
                        <?php endif; ?>
                        <?php if (($item['stability'] ?? 'stable') !== 'stable') : ?>
                            <span class="badge bg-warning text-dark"><?php echo $this->escape(strtoupper((string) $item['stability'])); ?></span>
                        <?php endif; ?>
                        <?php if ($item['update_type'] === 'major') : ?>
                            <span class="badge bg-danger"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BADGE_MAJOR')); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($item['description'] !== '') : ?>
                <p class="card-text small mt-2 mb-2"><?php echo $this->escape($item['description']); ?></p>
            <?php endif; ?>

            <dl class="csem-versions small mb-2">
                <?php if ($item['installed']) : ?>
                    <dt><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_INSTALLED_VERSION')); ?></dt>
                    <dd><?php echo $this->escape($item['installed_version'] !== '' ? $item['installed_version'] : '?'); ?>
                        <?php if (!$item['enabled'] && $item['type'] !== 'package') : ?>
                            <span class="badge bg-secondary"><?php echo $this->escape(Text::_('JDISABLED')); ?></span>
                        <?php endif; ?>
                    </dd>
                <?php endif; ?>
                <?php if ($item['latest_version'] !== '') : ?>
                    <dt><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_LATEST_VERSION')); ?></dt>
                    <dd><?php echo $this->escape($item['latest_version']); ?>
                        <?php if ($item['released'] !== '') : ?>
                            <span class="text-body-secondary">(<?php echo $this->escape(HTMLHelper::_('date', $item['released'], Text::_('DATE_FORMAT_LC4'))); ?>)</span>
                        <?php endif; ?>
                    </dd>
                <?php endif; ?>
            </dl>

            <?php if (is_array($item['target'])) : ?>
                <p class="small mb-2">
                    <?php if ($item['target_installed']) : ?>
                        <span class="icon-check text-success" aria-hidden="true"></span>
                        <?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_TARGET_PRESENT', $item['target']['display_name'])); ?>
                    <?php else : ?>
                        <span class="icon-minus-circle text-body-secondary" aria-hidden="true"></span>
                        <?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_TARGET_ABSENT', $item['target']['display_name'])); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($item['requires_missing'] !== [] && !$item['installed']) : ?>
                <p class="small text-body-secondary mb-2">
                    <span class="icon-link" aria-hidden="true"></span>
                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_REQUIRES_PARENT')); ?>
                </p>
            <?php endif; ?>

            <?php if (!$item['compatible'] && $item['available']) : ?>
                <div class="alert alert-warning small py-2 mb-2"><?php echo $this->escape($item['incompatible_reason']); ?></div>
            <?php endif; ?>

            <?php if ($item['denied'] !== '') : ?>
                <div class="alert alert-warning small py-2 mb-2">
                    <?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_DENIED', $item['denied'])); ?>
                    <?php if ($item['info_url'] !== '') : ?>
                        <a href="<?php echo $this->escape($item['info_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DENIED_WHY')); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($item['superseded_name'] !== '') : ?>
                <div class="alert alert-info small py-2 mb-2"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_SUPERSEDED', $item['superseded_name'])); ?></div>
            <?php endif; ?>

            <?php if (is_array($item['advisory'])) : ?>
                <details class="alert alert-<?php echo $this->escape($item['advisory']['severity']); ?> small py-2 mb-2 csem-advisory"<?php echo $item['advisory']['severity'] === 'danger' ? ' open' : ''; ?>>
                    <summary><?php echo $this->escape($item['advisory']['title'] !== '' ? $item['advisory']['title'] : Text::_('COM_CSEXTENSIONMANAGER_ADVISORY_DEFAULT_TITLE')); ?></summary>
                    <?php echo $this->escape($item['advisory']['message']); ?>
                </details>
            <?php endif; ?>

            <?php if ($status === StateHelper::STATUS_BROKEN) : ?>
                <div class="alert alert-danger small py-2 mb-2"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BROKEN_DESC')); ?></div>
            <?php endif; ?>

            <?php if ($item['children'] !== []) : ?>
                <details class="csem-parts small">
                    <summary><?php echo $this->escape(Text::plural('COM_CSEXTENSIONMANAGER_PARTS_N', count($item['children']))); ?></summary>
                    <ul class="list-unstyled mt-2 mb-0">
                        <?php foreach ($item['children'] as $child) : ?>
                            <li class="d-flex align-items-center gap-2 py-1">
                                <?php if ($this->canInstall && empty($item['self']) && in_array($child['type'], ['plugin', 'module', 'component'], true)) : ?>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="<?php echo $this->escape($domId . '-part-' . $child['id']); ?>"
                                               data-csem-toggle="<?php echo (int) $child['id']; ?>"
                                               data-key="<?php echo $this->escape($key); ?>"
                                               <?php echo $child['enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="<?php echo $this->escape($domId . '-part-' . $child['id']); ?>"><?php echo $this->escape($child['name']); ?></label>
                                    </div>
                                <?php else : ?>
                                    <span class="<?php echo $child['enabled'] ? 'icon-check text-success' : 'icon-times text-body-secondary'; ?>" aria-hidden="true"></span>
                                    <?php echo $this->escape($child['name']); ?>
                                <?php endif; ?>
                                <span class="text-body-secondary ms-auto"><?php echo $this->escape(DisplayHelper::typeLabel($child)); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>

        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
            <?php echo DisplayHelper::primaryButton($item, $action); ?>

            <button type="button" class="btn btn-sm btn-secondary" data-csem-changelog="<?php echo $this->escape($key); ?>">
                <span class="icon-list" aria-hidden="true"></span>
                <?php echo $this->escape(Text::_($item['installed'] && $status === StateHelper::STATUS_UPDATE ? 'COM_CSEXTENSIONMANAGER_BTN_WHATS_NEW' : 'COM_CSEXTENSIONMANAGER_BTN_CHANGELOG')); ?>
            </button>

            <div class="dropdown ms-auto">
                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_MORE')); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($this->canInstall && $item['installed'] && $item['available'] && $status !== StateHelper::STATUS_UPDATE && $item['entitled']) : ?>
                        <li><button type="button" class="dropdown-item" data-csem-install="<?php echo $this->escape($key); ?>" data-version="<?php echo $this->escape($item['latest_version']); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_REINSTALL')); ?></button></li>
                    <?php endif; ?>
                    <?php if ($this->canInstall && $item['entitled']) : ?>
                        <li><button type="button" class="dropdown-item" data-csem-changelog="<?php echo $this->escape($key); ?>" data-pick-version="1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_OTHER_VERSION')); ?></button></li>
                    <?php endif; ?>
                    <?php if ($this->canInstall && $item['installed'] && $item['type'] !== 'package' && empty($item['self'])) : ?>
                        <li><button type="button" class="dropdown-item" data-csem-toggle="<?php echo (int) $item['extension_id']; ?>" data-key="<?php echo $this->escape($key); ?>" data-enabled="<?php echo $item['enabled'] ? '1' : '0'; ?>"><?php echo $this->escape(Text::_($item['enabled'] ? 'COM_CSEXTENSIONMANAGER_BTN_DISABLE' : 'COM_CSEXTENSIONMANAGER_BTN_ENABLE')); ?></button></li>
                    <?php endif; ?>
                    <?php if ($item['docs_url'] !== '' || $item['product_url'] !== '' || $item['repo_url'] !== '') : ?>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <?php foreach (['docs_url' => 'COM_CSEXTENSIONMANAGER_LINK_DOCS', 'product_url' => 'COM_CSEXTENSIONMANAGER_LINK_PRODUCT', 'repo_url' => 'COM_CSEXTENSIONMANAGER_LINK_REPO'] as $field => $label) : ?>
                        <?php if ($item[$field] !== '') : ?>
                            <li><a class="dropdown-item" href="<?php echo $this->escape($item[$field]); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_($label)); ?> <span class="icon-out-2 small" aria-hidden="true"></span></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (is_array($item['target']) && $item['target']['vendor_url'] !== '') : ?>
                        <li><a class="dropdown-item" href="<?php echo $this->escape($item['target']['vendor_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_LINK_VENDOR', $item['target']['vendor_name'] !== '' ? $item['target']['vendor_name'] : $item['target']['display_name'])); ?> <span class="icon-out-2 small" aria-hidden="true"></span></a></li>
                    <?php endif; ?>
                    <?php if ($this->canInstall && $item['installed'] && empty($item['self'])) : ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><button type="button" class="dropdown-item text-danger" data-csem-uninstall="<?php echo $this->escape($key); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_UNINSTALL')); ?></button></li>
                    <?php endif; ?>
                    <?php if (empty($item['docs_url']) && empty($item['product_url']) && empty($item['repo_url']) && !$this->canInstall) : ?>
                        <li><a class="dropdown-item" href="<?php echo $this->escape(SupportHelper::getUrl()); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SUPPORT_GET_HELP')); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
