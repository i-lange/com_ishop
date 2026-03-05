<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Utilities\ArrayHelper;
use Joomla\Database\ParameterType;

/**
 * Модель списка зон доставки
 * @since 1.0.0
 */
class ZonesModel extends ListModel
{
    /**
     * Конструктор
     * @param array $config Массив параметров, необязательно
     * @throws \Exception
     * @since 1.0.0
     * @see JController
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'alias', 'a.alias',
                'state', 'a.state',
                'published', 'a.published',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'created_by_alias', 'a.created_by_alias',
                'modified', 'a.modified',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'ordering', 'a.ordering',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @param string $ordering Порядок элементов
     * @param string $direction Направление сортировки
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = 'a.id', $direction = 'asc')
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '');
        $this->setState('filter.state', $published);

        parent::populateState($ordering, $direction);
    }

    /**
     * Метод для получения идентификатора на основе конфигурации модели 
     * Это необходимо, поскольку модель используется компонентом и различными модулями, 
     * которым могут понадобиться разные наборы данных или разный порядок сортировки
     * @param string $id
     * @return string Идентификатор
     * @since 1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Составляем запрос к базе данных, для выборки списка записей
     * @return \Joomla\Database\QueryInterface
     * @throws \Exception
     * @since 1.0.0
     */
    protected function getListQuery()
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $user  = $this->getCurrentUser();

        $query
            ->select(
                $this->getState('list.select',
                    [
                        $db->quoteName('a.id'),
                        $db->quoteName('a.title'),
                        $db->quoteName('a.alias'),
                        $db->quoteName('a.state'),
                        $db->quoteName('a.created'),
                        $db->quoteName('a.created_by'),
                        $db->quoteName('a.created_by_alias'),
                        $db->quoteName('a.modified'),
                        $db->quoteName('a.checked_out'),
                        $db->quoteName('a.checked_out_time'),
                        $db->quoteName('a.ordering'),
                    ]
                )
            )
            ->select(
                [
                    $db->quoteName('users_checked.name', 'editor'),
                    $db->quoteName('users_created.name', 'author_name'),
                ]
            )
            ->from($db->quoteName('#__ishop_delivery_zones', 'a'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_checked'),
                $db->quoteName('users_checked.id') . ' = ' . $db->quoteName('a.checked_out'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_created'),
                $db->quoteName('users_created.id') . ' = ' . $db->quoteName('a.created_by'));

        // Фильтрация по состоянию публикации
        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $published = (int) $published;
            $query
                ->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $published, ParameterType::INTEGER);
        } elseif (empty($published)) {
            $published = [0, 1];
            $query->whereIn($db->quoteName('a.state'), $published);
        } elseif (is_array($published)) {
            $published = ArrayHelper::toInteger($published);
            $query->whereIn($db->quoteName('a.state'), $published);
        }

        // Фильтрация по автору
        $authorId = $this->getState('filter.author_id');
        if (is_numeric($authorId)) {
            $authorId = (int) $authorId;
            $type     = $this->getState('filter.author_id.include', true) ? ' = ' : ' <> ';
            $query
                ->where($db->quoteName('a.created_by') . $type . ':authorId')
                ->bind(':authorId', $authorId, ParameterType::INTEGER);
        } elseif (is_array($authorId)) {
            // Проверяем, есть ли в массиве by_me
            if (in_array('by_me', $authorId)) {
                // Заменяем by_me на идентификатор текущего пользователя
                $authorId['by_me'] = $user->id;
            }

            $authorId = ArrayHelper::toInteger($authorId);
            $query->whereIn($db->quoteName('a.created_by'), $authorId);
        }

        // Фильтрация на основе поискового запроса
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            // Поиск по идентификатору товара
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query
                    ->where($db->quoteName('a.id') . ' = :search')
                    ->bind(':search', $search, ParameterType::INTEGER);
            } else {
                // Поиск по заголовку
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query
                    ->where('(' . $db->quoteName('a.title') . ' LIKE :search1 OR ' .
                            $db->quoteName('a.alias') . ' LIKE :search2)')
                    ->bind([':search1', ':search2'], $search);
            }
        }

        // Сортировка списка
        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        $ordering = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);
        $query->order($ordering);

        return $query;
    }

    /**
     * Метод для получения списка,
     * переопределяем для добавления проверки уровней доступа
     * @return mixed Массив элементов или false
     * @since 1.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        foreach ($items as $item) {
            $item->typeAlias = 'com_ishop.zones';
        }

        return $items;
    }
}
