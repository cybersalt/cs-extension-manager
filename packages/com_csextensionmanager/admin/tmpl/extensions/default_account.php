<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 *
 * Account / download ID card.
 *
 * @var \Cybersalt\Component\Csextensionmanager\Administrator\View\Extensions\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\AccountHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\DisplayHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\SupportHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$account    = $this->account;
$source     = $account['source'];
$status     = $account['status'];
$optionsUrl = Route::_('index.php?option=com_config&view=component&component=com_csextensionmanager', false);
$action     = static fn (string $task): string => Route::_('index.php?option=com_csextensionmanager&task=account.' . $task, false);

$sourceText = match ($source) {
    AccountHelper::SOURCE_MANUAL  => Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_SOURCE_MANUAL'),
    AccountHelper::SOURCE_ACCOUNT => Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_SOURCE_ACCOUNT', $account['email']),
    AccountHelper::SOURCE_MCPFORJ => Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_SOURCE_MCPFORJ', $account['email'] !== '' ? $account['email'] : '-'),
    default                       => '',
};
?>
<div class="card mb-3 csem-account" id="cs-account">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_TITLE')); ?></h2>
            <?php echo DisplayHelper::accountBadge($source, $status); ?>
        </div>

        <?php if ($account['manual_invalid']) : ?>
            <div class="alert alert-warning small py-2"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_MANUAL_INVALID')); ?></div>
        <?php endif; ?>

        <?php if ($source === AccountHelper::SOURCE_NONE) : ?>
            <p class="mb-2"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_NONE')); ?></p>

            <?php if ($this->canConfigure) : ?>
                <form method="post" action="<?php echo $this->escape($action('activate')); ?>" class="row g-2 align-items-end csem-busy-form" style="max-width: 40rem">
                    <div class="col-sm">
                        <label for="csem-email" class="form-label small mb-1"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_EMAIL_LABEL')); ?></label>
                        <input type="email" class="form-control" id="csem-email" name="email" required autocomplete="email" placeholder="you@example.com">
                    </div>
                    <div class="col-sm-auto">
                        <button type="submit" class="btn btn-primary"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_ACTIVATE')); ?></button>
                    </div>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
                <p class="small text-body-secondary mt-2 mb-0">
                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_PRIVACY')); ?>
                    <a href="<?php echo $this->escape($optionsUrl); ?>"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_PASTE_ID')); ?></a>
                    &middot;
                    <a href="<?php echo $this->escape(SupportHelper::PLANS_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_PLANS')); ?></a>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <p class="mb-1"><?php echo $this->escape($sourceText); ?></p>
            <p class="small text-body-secondary mb-2">
                <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_DLID_LABEL')); ?>
                <code><?php echo $this->escape($account['dlid_masked']); ?></code>
                <?php if ($account['verified_at'] !== '') : ?>
                    &middot; <?php echo $this->escape(Text::sprintf('COM_CSEXTENSIONMANAGER_ACCOUNT_CHECKED', HTMLHelper::_('date.relative', $account['verified_at']))); ?>
                <?php endif; ?>
            </p>

            <?php if ($status === 'lapsed') : ?>
                <div class="alert alert-warning small py-2 mb-2">
                    <?php echo $this->escape($account['message'] !== '' ? $account['message'] : Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_LAPSED_DESC')); ?>
                    <a href="<?php echo $this->escape($account['renewal_url'] !== '' ? $account['renewal_url'] : SupportHelper::PLANS_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_RENEW')); ?></a>
                </div>
            <?php elseif ($status === 'not_a_member') : ?>
                <div class="alert alert-info small py-2 mb-2">
                    <?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_NOT_A_MEMBER_DESC')); ?>
                    <a href="<?php echo $this->escape($account['signup_url'] !== '' ? $account['signup_url'] : SupportHelper::PLANS_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_PLANS')); ?></a>
                </div>
            <?php elseif ($status !== '' && $status !== 'active') : ?>
                <div class="alert alert-warning small py-2 mb-2"><?php echo $this->escape($account['message'] !== '' ? $account['message'] : Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_PROBLEM_DESC')); ?></div>
            <?php endif; ?>

            <?php if ($this->canConfigure) : ?>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="<?php echo $this->escape($action('verify')); ?>" class="csem-busy-form">
                        <button type="submit" class="btn btn-sm btn-secondary"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_VERIFY')); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                    <form method="post" action="<?php echo $this->escape($action('sync')); ?>">
                        <button type="submit" class="btn btn-sm btn-secondary"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_SYNC')); ?></button>
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                    <?php if ($source === AccountHelper::SOURCE_ACCOUNT) : ?>
                        <form method="post" action="<?php echo $this->escape($action('deactivate')); ?>" data-csem-confirm="<?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_DEACTIVATE_CONFIRM')); ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_DEACTIVATE')); ?></button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-secondary" href="<?php echo $this->escape(SupportHelper::LICENSES_URL); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape(Text::_('COM_CSEXTENSIONMANAGER_ACCOUNT_MY_LICENSES')); ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
