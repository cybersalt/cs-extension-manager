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
     * Items split into the view's sections.
     *
     * @return  array<string, array<string, array<string, mixed>>>  attention / installed / products / addons
     *
     * @since   0.1.0
     */
    public function getSections(): array
    {
        $sections = ['attention' => [], 'installed' => [], 'products' => [], 'addons' => []];

        foreach ($this->getItems() as $key => $item) {
            if (\in_array($item['status'], [StateHelper::STATUS_UPDATE, StateHelper::STATUS_BROKEN], true)) {
                $sections['attention'][$key] = $item;
            } elseif ($item['installed']) {
                $sections['installed'][$key] = $item;
            } elseif ($item['group'] === CatalogHelper::GROUP_ADDON) {
                $sections['addons'][$key] = $item;
            } else {
                $sections['products'][$key] = $item;
            }
        }

        return $sections;
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
     * Number of health issues worse than "info".
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function getIssueCount(): int
    {
        try {
            $issues = HealthHelper::check($this->getItems());
        } catch (\Throwable $e) {
            return 0;
        }

        return \count(array_filter($issues, static fn (array $i): bool => $i['severity'] !== HealthHelper::SEVERITY_INFO));
    }
}
