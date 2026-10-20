<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Field;

\defined('_JEXEC') or die;

use Cybersalt\Component\Csextensionmanager\Administrator\Helper\CatalogHelper;
use Cybersalt\Component\Csextensionmanager\Administrator\Helper\InstalledHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Picker of Cybersalt extensions installed on this site (from the cached
 * catalog; no network while the form renders). Values are element names.
 *
 * @since  0.1.0
 */
final class CatalogitemsField extends ListField
{
    /**
     * @var    string
     * @since  0.1.0
     */
    protected $type = 'Catalogitems';

    /**
     * @return  array
     *
     * @since   0.1.0
     */
    protected function getOptions()
    {
        $options = parent::getOptions();
        $cached  = CatalogHelper::getCached();
        $items   = $cached['items'] ?? [];

        $descriptors = array_map(
            static fn (array $i): array => ['type' => $i['type'], 'folder' => $i['folder'], 'element' => $i['element']],
            $items
        );

        $installed = InstalledHelper::findMany(array_values($descriptors));

        uasort($items, static fn (array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));

        foreach ($items as $key => $item) {
            if (!isset($installed[$key])) {
                continue;
            }

            $options[] = HTMLHelper::_('select.option', (string) $item['element'], (string) $item['title']);
        }

        if (\count($options) === \count(parent::getOptions()) && $cached === null) {
            $options[] = HTMLHelper::_('select.option', '', Text::_('COM_CSEXTENSIONMANAGER_FIELD_CATALOG_NOT_LOADED'), 'value', 'text', true);
        }

        return $options;
    }
}
