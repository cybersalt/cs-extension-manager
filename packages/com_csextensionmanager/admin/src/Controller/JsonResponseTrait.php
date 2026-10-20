<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Controller;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * JSON answers for the browser-side queue, plus catalog item lookup by key.
 *
 * @since  0.1.0
 */
trait JsonResponseTrait
{
    /**
     * Send `{success, message, data}` and stop.
     *
     * @param   boolean               $ok       Success.
     * @param   string                $message  Plain-text message.
     * @param   array<string, mixed>  $data     Extra data.
     * @param   int                   $status   HTTP status.
     *
     * @return  never
     *
     * @since   0.1.0
     */
    private function sendJson(bool $ok, string $message, array $data = [], int $status = 200): never
    {
        $app = Factory::getApplication();

        // Anything an extension's install script echoed must not reach the client.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);

        if ($status !== 200) {
            $app->setHeader('status', (string) $status, true);
        }

        $app->sendHeaders();

        echo json_encode(
            ['success' => $ok, 'message' => $message, 'data' => $data],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        $app->close();

        exit;
    }

    /**
     * Token + permission gate for JSON tasks. Answers 403 JSON instead of
     * Joomla's HTML error page.
     *
     * @param   callable  $permission  PermissionHelper::require*() to run.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function guardJson(callable $permission): void
    {
        if (!$this->checkToken('post', false)) {
            $this->sendJson(false, Text::_('JINVALID_TOKEN_NOTICE'), [], 403);
        }

        try {
            $permission();
        } catch (NotAllowed $e) {
            $this->sendJson(false, Text::_('JERROR_ALERTNOAUTHOR'), [], 403);
        }
    }

    /**
     * The enriched catalog item named by the `key` request variable.
     *
     * @return  array<string, mixed>|null
     *
     * @since   0.1.0
     */
    private function requestedItem(): ?array
    {
        $key = strtolower(trim($this->input->post->getString('key', $this->input->getString('key', ''))));

        if (!preg_match('/^[a-z]+:[a-z0-9_]*:[a-z0-9_.-]+$/', $key)) {
            return null;
        }

        $catalog = CatalogHelper::get();

        if (!isset($catalog['items'][$key])) {
            return null;
        }

        $items = StateHelper::enrich([$key => $catalog['items'][$key]]);

        return $items[$key] ?? null;
    }
}
