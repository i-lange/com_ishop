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
use Joomla\Registry\Registry;

/**
 * Модель способов оплаты частями com_iShop
 * @since 1.0.0
 */
class PartsModel extends ListModel
{
    /**
     * Список способов оплаты частями
     * @var array
     * @since 1.0.0
     */
    protected static $_partsList = null;

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
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
                        $db->quoteName('a.type'),
                        $db->quoteName('a.introtext'),
                        $db->quoteName('a.fulltext'),
                        $db->quoteName('a.images'),
                        $db->quoteName('a.icon'),
                        $db->quoteName('a.attribs'),
                        $db->quoteName('a.ordering'),
                    ]
                )
            )
            ->from($db->quoteName('#__ishop_payment_parts', 'a'));

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
        if (!self::$_partsList) {
            $list = parent::getItems();

            // Преобразуем поля json в объекты и массивы
            foreach ($list as $part) {
                $registry      = new Registry($part->images);
                $part->images = $registry->toObject();

                $registry      = new Registry($part->attribs);
                $part->attribs = $registry->toArray();
            }

            self::$_partsList = $list;
        }

        return self::$_partsList;
    }
}
