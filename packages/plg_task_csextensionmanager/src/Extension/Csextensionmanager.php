<?php

/**
 * @package    plg_task_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Plugin\Task\Csextensionmanager\Extension;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\LogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\UpdateRunner;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Scheduler task "Cybersalt Extension Manager: check for updates": check the
 * store, optionally install updates (patch only / patch + minor / all), and
 * email a summary.
 *
 * @since  0.1.0
 */
final class Csextensionmanager extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var    array<string, array<string, string>>
     * @since  0.1.0
     */
    private const TASKS_MAP = [
        'csextensionmanager.check' => [
            'langConstPrefix' => 'PLG_TASK_CSEXTENSIONMANAGER_CHECK',
            'method'          => 'checkUpdates',
            'form'            => 'checkForm',
        ],
    ];

    /**
     * @var    boolean
     * @since  0.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return  array<string, string>
     *
     * @since   0.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  integer  Status code.
     *
     * @since   0.1.0
     */
    private function checkUpdates(ExecuteTaskEvent $event): int
    {
        if (!class_exists(UpdateRunner::class)) {
            $this->logTask('Cybersalt Extension Manager component is not installed.', 'error');

            return Status::KNOCKOUT;
        }

        $this->getApplication()->getLanguage()->load('com_csextensionmanager', JPATH_ADMINISTRATOR);

        $params = $event->getArgument('params');
        $mode   = (string) ($params->mode ?? UpdateRunner::MODE_NOTIFY);
        $notify = (string) ($params->notify ?? UpdateRunner::NOTIFY_CHANGES);

        if (!\in_array($mode, [UpdateRunner::MODE_NOTIFY, UpdateRunner::MODE_PATCH, UpdateRunner::MODE_MINOR, UpdateRunner::MODE_ALL], true)) {
            $mode = UpdateRunner::MODE_NOTIFY;
        }

        if (!\in_array($notify, [UpdateRunner::NOTIFY_CHANGES, UpdateRunner::NOTIFY_ALWAYS, UpdateRunner::NOTIFY_NEVER], true)) {
            $notify = UpdateRunner::NOTIFY_CHANGES;
        }

        LogHelper::setTrigger(LogHelper::TRIGGER_TASK);

        try {
            $summary = UpdateRunner::run($mode, $notify);
        } catch (\Throwable $e) {
            $this->logTask('Cybersalt Extension Manager check failed: ' . $e->getMessage(), 'error');

            return Status::KNOCKOUT;
        } finally {
            LogHelper::setTrigger(LogHelper::TRIGGER_MANUAL);
        }

        $this->logTask(\sprintf(
            'Cybersalt Extension Manager: %d pending, %d installed, %d failed, %d skipped%s.',
            \count($summary['pending']),
            \count($summary['installed']),
            \count($summary['failed']),
            \count($summary['skipped']),
            $summary['emailed'] ? ', summary emailed' : ''
        ));

        return $summary['failed'] === [] ? Status::OK : Status::KNOCKOUT;
    }
}
