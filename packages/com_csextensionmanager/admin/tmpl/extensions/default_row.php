<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * One extension as a table row, plus a details row that opens below it.
 * Each extension is its own <tbody>, so sorting and filtering move or hide
 * both rows together. Reads $this->currentItem.
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

$item     = $this->currentItem;
$key      = (string) $item['key'];
$status   = (string) $item['status'];
$domId    = 'csem-' . preg_replace('/[^a-z0-9]+/', '-', $key);
$action   = DisplayHelper::primaryAction($item, $this->canInstall);
$search   = strtolower(implode(' ', [$item['title'], $item['element'], $item['description'], $item['target']['display_name'] ?? '']));
$relevant = $item['group'] !== CatalogHelper::GROUP_ADDON ? '' : ($item['installed'] || $item['target_installed'] ? '1' : '0');
$selectable = $this->canInstall && in_array($action['kind'], ['install', 'update', 'repair'], true);
// Same rules as the More menu's Reinstall and Uninstall; never the manager itself.
$canReinstall = $this->canInstall && $item['installed'] && $item['available'] && $item['entitled'] && empty($item['self']);
$canUninstall = $this->canInstall && $item['installed'] && empty($item['self']);

// Needs attention first when sorting by status.
$statusRank = [StateHelper::STATUS_BROKEN => 0, StateHelper::STATUS_UPDATE => 1, StateHelper::STATUS_CURRENT => 2, StateHelper::STATUS_AVAILABLE => 3][$status] ?? 4;

// The one-line warning shown under the title; the rest waits in Details.
$note = match (true) {
    $status === StateHelper::STATUS_BROKEN       => ['text-danger', Text::_('COM_CSEXTENSIONMANAGER_BROKEN_DESC')],
    $item['denied'] !== ''                       => ['text-warning-emphasis', Text::sprintf('COM_CSEXTENSIONMANAGER_DENIED', $item['denied'])],
    !$item['compatible'] && $item['available']   => ['text-warning-emphasis', (string) $item['incompatible_reason']],
    is_array($item['advisory']) && $item['advisory']['severity'] === 'danger' => ['text-danger', $item['advisory']['title'] !== '' ? $item['advisory']['title'] : Text::_('COM_CSEXTENSIONMANAGER_ADVISORY_DEFAULT_TITLE')],
    default                                      => null,
};

$changelogLabel = $item['installed'] && $status === StateHelper::STATUS_UPDATE ? 'COM_CSEXTENSIONMANAGER_BTN_WHATS_NEW' : 'COM_CSEXTENSIONMANAGER_BTN_CHANGELOG';
?>
<tbody data-csem-card
       id="<?php echo $this->escape($domId); ?>"
       data-key="<?php echo $this->escape($key); ?>"
       data-status="<?php echo $this->escape($status); ?>"
       data-installed="<?php echo $item['installed'] ? '1' : '0'; ?>"
       data-group="<?php echo $this->escape($item['group']); ?>"
       data-tier="<?php echo $this->escape($item['tier']); ?>"
       data-relevant="<?php echo $relevant; ?>"
       data-search="<?php echo $this->escape($search); ?>"
       data-sort-name="<?php echo $this->escape(strtolower((string) $item['title'])); ?>"
       data-sort-status="<?php echo $statusRank; ?>"
       data-sort-version="<?php echo $this->escape((string) $item['installed_version']); ?>"
       data-sort-released="<?php echo $this->escape((string) $item['released']); ?>">
    <tr class="csem-row csem-status-<?php echo $this->escape($status); ?>">
        <td class="csem-col-select">
            <?php if ($selectable || $canReinstall || $canUninstall) : ?>
                <?php // What a ticked row can take part in; the bulk buttons count only the rows that fit. ?>
                <input type="checkbox" class="form-check-input csem-select" data-group="all" value="<?php echo $this->escape($key); ?>"
                       data-can-run="<?php echo $selectable ? '1' : '0'; ?>"
                       data-can-reinstall="<?php echo $canReinstall ? '1' : '0'; ?>"
                       data-can-uninstall="<?php echo $canUninstall ? '1' : '0'; ?>"
                       aria-label="<?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_SELECT_ITEM', $item['title'])); ?>">
            <?php elseif ($this->canInstall) : ?>
                <?php // e.g. a Pro add-on this site isn't entitled to: nothing to do from here. ?>
                <input type="checkbox" class="form-check-input csem-select-none" disabled title="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SELECT_NOTHING_TO_DO')); ?>" aria-label="<?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_SELECT_NOTHING_TO_DO_ITEM', $item['title'])); ?>">
            <?php endif; ?>
        </td>
        <td class="csem-col-name">
            <div class="d-flex align-items-center gap-2">
                <img class="csem-icon csem-icon-sm" src="<?php echo $this->escape(CatalogHelper::iconSrc($item)); ?>" width="32" height="32" alt="" loading="lazy">
                <div class="min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <button type="button" class="csem-expand" data-csem-expand aria-expanded="false" aria-controls="<?php echo $this->escape($domId); ?>-details">
                            <span class="csem-expand-icon" aria-hidden="true"></span>
                            <span class="fw-semibold"><?php echo $this->escape($item['title']); ?></span>
                        </button>
                        <?php echo DisplayHelper::tierBadge($item); ?>
                        <?php if ($item['is_new']) : ?>
                            <span class="badge bg-info-subtle text-info-emphasis"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BADGE_NEW')); ?></span>
                        <?php endif; ?>
                        <?php if ($item['update_type'] === 'major') : ?>
                            <span class="badge bg-danger"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BADGE_MAJOR')); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($item['description'] !== '') : ?>
                        <div class="small text-body-secondary csem-desc"><?php echo $this->escape($item['description']); ?></div>
                    <?php endif; ?>
                    <?php if ($note !== null) : ?>
                        <div class="small <?php echo $note[0]; ?>"><span class="icon-warning" aria-hidden="true"></span> <?php echo $this->escape($note[1]); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td><?php echo DisplayHelper::statusBadge($item); ?></td>
        <td class="d-none d-md-table-cell text-nowrap">
            <?php echo $this->escape($item['installed'] ? ($item['installed_version'] !== '' ? $item['installed_version'] : '?') : '—'); ?>
            <?php if ($item['installed'] && $item['type'] !== 'package') : ?>
                <span class="badge bg-secondary<?php echo $item['enabled'] ? ' d-none' : ''; ?>" data-csem-disabled-badge><?php echo $this->escape(Text::_('JDISABLED')); ?></span>
            <?php endif; ?>
        </td>
        <td class="d-none d-md-table-cell text-nowrap">
            <?php echo $this->escape($item['latest_version'] !== '' ? $item['latest_version'] : '—'); ?>
            <?php if ($item['released'] !== '') : ?>
                <div class="small text-body-secondary"><?php echo $this->escape(HTMLHelper::_('date', $item['released'], Text::_('DATE_FORMAT_LC4'))); ?></div>
            <?php endif; ?>
        </td>
        <td class="text-end text-nowrap">
            <div class="d-inline-flex align-items-center gap-1">
                <?php // "Up to date" / "Not published" are already the status badge. ?>
                <?php if (!in_array($action['kind'], [StateHelper::STATUS_CURRENT, StateHelper::STATUS_UNAVAILABLE], true)) : ?>
                    <?php echo DisplayHelper::primaryButton($item, $action); ?>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                        <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_MORE')); ?>
                        <span class="visually-hidden"><?php echo $this->escape($item['title']); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item" data-csem-changelog="<?php echo $this->escape($key); ?>"><?php echo $this->escape(Text::_($changelogLabel)); ?></button></li>
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
                                <li><a class="dropdown-item" href="<?php echo $this->escape($item[$field]); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_($label)); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (is_array($item['target']) && $item['target']['vendor_url'] !== '') : ?>
                            <li><a class="dropdown-item" href="<?php echo $this->escape($item['target']['vendor_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_LINK_VENDOR', $item['target']['vendor_name'] !== '' ? $item['target']['vendor_name'] : $item['target']['display_name'])); ?></a></li>
                        <?php endif; ?>
                        <?php if ($this->canInstall && $item['installed'] && empty($item['self'])) : ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><button type="button" class="dropdown-item text-danger" data-csem-uninstall="<?php echo $this->escape($key); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BTN_UNINSTALL')); ?></button></li>
                        <?php endif; ?>
                        <?php if (!$this->canInstall) : ?>
                            <li><a class="dropdown-item" href="<?php echo $this->escape(SupportHelper::getUrl()); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_SUPPORT_GET_HELP')); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </td>
    </tr>
    <tr class="csem-details d-none" id="<?php echo $this->escape($domId); ?>-details">
        <td></td>
        <td colspan="5">
            <?php if ($item['description'] !== '') : ?>
                <p class="small mb-2"><?php echo $this->escape($item['description']); ?></p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <?php echo DisplayHelper::statusBadge($item); ?>
                <span class="badge bg-secondary"><?php echo $this->escape(DisplayHelper::typeLabel($item)); ?></span>
                <?php if ($item['is_experimental']) : ?>
                    <span class="badge bg-warning text-dark"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_BADGE_EXPERIMENTAL')); ?></span>
                <?php endif; ?>
                <?php if (($item['stability'] ?? 'stable') !== 'stable') : ?>
                    <span class="badge bg-warning text-dark"><?php echo $this->escape(strtoupper((string) $item['stability'])); ?></span>
                <?php endif; ?>
            </div>

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

            <?php if ($item['denied'] !== '' && $item['info_url'] !== '') : ?>
                <p class="small mb-2"><a href="<?php echo $this->escape($item['info_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_DENIED_WHY')); ?></a></p>
            <?php endif; ?>

            <?php if ($item['superseded_name'] !== '') : ?>
                <div class="alert alert-info small py-2 mb-2"><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_SUPERSEDED', $item['superseded_name'])); ?></div>
            <?php endif; ?>

            <?php if (is_array($item['advisory'])) : ?>
                <div class="alert alert-<?php echo $this->escape($item['advisory']['severity']); ?> small py-2 mb-2">
                    <strong><?php echo $this->escape($item['advisory']['title'] !== '' ? $item['advisory']['title'] : Text::_('COM_CSEXTENSIONMANAGER_ADVISORY_DEFAULT_TITLE')); ?></strong>
                    <?php echo $this->escape($item['advisory']['message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($item['children'] !== []) : ?>
                <p class="small fw-semibold mb-1"><?php echo $this->escape(Text::plural('COM_CSEXTENSIONMANAGER_PARTS_N', count($item['children']))); ?></p>
                <ul class="list-unstyled small mb-0 csem-parts-list">
                    <?php foreach ($item['children'] as $child) : ?>
                        <li class="d-flex align-items-center gap-2 py-1">
                            <?php if ($this->canInstall && empty($item['self']) && in_array($child['type'], ['plugin', 'module', 'component'], true)) : ?>
                                <div class="form-check form-switch mb-0 csem-switch" data-csem-tip="<?php echo $this->escape(Text::_($child['enabled'] ? 'COM_CSEXTENSIONMANAGER_TIP_DISABLE' : 'COM_CSEXTENSIONMANAGER_TIP_ENABLE')); ?>">
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
                            <span class="text-body-secondary"><?php echo $this->escape(DisplayHelper::typeLabel($child)); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
</tbody>
