<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Класс helper компонента
 * @since 1.0.0
 */
class IshopHelper
{
    /**
     * Возвращает список действий, которые могут быть выполнены
     * @return \stdClass
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getActions(): \stdClass
    {
        $user = Factory::getApplication()->getIdentity();
        $result = new \stdClass();

        $actions = [
            'core.admin',
            'core.options',
            'core.manage',
            'core.create',
            'core.delete',
            'core.edit',
            'core.edit.state',
            'core.edit.own',
            'core.edit.value',
        ];

        foreach ($actions as $action) {
            $result->$action = $user->authorise($action, 'com_ishop');
        }

        return $result;
    }

    /**
     * Возвращает Id супер администратора
     * @return int Id пользователя при успешном выполнении
     * @since 1.0.0
     */
    private static function getAdminId(): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Выбираем все Id пользователей с правами администратора
        $query
            ->select($db->quoteName('user.id'))
            ->from($db->quoteName('#__users', 'user'))
            ->join(
                'LEFT',
                $db->quoteName('#__user_usergroup_map', 'map'),
                $db->quoteName('map.user_id') . ' = ' . $db->quoteName('user.id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__usergroups', 'grp'),
                $db->quoteName('map.group_id') . ' = ' . $db->quoteName('grp.id')
            )
            ->where(
                $db->quoteName('grp.title') . ' = ' . $db->quote('Super Users')
            );

        $db->setQuery($query);

        // Берем первый из найденных
        $id = $db->loadResult();

        if (!$id || $id instanceof \Exception) {
            return 0;
        }

        return $id;
    }
}