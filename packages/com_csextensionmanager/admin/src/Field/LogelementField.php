<?php

/**
 * @package    com_csextensionmanager
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Cybersalt\Component\Csextensionmanager\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Extensions that appear in the history (filter picker).
 *
 * @since  0.1.0
 */
final class LogelementField extends ListField
{
    /**
     * @var    string
     * @since  0.1.0
     */
    protected $type = 'Logelement';

    /**
     * @return  array
     *
     * @since   0.1.0
     */
    protected function getOptions()
    {
        $options = parent::getOptions();
        $db      = Factory::getContainer()->get(DatabaseInterface::class);

        $rows = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName('element') . ', MAX(' . $db->quoteName('title') . ') AS ' . $db->quoteName('title'))
                ->from($db->quoteName('#__csextensionmanager_log'))
                ->where($db->quoteName('element') . ' <> ' . $db->quote(''))
                ->group($db->quoteName('element'))
                ->order($db->quoteName('title'))
        )->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $options[] = HTMLHelper::_('select.option', (string) $row->element, (string) ($row->title !== '' ? $row->title : $row->element));
        }

        return $options;
    }
}
