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
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

/**
 * Модель списка SEO-страниц фильтра.
 *
 * Готовит данные для административного списка записей `#__ishop_filters`:
 * поддерживает фильтры по состоянию, категории, языку и поисковой строке,
 * собирает связи с категориями, языками и пользователями, а также формирует
 * стабильный ключ кеша списка.
 *
 * @since 1.0.0
 */
class FiltersModel extends ListModel
{
    /**
     * Инициализирует модель списка и набор полей сортировки/фильтрации.
     *
     * @param   array  $config  Конфигурация модели Joomla.
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'category_id', 'a.category_id',
                'category_title',
                'heading', 'a.heading',
                'state', 'a.state',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'created_by_alias', 'a.created_by_alias',
                'modified', 'a.modified',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'ordering', 'a.ordering',
                'language', 'a.language',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Заполняет состояние модели из запроса и user state.
     *
     * Сохраняет значения фильтров административного списка: поиск, состояние,
     * категория и язык.
     *
     * @param   string  $ordering   Поле сортировки по умолчанию.
     * @param   string  $direction  Направление сортировки по умолчанию.
     *
     * @return  void
     *
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = 'a.ordering', $direction = 'asc')
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        if ($layout = $input->get('layout')) {
            $this->context .= '.' . $layout;
        }

        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '');
        $this->setState('filter.state', $published);

        $categoryId = $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id', '');
        $this->setState('filter.category_id', $categoryId);

        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

        parent::populateState($ordering, $direction);
    }

    /**
     * Формирует ключ кеша списка с учетом активных фильтров.
     *
     * @param   string  $id  Начальная часть ключа кеша.
     *
     * @return  string
     *
     * @since 1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.category_id');
        $id .= ':' . $this->getState('filter.language');

        return parent::getStoreId($id);
    }

    /**
     * Строит SQL-запрос административного списка SEO-страниц фильтра.
     *
     * Запрос выбирает данные из `#__ishop_filters`, присоединяет связанные
     * категории, языки и пользователей, затем применяет фильтры и сортировку
     * из состояния модели.
     *
     * @return  \Joomla\Database\QueryInterface
     *
     * @since 1.0.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $user = $this->getCurrentUser();

        $query
            ->select(
                $this->getState('list.select', [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.category_id'),
                    $db->quoteName('a.manufacturers'),
                    $db->quoteName('a.ishop_fields'),
                    $db->quoteName('a.heading'),
                    $db->quoteName('a.metatitle'),
                    $db->quoteName('a.metadesc'),
                    $db->quoteName('a.metakey'),
                    $db->quoteName('a.state'),
                    $db->quoteName('a.created'),
                    $db->quoteName('a.created_by'),
                    $db->quoteName('a.created_by_alias'),
                    $db->quoteName('a.modified'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                    $db->quoteName('a.ordering'),
                    $db->quoteName('a.language'),
                ])
            )
            ->select([
                $db->quoteName('category.title', 'category_title'),
                $db->quoteName('languages.title', 'language_title'),
                $db->quoteName('languages.image', 'language_image'),
                $db->quoteName('users_checked.name', 'editor'),
                $db->quoteName('users_created.name', 'author_name'),
            ])
            ->from($db->quoteName('#__ishop_filters', 'a'))
            ->join('LEFT',
                $db->quoteName('#__categories', 'category'),
                $db->quoteName('category.id') . ' = ' . $db->quoteName('a.category_id'))
            ->join('LEFT',
                $db->quoteName('#__languages', 'languages'),
                $db->quoteName('languages.lang_code') . ' = ' . $db->quoteName('a.language'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_checked'),
                $db->quoteName('users_checked.id') . ' = ' . $db->quoteName('a.checked_out'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_created'),
                $db->quoteName('users_created.id') . ' = ' . $db->quoteName('a.created_by'));

        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $published = (int) $published;
            $query
                ->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $published, ParameterType::INTEGER);
        } elseif (empty($published)) {
            $query->whereIn($db->quoteName('a.state'), [0, 1]);
        } elseif (is_array($published)) {
            $query->whereIn($db->quoteName('a.state'), ArrayHelper::toInteger($published));
        }

        $categoryId = $this->getState('filter.category_id');
        if (is_numeric($categoryId) && (int) $categoryId > 0) {
            $categoryId = (int) $categoryId;
            $query
                ->where($db->quoteName('a.category_id') . ' = :category_id')
                ->bind(':category_id', $categoryId, ParameterType::INTEGER);
        }

        $authorId = $this->getState('filter.author_id');
        if (is_numeric($authorId)) {
            $authorId = (int) $authorId;
            $type = $this->getState('filter.author_id.include', true) ? ' = ' : ' <> ';
            $query
                ->where($db->quoteName('a.created_by') . $type . ':authorId')
                ->bind(':authorId', $authorId, ParameterType::INTEGER);
        } elseif (is_array($authorId)) {
            if (in_array('by_me', $authorId)) {
                $authorId['by_me'] = $user->id;
            }

            $query->whereIn($db->quoteName('a.created_by'), ArrayHelper::toInteger($authorId));
        }

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query
                    ->where($db->quoteName('a.id') . ' = :search')
                    ->bind(':search', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query
                    ->where('(' . $db->quoteName('a.metatitle') . ' LIKE :search1 OR ' .
                        $db->quoteName('a.metadesc') . ' LIKE :search2 OR ' .
                        $db->quoteName('a.heading') . ' LIKE :search3 OR ' .
                        $db->quoteName('category.title') . ' LIKE :search4)')
                    ->bind([':search1', ':search2', ':search3', ':search4'], $search);
            }
        }

        if (Multilanguage::isEnabled() && $language = $this->getState('filter.language')) {
            $query
                ->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language);
        }

        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Возвращает элементы списка и добавляет alias типа контента.
     *
     * Alias используется стандартными механизмами Joomla для действий списка.
     *
     * @return  array
     *
     * @since 1.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        foreach ($items as $item) {
            $item->typeAlias = 'com_ishop.filters';
        }

        return $items;
    }
}
