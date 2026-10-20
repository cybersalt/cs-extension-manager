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
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Writes the manager's history to `#__csextensionmanager_log`, and mirrors
 * installs/updates/uninstalls into Joomla's User Actions Log when that
 * option is on.
 *
 * Logging must never break an install, so every write is wrapped.
 *
 * @since  0.1.0
 */
final class LogHelper
{
    public const ACTION_INSTALL   = 'install';
    public const ACTION_UPDATE    = 'update';
    public const ACTION_DOWNGRADE = 'downgrade';
    public const ACTION_REINSTALL = 'reinstall';
    public const ACTION_UNINSTALL = 'uninstall';
    public const ACTION_ENABLE    = 'enable';
    public const ACTION_DISABLE   = 'disable';
    public const ACTION_REFRESH   = 'refresh';
    public const ACTION_FIX       = 'fix';
    public const ACTION_ACCOUNT   = 'account';
    public const ACTION_NOTIFY    = 'notify';

    /**
     * All actions, in display order.
     *
     * @var    string[]
     * @since  0.1.0
     */
    public const ACTIONS = [
        self::ACTION_INSTALL,
        self::ACTION_UPDATE,
        self::ACTION_DOWNGRADE,
        self::ACTION_REINSTALL,
        self::ACTION_UNINSTALL,
        self::ACTION_ENABLE,
        self::ACTION_DISABLE,
        self::ACTION_REFRESH,
        self::ACTION_FIX,
        self::ACTION_ACCOUNT,
        self::ACTION_NOTIFY,
    ];

    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR   = 'error';
    public const STATUS_SKIP    = 'skip';

    /**
     * @var    string[]
     * @since  0.1.0
     */
    public const STATUSES = [self::STATUS_SUCCESS, self::STATUS_WARNING, self::STATUS_ERROR, self::STATUS_SKIP];

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_TASK   = 'task';
    public const TRIGGER_SYSTEM = 'system';

    /**
     * @var    string[]
     * @since  0.1.0
     */
    public const TRIGGERS = [self::TRIGGER_MANUAL, self::TRIGGER_TASK, self::TRIGGER_SYSTEM];

    /**
     * Who triggered what's being logged right now. The task plugin switches
     * this to TRIGGER_TASK for the length of its run.
     *
     * @var    string
     * @since  0.1.0
     */
    private static string $trigger = self::TRIGGER_MANUAL;

    /**
     * @param   string  $trigger  TRIGGER_* constant.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function setTrigger(string $trigger): void
    {
        self::$trigger = \in_array($trigger, self::TRIGGERS, true) ? $trigger : self::TRIGGER_MANUAL;
    }

    /**
     * Record one event.
     *
     * @param   string  $action       ACTION_* constant.
     * @param   string  $status       STATUS_* constant.
     * @param   string  $element      Extension element ('' for site-wide events).
     * @param   string  $title        Extension title.
     * @param   string  $fromVersion  Version before.
     * @param   string  $toVersion    Version after.
     * @param   string  $message      One-line summary.
     * @param   string  $details      Verbose detail (installer messages, errors).
     * @param   int     $elapsedMs    Duration.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function log(
        string $action,
        string $status,
        string $element,
        string $title,
        string $fromVersion,
        string $toVersion,
        string $message,
        string $details = '',
        int $elapsedMs = 0
    ): void {
        if (!\in_array($action, self::ACTIONS, true) || !\in_array($status, self::STATUSES, true)) {
            return;
        }

        try {
            $db      = Factory::getContainer()->get(DatabaseInterface::class);
            $user    = Factory::getApplication()->getIdentity();
            $userId  = $user !== null ? (int) $user->id : 0;
            $created = Factory::getDate()->toSql();
            $trigger = self::$trigger;

            $element     = mb_substr($element, 0, 100);
            $title       = mb_substr($title, 0, 255);
            $fromVersion = mb_substr($fromVersion, 0, 50);
            $toVersion   = mb_substr($toVersion, 0, 50);
            $message     = mb_substr($message, 0, 1024);
            $details     = mb_substr($details, 0, 60000);
            $elapsedMs   = max(0, $elapsedMs);

            $query = $db->createQuery()
                ->insert($db->quoteName('#__csextensionmanager_log'))
                ->columns($db->quoteName([
                    'created', 'action', 'status', 'trigger_source', 'element', 'title',
                    'from_version', 'to_version', 'user_id', 'elapsed_ms', 'message', 'details',
                ]))
                ->values(':created, :action, :status, :trig, :element, :title, :fromv, :tov, :uid, :ms, :message, :details')
                ->bind(':created', $created)
                ->bind(':action', $action)
                ->bind(':status', $status)
                ->bind(':trig', $trigger)
                ->bind(':element', $element)
                ->bind(':title', $title)
                ->bind(':fromv', $fromVersion)
                ->bind(':tov', $toVersion)
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':ms', $elapsedMs, ParameterType::INTEGER)
                ->bind(':message', $message)
                ->bind(':details', $details);

            $db->setQuery($query)->execute();

            if (random_int(1, 50) === 1) {
                self::prune($db, (int) ParamsHelper::get()->get('retention_days', 365));
            }
        } catch (\Throwable $e) {
            Log::add('cs-extension-manager log write failed: ' . $e->getMessage(), Log::WARNING, 'com_csextensionmanager');
        }

        if ($status === self::STATUS_SUCCESS && \in_array($action, [self::ACTION_INSTALL, self::ACTION_UPDATE, self::ACTION_DOWNGRADE, self::ACTION_REINSTALL, self::ACTION_UNINSTALL], true)) {
            self::actionLog($action, $title !== '' ? $title : $element, $toVersion !== '' ? $toVersion : $fromVersion);
        }
    }

    /**
     * Delete rows older than the retention window. 0 = keep forever.
     *
     * @param   DatabaseInterface  $db    Database.
     * @param   int                $days  Retention in days.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function prune(DatabaseInterface $db, int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $cutoff = Factory::getDate('-' . $days . ' days')->toSql();

        $db->setQuery(
            $db->createQuery()
                ->delete($db->quoteName('#__csextensionmanager_log'))
                ->where($db->quoteName('created') . ' < :cutoff')
                ->bind(':cutoff', $cutoff)
        )->execute();
    }

    /**
     * Mirror into Joomla's User Actions Log (com_actionlogs), so the site's
     * audit trail shows who installed what through the manager.
     *
     * @param   string  $action   Action.
     * @param   string  $title    Extension title.
     * @param   string  $version  Version.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function actionLog(string $action, string $title, string $version): void
    {
        if ((int) ParamsHelper::get()->get('actionlogs', 1) !== 1) {
            return;
        }

        try {
            $app  = Factory::getApplication();
            $user = $app->getIdentity();

            if ($user === null || !$user->id || !class_exists(\Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel::class)) {
                return;
            }

            /** @var \Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel $model */
            $model = $app->bootComponent('com_actionlogs')->getMVCFactory()->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

            $model->addLog(
                [[
                    'action'      => $action,
                    'type'        => 'COM_CSEXTENSIONMANAGER',
                    'id'          => 0,
                    'title'       => $title . ($version !== '' ? ' ' . $version : ''),
                    'extension_name' => $title,
                    'userid'      => (int) $user->id,
                    'username'    => (string) $user->username,
                    'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . (int) $user->id,
                ]],
                'COM_CSEXTENSIONMANAGER_ACTIONLOG_' . strtoupper($action),
                'com_csextensionmanager',
                (int) $user->id
            );
        } catch (\Throwable $e) {
            // The manager's own history already has the entry.
        }
    }
}
