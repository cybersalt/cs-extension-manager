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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Users who appear in the history (filter picker). 0 = scheduled task / system.
 *
 * @since  0.1.0
 */
final class LoguserField extends ListField
{
    /**
     * @var    string
     * @since  0.1.0
     */
    protected $type = 'Loguser';

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
                ->select('DISTINCT ' . $db->quoteName('l.user_id') . ', ' . $db->quoteName('u.name'))
                ->from($db->quoteName('#__csextensionmanager_log', 'l'))
                ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('l.user_id'))
                ->order($db->quoteName('u.name'))
        )->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $label = (int) $row->user_id === 0
                ? Text::_('COM_CSEXTENSIONMANAGER_USER_SYSTEM')
                : (string) ($row->name ?? ('#' . (int) $row->user_id));

            $options[] = HTMLHelper::_('select.option', (string) (int) $row->user_id, $label);
        }

        return $options;
    }
}
