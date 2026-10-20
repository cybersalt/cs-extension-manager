<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Model\LogsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * History actions: CSV download, plain-text dump, delete selected, clear all.
 *
 * @since  0.1.0
 */
final class LogsController extends BaseController
{
    /**
     * Stream the currently filtered rows as CSV. Token-checked GET.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function download(): void
    {
        $this->checkToken('get');
        PermissionHelper::requireView();

        $csv  = $this->getLogsModel()->getCsv();
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', 'csextensionmanager-log-' . gmdate('Ymd-His') . '.csv');

        $this->sendFile($csv, 'text/csv; charset=utf-8', 'attachment; filename="' . $name . '"');
    }

    /**
     * The currently filtered rows as plain text, for a support ticket.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function dump(): void
    {
        $this->checkToken('get');
        PermissionHelper::requireView();

        $this->sendFile($this->getLogsModel()->getTextDump(), 'text/plain; charset=utf-8', 'inline');
    }

    /**
     * Delete the ticked rows.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function delete(): void
    {
        $this->checkToken();
        PermissionHelper::requireFix();

        $ids = array_values(array_filter(
            array_map('intval', (array) $this->input->get('cid', [], 'array')),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids === []) {
            $this->setMessage(Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'), 'warning');
        } else {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__csextensionmanager_log'))
                    ->whereIn($db->quoteName('id'), $ids, ParameterType::INTEGER)
            )->execute();

            $this->setMessage(Text::plural('COM_CSEXTENSIONMANAGER_LOGS_N_DELETED', \count($ids)));
        }

        $this->setRedirect(Route::_('index.php?option=com_csextensionmanager&view=logs', false));
    }

    /**
     * Empty the whole history (confirmed in a modal, POST + token).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function purge(): void
    {
        $this->checkToken();
        PermissionHelper::requireFix();

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__csextensionmanager_log'))->execute();

        $this->setMessage(Text::_('COM_CSEXTENSIONMANAGER_LOGS_PURGED'));
        $this->setRedirect(Route::_('index.php?option=com_csextensionmanager&view=logs', false));
    }

    /**
     * The list model with the Logs view's user state, page limit lifted.
     *
     * @return  LogsModel
     *
     * @since   0.1.0
     */
    private function getLogsModel(): LogsModel
    {
        /** @var LogsModel $model */
        $model = $this->getModel('Logs', 'Administrator', ['ignore_request' => false]);

        $model->getState();
        $model->setState('list.start', 0);
        $model->setState('list.limit', 0);

        return $model;
    }

    /**
     * Send a body with safe headers and stop.
     *
     * @param   string  $body         Response body.
     * @param   string  $contentType  Content-Type header.
     * @param   string  $disposition  Content-Disposition header.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function sendFile(string $body, string $contentType, string $disposition): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', $contentType, true);
        $app->setHeader('Content-Disposition', $disposition, true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->sendHeaders();

        echo $body;

        $app->close();
    }
}
