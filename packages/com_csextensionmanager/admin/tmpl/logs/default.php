<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Logs\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_csextensionmanager');
$wa->useStyle('com_csextensionmanager.admin');
$wa->useScript('multiselect');

HTMLHelper::_('bootstrap.modal');

$listOrder = $this->escape((string) $this->state->get('list.ordering'));
$listDirn  = $this->escape((string) $this->state->get('list.direction'));
$logsBase  = 'index.php?option=com_csextensionmanager&view=logs';
$logsUrl   = static fn (array $filters = []): string => Route::_(
    $logsBase . ($filters ? '&' . http_build_query(['filter' => $filters]) : ''),
    false
);

$statusBadge = [
    'success' => 'bg-success',
    'warning' => 'bg-warning text-dark',
    'error'   => 'bg-danger',
    'skip'    => 'bg-secondary',
];

// Every stat card is a filter link (Cybersalt "hotlinked stats" rule).
$cards = [
    ['key' => 'total',     'label' => 'COM_CSEXTENSIONMANAGER_STAT_LOG_TOTAL', 'href' => $logsUrl(),                         'tone' => ''],
    ['key' => 'last24h',   'label' => 'COM_CSEXTENSIONMANAGER_STAT_LOG_24H',   'href' => $logsUrl(['since' => '24h']),       'tone' => ''],
    ['key' => 'n_install', 'label' => 'COM_CSEXTENSIONMANAGER_STAT_LOG_INSTALLS', 'href' => $logsUrl(['action' => 'install', 'status' => 'success']), 'tone' => ''],
    ['key' => 'n_update',  'label' => 'COM_CSEXTENSIONMANAGER_STAT_LOG_UPDATES', 'href' => $logsUrl(['action' => 'update', 'status' => 'success']),  'tone' => ''],
    ['key' => 'success',   'label' => 'COM_CSEXTENSIONMANAGER_STATUS_SUCCESS', 'href' => $logsUrl(['status' => 'success']), 'tone' => 'text-success'],
    ['key' => 'warning',   'label' => 'COM_CSEXTENSIONMANAGER_STATUS_WARNING', 'href' => $logsUrl(['status' => 'warning']), 'tone' => 'text-warning'],
    ['key' => 'error',     'label' => 'COM_CSEXTENSIONMANAGER_STATUS_ERROR',   'href' => $logsUrl(['status' => 'error']),   'tone' => 'text-danger'],
];
?>
<div class="csem csem-logs">
    <div class="row g-2 mb-3">
        <?php foreach ($cards as $card) : ?>
            <div class="col-6 col-md-3 col-xl">
                <a href="<?php echo $this->escape($card['href']); ?>" class="card text-decoration-none h-100 csem-stat-card">
                    <div class="card-body text-center py-2">
                        <div class="h3 mb-0 <?php echo $card['tone']; ?>"><?php echo (int) ($this->stats[$card['key']] ?? 0); ?></div>
                        <div class="small text-body-secondary"><?php echo $this->escape(Text::_($card['label'])); ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <form action="<?php echo Route::_($logsBase); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span>
                <span class="visually-hidden"><?php echo $this->escape(Text::_('INFO')); ?></span>
                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_LOGS_EMPTY')); ?>
            </div>
        <?php else : ?>
            <table class="table" id="csemLogList">
                <caption class="visually-hidden">
                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_LOGS_CAPTION')); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <th scope="col" class="text-nowrap"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_DATE', 'a.created', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_ACTION', 'a.action', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_STATUS', 'a.status', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_EXTENSION', 'a.title', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="text-nowrap"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_VERSION', 'a.to_version', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_HEADING_MESSAGE')); ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_USER', 'u.name', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="d-none d-lg-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_TRIGGER', 'a.trigger_source', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="d-none d-lg-table-cell text-end"><?php echo HTMLHelper::_('searchtools.sort', 'COM_CSEXTENSIONMANAGER_HEADING_ELAPSED', 'a.elapsed_ms', $listDirn, $listOrder); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $i => $item) : ?>
                        <tr class="row<?php echo $i % 2; ?>">
                            <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', (string) $item->title); ?></td>
                            <td class="text-nowrap small"><?php echo $this->escape(HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC6'))); ?></td>
                            <td>
                                <a href="<?php echo $this->escape($logsUrl(['action' => (string) $item->action])); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACTION_' . strtoupper((string) $item->action))); ?></a>
                            </td>
                            <td>
                                <a href="<?php echo $this->escape($logsUrl(['status' => (string) $item->status])); ?>" class="badge text-decoration-none <?php echo $statusBadge[(string) $item->status] ?? 'bg-secondary'; ?>">
                                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_STATUS_' . strtoupper((string) $item->status))); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ((string) $item->element !== '') : ?>
                                    <a href="<?php echo $this->escape($logsUrl(['element' => (string) $item->element])); ?>"><?php echo $this->escape((string) $item->title !== '' ? $item->title : $item->element); ?></a>
                                <?php else : ?>
                                    &ndash;
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap small">
                                <?php if ((string) $item->from_version !== '' || (string) $item->to_version !== '') : ?>
                                    <?php echo $this->escape((string) $item->from_version !== '' ? $item->from_version : '–'); ?>
                                    &rarr;
                                    <?php echo $this->escape((string) $item->to_version !== '' ? $item->to_version : '–'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="small csem-msg-cell">
                                <?php if ((string) $item->details !== '') : ?>
                                    <details>
                                        <summary><?php echo $this->escape($item->message); ?></summary>
                                        <pre class="border rounded p-2 mt-2 mb-0 small"><?php echo $this->escape($item->details); ?></pre>
                                    </details>
                                <?php else : ?>
                                    <?php echo $this->escape($item->message); ?>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-lg-table-cell small">
                                <a href="<?php echo $this->escape($logsUrl(['user_id' => (string) (int) $item->user_id])); ?>">
                                    <?php echo $this->escape((int) $item->user_id === 0 ? Text::_('COM_CSEXTENSIONMANAGER_USER_SYSTEM') : (string) ($item->user_name ?? ('#' . (int) $item->user_id))); ?>
                                </a>
                            </td>
                            <td class="d-none d-lg-table-cell small">
                                <a href="<?php echo $this->escape($logsUrl(['trigger' => (string) $item->trigger_source])); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_TRIGGER_' . strtoupper((string) $item->trigger_source))); ?></a>
                            </td>
                            <td class="d-none d-lg-table-cell small text-end"><?php echo (int) $item->elapsed_ms > 0 ? (int) $item->elapsed_ms . ' ms' : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <?php echo DisplayHelper::supportPanel(); ?>
</div>

<?php if ($this->canDelete) : ?>
    <div class="modal fade" id="csemClearAllModal" tabindex="-1" aria-labelledby="csemClearAllTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?php echo Route::_('index.php?option=com_csextensionmanager&task=logs.purge'); ?>">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="csemClearAllTitle"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_CLEAR_ALL_TITLE')); ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $this->escape(Text::_('JCLOSE')); ?>"></button>
                </div>
                <div class="modal-body">
                    <p><?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_CLEAR_ALL_BODY', (int) ($this->stats['total'] ?? 0))); ?></p>
                    <p class="small text-body-secondary mb-0"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_CLEAR_ALL_NOTE')); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $this->escape(Text::_('JCANCEL')); ?></button>
                    <button type="submit" class="btn btn-danger"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_CLEAR_ALL_CONFIRM')); ?></button>
                </div>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </div>
    </div>
<?php endif; ?>
