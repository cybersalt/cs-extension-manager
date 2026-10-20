<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The "check for updates" scheduled task: find it, create it, switch it on.
 *
 * The package creates it switched off (Tim, 2026-10-20: email updates are
 * opt-in); "Email me about new releases" on the install card and the
 * Extensions screen switches it on.
 *
 * @since  0.1.0
 */
final class TaskHelper
{
    public const TYPE = 'csextensionmanager.check';

    /**
     * The task row, or null when there is none.
     *
     * @return  object|null
     *
     * @since   0.1.0
     */
    public static function find(): ?object
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $type = self::TYPE;

            return $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName(['id', 'state', 'params']))
                    ->from($db->quoteName('#__scheduler_tasks'))
                    ->where($db->quoteName('type') . ' = :type')
                    ->where($db->quoteName('state') . ' >= 0')
                    ->bind(':type', $type)
                    ->order($db->quoteName('id')),
                0,
                1
            )->loadObject() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Is a check task switched on?
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function isEnabled(): bool
    {
        $task = self::find();

        return $task !== null && (int) $task->state === 1;
    }

    /**
     * Create the task (switched off unless $enabled) when there isn't one.
     * Goes through the Scheduler's own table so it gets its asset row like a
     * task made in the UI.
     *
     * @param   boolean  $enabled  Create it switched on.
     *
     * @return  boolean  A task was created.
     *
     * @since   0.1.0
     */
    public static function create(bool $enabled = false): bool
    {
        if (self::find() !== null) {
            return false;
        }

        $app = Factory::getApplication();

        /** @var \Joomla\CMS\Table\Table $table */
        $table = $app->bootComponent('com_scheduler')->getMVCFactory()->createTable('Task', 'Administrator');
        $user  = $app->getIdentity();

        $table->bind([
            'title'           => Text::_('COM_CSEXTENSIONMANAGER_TASK_TITLE'),
            'type'            => self::TYPE,
            'execution_rules' => json_encode(['rule-type' => 'interval-days', 'interval-days' => '1', 'exec-day' => '01', 'exec-time' => '04:00']),
            'cron_rules'      => json_encode(['type' => 'interval', 'exp' => 'P1D']),
            'state'           => $enabled ? 1 : 0,
            'last_exit_code'  => 0,
            'next_execution'  => Factory::getDate('tomorrow 04:00')->toSql(),
            'priority'        => 0,
            'params'          => json_encode([
                'individual_log' => false,
                'log_file'       => '',
                'notifications'  => ['success_mail' => '0', 'failure_mail' => '1', 'fatal_failure_mail' => '1', 'orphan_mail' => '1'],
                'mode'           => UpdateRunner::MODE_NOTIFY,
                'notify'         => UpdateRunner::NOTIFY_CHANGES,
            ]),
            'created'         => Factory::getDate()->toSql(),
            'created_by'      => $user !== null ? (int) $user->id : 0,
        ]);

        return $table->check() && $table->store();
    }

    /**
     * Switch email updates on: make sure the task exists and runs, and when
     * no recipients are set yet, send them to $email.
     *
     * @param   string  $email  The address of whoever asked for them.
     *
     * @return  string  Who will get the emails (for the confirmation message).
     *
     * @since   0.1.0
     */
    public static function enableEmails(string $email): string
    {
        if (!self::create(true)) {
            $task = self::find();

            if ($task !== null && (int) $task->state !== 1) {
                $db    = Factory::getContainer()->get(DatabaseInterface::class);
                $id    = (int) $task->id;
                $state = 1;

                $db->setQuery(
                    $db->createQuery()
                        ->update($db->quoteName('#__scheduler_tasks'))
                        ->set($db->quoteName('state') . ' = :state')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':state', $state, ParameterType::INTEGER)
                        ->bind(':id', $id, ParameterType::INTEGER)
                )->execute();
            }
        }

        $current = trim((string) ParamsHelper::get()->get('notify_emails', ''));

        if ($current === '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ParamsHelper::save(['notify_emails' => $email]);
        }

        return implode(', ', UpdateRunner::recipients());
    }
}
