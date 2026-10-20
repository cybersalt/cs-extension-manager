<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * Queue (lock-out), changelog and confirm modals. Filled in by media/js/admin.js.
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Extensions\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="modal fade csem-modal" id="csemQueueModal" tabindex="-1" aria-labelledby="csemQueueTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="csemQueueTitle"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_JS_QUEUE_TITLE')); ?></h3>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-csem-queue-status aria-live="polite"></p>
                <div class="progress mb-3" role="progressbar" aria-label="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_JS_QUEUE_TITLE')); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="progress-bar" data-csem-queue-bar style="width: 0%"></div>
                </div>
                <ol class="list-group list-group-numbered" data-csem-queue-list></ol>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-csem-queue-reload disabled><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_JS_RELOAD')); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade csem-modal" id="csemChangelogModal" tabindex="-1" aria-labelledby="csemChangelogTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="csemChangelogTitle"></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $this->escape(Text::_('JCLOSE')); ?>"></button>
            </div>
            <div class="modal-body" data-csem-changelog-body></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $this->escape(Text::_('JCLOSE')); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade csem-modal" id="csemConfirmModal" tabindex="-1" aria-labelledby="csemConfirmTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="csemConfirmTitle"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_CONFIRM_TITLE')); ?></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $this->escape(Text::_('JCLOSE')); ?>"></button>
            </div>
            <div class="modal-body"><p class="mb-0" data-csem-confirm-text></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $this->escape(Text::_('JCANCEL')); ?></button>
                <button type="button" class="btn btn-danger" data-csem-confirm-ok><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_CONFIRM_OK')); ?></button>
            </div>
        </div>
    </div>
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
