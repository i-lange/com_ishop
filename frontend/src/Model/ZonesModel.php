<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Модель зон доставки com_iShop
 * @since 1.0.0
 */
class ZonesModel extends ListModel
{
    /**
     * Список доступных зон доставки
     * @var array
     * @since 1.0.0
     */
    protected static $_zonesList = null;

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        //parent::populateState();

        $app = Factory::getApplication();

        $params	= $app->getParams();
        $this->setState('params', $params);

        // Фильтрация по состоянию публикации
        $this->setState('filter.published', 1);

        // Количество зон, все
        $this->setState('list.limit', 0);
        $this->setState('list.ordering', 'a.ordering');
        $this->setState('list.direction', 'ASC');
    }

    /**
     * Метод для получения идентификатора на основе конфигурации модели
     * Это необходимо, поскольку модель используется компонентом и различными модулями,
     * которым могут понадобиться разные наборы данных или разный порядок сортировки
     *
     * @param   string  $id  Префикс
     *
     * @return string Идентификатор
     * @since 1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . serialize($this->getState('filter.published'));

        return parent::getStoreId($id);
    }

    /**
     * Основной запрос для получения списка записей на основе состояния модели
     * @return \Joomla\Database\QueryInterface
     * @throws \Exception
     * @since 1.0.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select(
                $this->getState(
                    'list.select',
                    [
                        $db->quoteName('a.id'),
                        $db->quoteName('a.title'),
                        $db->quoteName('a.alias'),
                        $db->quoteName('a.ordering'),
                    ]
                )
            )
            ->from($db->quoteName('#__ishop_delivery_zones', 'a'));

        // Фильтрация, по состоянию публикации
        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $published = (int) $published;
            $query
                ->where($db->quoteName('a.state') . ' = :published')
                ->bind(':published', $published, ParameterType::INTEGER);
        } elseif (is_array($published)) {
            $query->whereIn($db->quoteName('a.state'), $query->bindArray($published));
        }

        // Порядок сортировки списка
        $orderCol = $this->getState('list.ordering', 'a.ordering');
        $orderDirn = $this->getState('list.direction', 'ASC');
        $ordering = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);
        $query->order($ordering);

        return $query;
    }

    /**
     * Метод для получения списка
     * зон доставки
     *
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItems()
    {
        if (!self::$_zonesList) {
            $list = parent::getItems();
            self::$_zonesList = array_combine(array_column($list, 'id'), $list);
        }

        $active = $this->getActive();

        foreach (self::$_zonesList as $item) {
            $item->is_active = false;
            if ($item->id === $active) {
                $item->is_active = true;
            }
        }

        return self::$_zonesList;
    }

    /**
     * Метод для получения текущей выбранной зоны
     * @return int Идентификатор активной зоны
     * @throws \Exception
     * @since 1.0.0
     */
    public function getActive()
    {
        // Получаем данные пользователя
        $userData = $this->getMVCFactory()->createModel('User', 'Site')->getItem();

        if (empty($userData) || !$userData->zone_id) {
            $params	= $this->getState('params');
            return $params->get('default_zone');
        }

        return $userData->zone_id;
    }

    /**
     * Метод для получения данных зоны
     * по ее идентификатору
     *
     * @return object Объект или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getZone(int $pk = null)
    {
        $pk = $pk ?: $this->getActive();

        if (!self::$_zonesList) {
            $this->getItems();
        }

        return self::$_zonesList[$pk];
    }
}
