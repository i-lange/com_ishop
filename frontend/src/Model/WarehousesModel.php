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
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Модель списка складов
 * @since 1.0.0
 */
class WarehousesModel extends ListModel
{
    /**
     * Список доступных складов
     * @var array
     * @since 1.0.0
     */
    protected static $_warehousesList = null;

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
        $this->setState('filter.language', Multilanguage::isEnabled());

        $params	= $app->getParams();
        $this->setState('params', $params);

        // Фильтрация по состоянию публикации
        $this->setState('filter.published', 1);

        // Фильтрация по метке ПВЗ
        $this->setState('filter.point', 1);

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
                        $db->quoteName('a.introtext'),
                        $db->quoteName('a.fulltext'),
                        $db->quoteName('a.address'),
                        $db->quoteName('a.latitude'),
                        $db->quoteName('a.longitude'),
                        $db->quoteName('a.images'),
                        $db->quoteName('a.icon'),
                        $db->quoteName('a.emoji'),
                        $db->quoteName('a.ordering'),
                        $db->quoteName('a.bitrix24_id'),
                        $db->quoteName('a.system1c_guid'),
                    ]
                )
            )
            ->from($db->quoteName('#__ishop_warehouses', 'a'));

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

        // Фильтрация, языку контента
        if ($this->getState('filter.language')) {
            $query->whereIn($db->quoteName('a.language'), [Factory::getApplication()->getLanguage()->getTag(), '*'], ParameterType::STRING);
        }

        // Фильтрация по метке ПВЗ
        $point = $this->getState('filter.point');
        if (is_numeric($point)) {
            $point = (int) $point;
            $query
                ->where($db->quoteName('a.point') . ' = :point')
                ->bind(':point', $point, ParameterType::INTEGER);
            $this->setState('filter.point', 1);
        } elseif (is_array($point)) {
            $query->whereIn($db->quoteName('a.point'), $query->bindArray($point));
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
        if (!self::$_warehousesList) {
            $list = parent::getItems();
            self::$_warehousesList = array_combine(array_column($list, 'id'), $list);
        }

        return self::$_warehousesList;
    }
}
