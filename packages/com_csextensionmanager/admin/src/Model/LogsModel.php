<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Model;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\LogHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * History list.
 *
 * @since  0.1.0
 */
final class LogsModel extends ListModel
{
    /**
     * Columns the list may be sorted by.
     *
     * @var    string[]
     * @since  0.1.0
     */
    private const ORDERABLE = ['a.id', 'a.created', 'a.action', 'a.status', 'a.trigger_source', 'a.title', 'a.to_version', 'a.elapsed_ms', 'u.name'];

    /**
     * @param   array  $config  Model config.
     *
     * @since   0.1.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'created', 'a.created',
                'action', 'a.action',
                'status', 'a.status',
                'trigger_source', 'a.trigger_source',
                'title', 'a.title',
                'to_version', 'a.to_version',
                'elapsed_ms', 'a.elapsed_ms',
                'user', 'u.name',
                // Filter-only keys
                'since', 'element', 'user_id', 'trigger',
            ];
        }

        parent::__construct($config);
    }

    /**
     * @param   string  $ordering   Default ordering column.
     * @param   string  $direction  Default direction.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function populateState($ordering = 'a.created', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @param   string  $id  Store id prefix.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    protected function getStoreId($id = '')
    {
        foreach (['search', 'action', 'status', 'trigger', 'element', 'user_id', 'since'] as $key) {
            $id .= ':' . $this->getState('filter.' . $key);
        }

        return parent::getStoreId($id);
    }

    /**
     * @return  QueryInterface
     *
     * @since   0.1.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName('a') . '.*')
            ->select($db->quoteName('u.name', 'user_name'))
            ->from($db->quoteName('#__csextensionmanager_log', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'));

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :searchid')
                    ->bind(':searchid', $searchId, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $query->where('(' . implode(' OR ', [
                    $db->quoteName('a.title') . ' LIKE :s1',
                    $db->quoteName('a.element') . ' LIKE :s2',
                    $db->quoteName('a.message') . ' LIKE :s3',
                    $db->quoteName('a.details') . ' LIKE :s4',
                ]) . ')')
                    ->bind([':s1', ':s2', ':s3', ':s4'], $like);
            }
        }

        $action = (string) $this->getState('filter.action');

        if (\in_array($action, LogHelper::ACTIONS, true)) {
            $query->where($db->quoteName('a.action') . ' = :action')->bind(':action', $action);
        }

        $status = (string) $this->getState('filter.status');

        if (\in_array($status, LogHelper::STATUSES, true)) {
            $query->where($db->quoteName('a.status') . ' = :status')->bind(':status', $status);
        }

        $trigger = (string) $this->getState('filter.trigger');

        if (\in_array($trigger, LogHelper::TRIGGERS, true)) {
            $query->where($db->quoteName('a.trigger_source') . ' = :trig')->bind(':trig', $trigger);
        }

        $element = trim((string) $this->getState('filter.element'));

        if ($element !== '' && preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $element)) {
            $query->where($db->quoteName('a.element') . ' = :element')->bind(':element', $element);
        }

        $userId = (string) $this->getState('filter.user_id');

        if ($userId !== '' && ctype_digit($userId)) {
            $uid = (int) $userId;
            $query->where($db->quoteName('a.user_id') . ' = :uid')->bind(':uid', $uid, ParameterType::INTEGER);
        }

        $sinceCutoff = self::sinceCutoff((string) $this->getState('filter.since'));

        if ($sinceCutoff !== null) {
            $query->where($db->quoteName('a.created') . ' >= :since')->bind(':since', $sinceCutoff);
        }

        $ordering  = (string) $this->getState('list.ordering', 'a.created');
        $direction = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if (!\in_array($ordering, self::ORDERABLE, true)) {
            $ordering = 'a.created';
        }

        $query->order($db->quoteName($ordering) . ' ' . $direction);

        if ($ordering !== 'a.id') {
            $query->order($db->quoteName('a.id') . ' DESC');
        }

        return $query;
    }

    /**
     * Counts for the stats bar (whole table, not the filtered view).
     *
     * @return  array<string, int>
     *
     * @since   0.1.0
     */
    public function getStats(): array
    {
        $db     = $this->getDatabase();
        $cutoff = Factory::getDate('-24 hours')->toSql();

        $select = [
            'COUNT(*) AS ' . $db->quoteName('total'),
            'SUM(CASE WHEN ' . $db->quoteName('created') . ' >= ' . $db->quote($cutoff) . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('last24h'),
        ];

        foreach (LogHelper::STATUSES as $status) {
            $select[] = 'SUM(CASE WHEN ' . $db->quoteName('status') . ' = ' . $db->quote($status) . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName($status);
        }

        foreach ([LogHelper::ACTION_INSTALL, LogHelper::ACTION_UPDATE] as $action) {
            $select[] = 'SUM(CASE WHEN ' . $db->quoteName('action') . ' = ' . $db->quote($action) . ' AND ' . $db->quoteName('status') . ' = ' . $db->quote(LogHelper::STATUS_SUCCESS) . ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('n_' . $action);
        }

        $row = $db->setQuery($db->createQuery()->select($select)->from($db->quoteName('#__csextensionmanager_log')))->loadAssoc() ?: [];

        $stats = [];

        foreach (['total', 'last24h', 'success', 'warning', 'error', 'skip', 'n_install', 'n_update'] as $key) {
            $stats[$key] = (int) ($row[$key] ?? 0);
        }

        return $stats;
    }

    /**
     * CSV of the filtered rows (RFC 4180, formula-injection safe).
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getCsv(): string
    {
        $headers = ['id', 'created_utc', 'action', 'status', 'trigger', 'element', 'title', 'from_version', 'to_version', 'user', 'elapsed_ms', 'message', 'details'];
        $rows    = [self::csvRow($headers)];

        foreach ($this->getItems() ?: [] as $item) {
            $rows[] = self::csvRow([
                (string) $item->id,
                (string) $item->created,
                (string) $item->action,
                (string) $item->status,
                (string) $item->trigger_source,
                (string) $item->element,
                (string) $item->title,
                (string) $item->from_version,
                (string) $item->to_version,
                (string) ($item->user_name ?? ''),
                (string) $item->elapsed_ms,
                (string) $item->message,
                (string) $item->details,
            ]);
        }

        return implode("\r\n", $rows) . "\r\n";
    }

    /**
     * Human-readable dump of the filtered rows, for support tickets.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function getTextDump(): string
    {
        $items = $this->getItems() ?: [];
        $stats = $this->getStats();

        $out  = "Cybersalt Extension Manager - History\n";
        $out .= 'Generated (UTC): ' . Factory::getDate()->toSql() . "\n";
        $out .= 'Joomla ' . JVERSION . ' / PHP ' . PHP_VERSION . "\n";
        $out .= \sprintf(
            "All-time: %d entries (last 24h: %d) | success %d | warning %d | error %d | skipped %d\n",
            $stats['total'],
            $stats['last24h'],
            $stats['success'],
            $stats['warning'],
            $stats['error'],
            $stats['skip']
        );
        $out .= 'Rows in this dump: ' . \count($items) . "\n";
        $out .= str_repeat('=', 78) . "\n\n";

        foreach ($items as $item) {
            $out .= \sprintf("#%d  [%s/%s]  %s UTC  (%s)\n", (int) $item->id, strtoupper((string) $item->action), strtoupper((string) $item->status), (string) $item->created, (string) $item->trigger_source);

            if ((string) $item->title !== '' || (string) $item->element !== '') {
                $out .= 'Extension: ' . (string) $item->title . ' (' . (string) $item->element . ")\n";
            }

            if ((string) $item->from_version !== '' || (string) $item->to_version !== '') {
                $out .= 'Version:   ' . ((string) $item->from_version !== '' ? (string) $item->from_version : '-') . ' -> ' . ((string) $item->to_version !== '' ? (string) $item->to_version : '-') . "\n";
            }

            $out .= 'Message:   ' . (string) $item->message . "\n";

            if ((string) $item->details !== '') {
                $out .= "Details:\n" . (string) $item->details . "\n";
            }

            $out .= str_repeat('-', 78) . "\n";
        }

        return $out;
    }

    /**
     * Cut-off timestamp for the time-range filter.
     *
     * @param   string  $since  '1h', '24h', '7d', '30d' or ''.
     *
     * @return  string|null
     *
     * @since   0.1.0
     */
    public static function sinceCutoff(string $since): ?string
    {
        return match ($since) {
            '1h'    => Factory::getDate('-1 hour')->toSql(),
            '24h'   => Factory::getDate('-24 hours')->toSql(),
            '7d'    => Factory::getDate('-7 days')->toSql(),
            '30d'   => Factory::getDate('-30 days')->toSql(),
            default => null,
        };
    }

    /**
     * One CSV row; cells starting with = + - @ get a leading quote so they
     * can't become spreadsheet formulas.
     *
     * @param   string[]  $fields  Cell values.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function csvRow(array $fields): string
    {
        return implode(',', array_map(
            static function (string $value): string {
                $value = preg_replace('/(?![\t\n\r])[[:cntrl:]]/', '', $value) ?? '';
                $value = str_replace("\x7F", '', $value);

                if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
                    $value = "'" . $value;
                }

                return '"' . str_replace('"', '""', $value) . '"';
            },
            $fields
        ));
    }
}
