<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Model;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\AccountHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\HealthHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\StateHelper;
use Joomla\CMS\MVC\Model\BaseModel;

/**
 * The catalog joined with what's installed, grouped for the Extensions view.
 *
 * @since  0.1.0
 */
final class ExtensionsModel extends BaseModel
{
    /**
     * @var    array<string, mixed>|null
     * @since  0.1.0
     */
    private ?array $catalog = null;

    /**
     * @var    array<string, array<string, mixed>>|null
     * @since  0.1.0
     */
    private ?array $items = null;

    /**
     * Catalog meta: fetched, source, errors.
     *
     * @return  array<string, mixed>
     *
     * @since   0.1.0
     */
    public function getCatalog(): array
    {
        if ($this->catalog === null) {
            $this->catalog = CatalogHelper::get();

            // Re-check what the download ID unlocks once a day (no-op otherwise).
            if (AccountHelper::getDlid() !== '') {
                AccountHelper::verify();
            }
        }

        return $this->catalog;
    }

    /**
     * Enriched items, sorted: products before add-ons, then by title.
     *
     * @return  array<string, array<string, mixed>>
     *
     * @since   0.1.0
     */
    public function getItems(): array
    {
        if ($this->items === null) {
            $items = StateHelper::enrich($this->getCatalog()['items']);

            uasort($items, static function (array $a, array $b): int {
                $group = static fn (array $i): int => $i['group'] === CatalogHelper::GROUP_PRODUCT ? 0 : 1;

                return [$group($a), strtolower((string) $a['title'])] <=> [$group($b), strtolower((string) $b['title'])];
            });

            $this->items = $items;
        }

        return $this->items;
    }

    /**
     * @return  array<string, int>
     *
     * @since   0.1.0
     */
    public function getCounts(): array
    {
        return StateHelper::counts($this->getItems());
    }

    /**
     * The health issues the Health Check page shows (hidden notes left out)
     * and the colour that goes with them: success with none, danger when
     * something is a problem, warning otherwise.
     *
     * @return  array{count: int, tone: string, notes_only: bool}
     *
     * @since   0.1.0
     */
    public function getIssueSummary(): array
    {
        try {
            $issues = HealthHelper::partition(HealthHelper::check($this->getItems()))['visible'];
        } catch (\Throwable $e) {
            return ['count' => 0, 'tone' => 'success', 'notes_only' => false];
        }

        $severities = array_values(array_unique(array_column($issues, 'severity')));

        return [
            'count'      => \count($issues),
            'tone'       => $issues === [] ? 'success' : (\in_array(HealthHelper::SEVERITY_DANGER, $severities, true) ? 'danger' : 'warning'),
            'notes_only' => $issues !== [] && $severities === [HealthHelper::SEVERITY_INFO],
        ];
    }
}
