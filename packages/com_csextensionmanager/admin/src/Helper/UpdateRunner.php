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
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Unattended update check (Joomla Scheduler task): refresh the catalog,
 * optionally install updates up to a chosen size, and email a summary.
 *
 * @since  0.1.0
 */
final class UpdateRunner
{
    public const MODE_NOTIFY = 'notify';
    public const MODE_PATCH  = 'patch';
    public const MODE_MINOR  = 'minor';
    public const MODE_ALL    = 'all';

    public const NOTIFY_CHANGES = 'changes';
    public const NOTIFY_ALWAYS  = 'always';
    public const NOTIFY_NEVER   = 'never';

    /**
     * Cache row remembering what the last email reported, so the same
     * pending updates aren't mailed every day.
     *
     * @var    string
     * @since  0.1.0
     */
    private const NOTIFIED_KEY = 'notified.v1';

    /**
     * @param   string  $mode    MODE_* constant.
     * @param   string  $notify  NOTIFY_* constant.
     *
     * @return  array{pending: array<int, array<string, string>>, installed: array<int, array<string, string>>, failed: array<int, array<string, string>>, skipped: array<int, array<string, string>>, emailed: bool, errors: string[]}
     *
     * @since   0.1.0
     */
    public static function run(string $mode, string $notify): array
    {
        $catalog  = CatalogHelper::get(true);
        $items    = StateHelper::enrich($catalog['items']);
        $exclude  = array_map('strval', (array) ParamsHelper::get()->get('exclude_auto', []));
        $allowed  = match ($mode) {
            self::MODE_PATCH => ['patch'],
            self::MODE_MINOR => ['patch', 'minor'],
            self::MODE_ALL   => ['patch', 'minor', 'major'],
            default          => [],
        };

        $summary = ['pending' => [], 'installed' => [], 'failed' => [], 'skipped' => [], 'emailed' => false, 'errors' => $catalog['errors']];

        foreach ($items as $item) {
            if (!\in_array($item['status'], [StateHelper::STATUS_UPDATE, StateHelper::STATUS_BROKEN], true)) {
                continue;
            }

            $row = [
                'title' => (string) $item['title'],
                'element' => (string) $item['element'],
                'from'  => (string) $item['installed_version'],
                'to'    => (string) $item['latest_version'],
                'type'  => (string) $item['update_type'],
                'note'  => '',
            ];

            // Check-only mode: just report it.
            if ($allowed === []) {
                $summary['pending'][] = $row;
                continue;
            }

            $reason = '';

            if (!empty($item['self'])) {
                $reason = Text::_('COM_CSEXTENSIONMANAGER_TASK_SKIP_SELF');
            } elseif (\in_array((string) $item['element'], $exclude, true)) {
                $reason = Text::_('COM_CSEXTENSIONMANAGER_TASK_SKIP_EXCLUDED');
            } elseif (!$item['compatible']) {
                $reason = (string) $item['incompatible_reason'];
            } elseif (!$item['entitled']) {
                $reason = Text::_('COM_CSEXTENSIONMANAGER_TASK_SKIP_NOT_ENTITLED');
            } elseif ($item['status'] === StateHelper::STATUS_UPDATE && !\in_array($item['update_type'], $allowed, true)) {
                $reason = Text::sprintf('COM_CSEXTENSIONMANAGER_TASK_SKIP_SIZE', $item['update_type']);
            }

            if ($reason !== '') {
                $row['note']          = $reason;
                $summary['skipped'][] = $row;
                LogHelper::log(LogHelper::ACTION_UPDATE, LogHelper::STATUS_SKIP, $row['element'], $row['title'], $row['from'], $row['to'], $reason);
                continue;
            }

            $result = InstallHelper::install($item);

            if ($result['ok']) {
                $row['to']              = $result['to'];
                $summary['installed'][] = $row;
            } else {
                $row['note']         = $result['message'];
                $summary['failed'][] = $row;
            }
        }

        $summary['emailed'] = self::notify($summary, $notify);

        return $summary;
    }

    /**
     * Email the summary when the settings say so.
     *
     * @param   array<string, mixed>  $summary  Run summary.
     * @param   string                $notify   NOTIFY_* constant.
     *
     * @return  boolean  Sent.
     *
     * @since   0.1.0
     */
    private static function notify(array $summary, string $notify): bool
    {
        if ($notify === self::NOTIFY_NEVER) {
            return false;
        }

        $somethingHappened = $summary['installed'] !== [] || $summary['failed'] !== [];
        $outstanding       = array_merge($summary['pending'], $summary['skipped']);
        $fingerprint       = md5((string) json_encode(array_map(static fn (array $r): string => $r['element'] . '@' . $r['to'], $outstanding)));

        if ($notify === self::NOTIFY_CHANGES) {
            $nothingNew = !$somethingHappened && ($outstanding === [] || $fingerprint === self::lastFingerprint());

            if ($nothingNew) {
                return false;
            }
        }

        $recipients = self::recipients();

        if ($recipients === []) {
            LogHelper::log(LogHelper::ACTION_NOTIFY, LogHelper::STATUS_WARNING, '', '', '', '', Text::_('COM_CSEXTENSIONMANAGER_TASK_NO_RECIPIENTS'));

            return false;
        }

        $app      = Factory::getApplication();
        $siteName = (string) $app->get('sitename', Uri::root());
        $subject  = Text::sprintf('COM_CSEXTENSIONMANAGER_MAIL_SUBJECT', $siteName);
        $body     = self::body($summary, $siteName);

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->addRecipient($recipients);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(false);
            $mailer->send();
        } catch (\Throwable $e) {
            LogHelper::log(LogHelper::ACTION_NOTIFY, LogHelper::STATUS_ERROR, '', '', '', '', Text::_('COM_CSEXTENSIONMANAGER_TASK_MAIL_FAILED'), $e->getMessage());

            return false;
        }

        self::rememberFingerprint($fingerprint);
        LogHelper::log(LogHelper::ACTION_NOTIFY, LogHelper::STATUS_SUCCESS, '', '', '', '', Text::sprintf('COM_CSEXTENSIONMANAGER_TASK_MAIL_SENT', implode(', ', $recipients)), $body);

        return true;
    }

    /**
     * Plain-text email body.
     *
     * @param   array<string, mixed>  $summary   Run summary.
     * @param   string                $siteName  Site name.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function body(array $summary, string $siteName): string
    {
        $line = static fn (array $r): string => '  - ' . $r['title'] . ': ' . ($r['from'] !== '' ? $r['from'] : '?') . ' -> ' . $r['to'] . ($r['note'] !== '' ? '  (' . $r['note'] . ')' : '');
        $out  = Text::sprintf('COM_CSEXTENSIONMANAGER_MAIL_INTRO', $siteName, Uri::root()) . "\n\n";

        foreach (
            [
            'installed' => 'COM_CSEXTENSIONMANAGER_MAIL_INSTALLED',
            'failed'    => 'COM_CSEXTENSIONMANAGER_MAIL_FAILED',
            'pending'   => 'COM_CSEXTENSIONMANAGER_MAIL_PENDING',
            'skipped'   => 'COM_CSEXTENSIONMANAGER_MAIL_SKIPPED',
            ] as $group => $heading
        ) {
            if ($summary[$group] === []) {
                continue;
            }

            $out .= Text::_($heading) . "\n" . implode("\n", array_map($line, $summary[$group])) . "\n\n";
        }

        if ($summary['installed'] === [] && $summary['failed'] === [] && $summary['pending'] === [] && $summary['skipped'] === []) {
            $out .= Text::_('COM_CSEXTENSIONMANAGER_MAIL_ALL_CURRENT') . "\n\n";
        }

        if ($summary['errors'] !== []) {
            $out .= Text::_('COM_CSEXTENSIONMANAGER_MAIL_ERRORS') . "\n  - " . implode("\n  - ", $summary['errors']) . "\n\n";
        }

        $out .= Text::_('COM_CSEXTENSIONMANAGER_MAIL_MANAGE') . "\n" . Uri::root() . "administrator/index.php?option=com_csextensionmanager\n\n";
        $out .= "-- \n" . Text::_('COM_CSEXTENSIONMANAGER_MAIL_FOOTER') . "\n";

        return $out;
    }

    /**
     * Configured addresses, or every Super User who receives system emails.
     *
     * @return  string[]
     *
     * @since   0.1.0
     */
    public static function recipients(): array
    {
        $configured = preg_split('/[\s,;]+/', (string) ParamsHelper::get()->get('notify_emails', '')) ?: [];
        $emails     = array_values(array_unique(array_filter($configured, static fn (string $e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL))));

        if ($emails !== []) {
            return $emails;
        }

        // Same rule as Joomla's own update notifications: Super Users (group 8)
        // who have "Receive System Emails" switched on.
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $super = 8;
        $query = $db->createQuery()
            ->select('DISTINCT ' . $db->quoteName('u.email'))
            ->from($db->quoteName('#__users', 'u'))
            ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm'), $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('m.group_id') . ' = :gid')
            ->where($db->quoteName('u.block') . ' = 0')
            ->where($db->quoteName('u.sendEmail') . ' = 1')
            ->bind(':gid', $super, ParameterType::INTEGER);

        return array_values(array_filter($db->setQuery($query)->loadColumn() ?: [], static fn ($e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    /**
     * @return  string
     *
     * @since   0.1.0
     */
    private static function lastFingerprint(): string
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            return (string) $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName('data'))
                    ->from($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = ' . $db->quote(self::NOTIFIED_KEY))
            )->loadResult();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param   string  $fingerprint  What was just reported.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private static function rememberFingerprint(string $fingerprint): void
    {
        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $key = self::NOTIFIED_KEY;
            $now = Factory::getDate()->toSql();

            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__csextensionmanager_cache'))
                    ->where($db->quoteName('cache_key') . ' = :key')
                    ->bind(':key', $key)
            )->execute();

            $db->setQuery(
                $db->createQuery()
                    ->insert($db->quoteName('#__csextensionmanager_cache'))
                    ->columns($db->quoteName(['cache_key', 'fetched', 'data']))
                    ->values(':key, :fetched, :data')
                    ->bind(':key', $key)
                    ->bind(':fetched', $now)
                    ->bind(':data', $fingerprint)
            )->execute();
        } catch (\Throwable $e) {
            // Worst case the same summary goes out again tomorrow.
        }
    }
}
