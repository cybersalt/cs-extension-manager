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
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\HealthHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\PermissionHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Health-check fixes.
 *
 * @since  0.1.0
 */
final class HealthController extends BaseController
{
    /**
     * Fix one issue. POST: issue (id from the health view).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function fix(): void
    {
        $this->checkToken();
        PermissionHelper::requireFix();

        $id     = $this->input->post->getString('issue', '');
        $items  = StateHelper::enrich(CatalogHelper::get()['items']);
        $issues = HealthHelper::check($items);
        $issue  = null;

        foreach ($issues as $candidate) {
            if (hash_equals((string) $candidate['id'], $id)) {
                $issue = $candidate;
                break;
            }
        }

        if ($issue === null || $issue['fix'] === '') {
            $this->setMessage(Text::_('COM_CSEXTENSIONMANAGER_HEALTH_ALREADY_FIXED'), 'notice');
            $this->back();

            return;
        }

        // Installing and enabling extensions also needs the install permission.
        if (\in_array($issue['fix'], ['reinstall', 'install_parent', 'enable_extension'], true)) {
            PermissionHelper::requireInstall();
            @set_time_limit(300);
        }

        $result = HealthHelper::fix($issue, $items);

        $this->setMessage($result['message'], $result['ok'] ? 'message' : 'error');
        $this->back();
    }

    /**
     * Apply every safe fix (update-site bookkeeping only).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function fixall(): void
    {
        $this->checkToken();
        PermissionHelper::requireFix();

        $fixed  = 0;
        $failed = 0;

        // Fixes can resolve other issues (sync_dlid covers every site), so re-check each round.
        for ($round = 0; $round < 5; $round++) {
            $items  = StateHelper::enrich(CatalogHelper::get()['items']);
            $issues = array_values(array_filter(
                HealthHelper::check($items),
                static fn (array $i): bool => \in_array($i['fix'], HealthHelper::SAFE_FIXES, true)
            ));

            if ($issues === []) {
                break;
            }

            $roundFixed = 0;

            foreach ($issues as $issue) {
                $result = HealthHelper::fix($issue, $items);
                $result['ok'] ? $roundFixed++ : $failed++;
            }

            $fixed += $roundFixed;

            // Nothing moved: stop instead of failing the same fixes again.
            if ($roundFixed === 0) {
                break;
            }
        }

        $this->setMessage(Text::sprintf('COM_CSEXTENSIONMANAGER_HEALTH_FIXALL_DONE', $fixed, $failed), $failed > 0 ? 'warning' : 'message');
        $this->back();
    }

    /**
     * Re-check against a fresh catalog. GET with token.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function recheck(): void
    {
        $this->checkToken('get');
        PermissionHelper::requireView();

        CatalogHelper::get(true);

        $this->back();
    }

    /**
     * @return  void
     *
     * @since   0.1.0
     */
    private function back(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_csextensionmanager&view=health', false));
    }
}
