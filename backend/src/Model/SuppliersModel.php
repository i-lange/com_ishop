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
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\Database\ParameterType;

/**
 * Модель списка поставщиков
 * @since 1.0.0
 */
class SuppliersModel extends ListModel
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
                'access', 'a.access', 'access_level',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'created_by_alias', 'a.created_by_alias',
                'modified', 'a.modified',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
                'ordering', 'a.ordering',
                'language', 'a.language',
            ];

            if (Associations::isEnabled()) {
                $config['filter_fields'][] = 'association';
            }
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
        $app   = Factory::getApplication();
        $input = $app->getInput();

        $forcedLanguage = $input->get('forcedLanguage', '', 'cmd');

        // Контекст для поддержки модальных макетов
        if ($layout = $input->get('layout')) {
            $this->context .= '.' . $layout;
        }

        // Контекст для поддержки форсирования языка
        if ($forcedLanguage) {
            $this->context .= '.' . $forcedLanguage;
        }

        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_published', '');
        $this->setState('filter.state', $published);

        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

        parent::populateState($ordering, $direction);
    }

    /**
     * Метод для получения идентификатора на основе конфигурации модели 
     * Это необходимо, поскольку модель используется компонентом и различными модулями, 
     * которым могут понадобиться разные наборы данных или разный порядок сортировки
     * @param string $id Префикс
     * @return string Идентификатор
     * @since 1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.language');

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
                        $db->quoteName('a.access'),
                        $db->quoteName('a.created'),
                        $db->quoteName('a.created_by'),
                        $db->quoteName('a.created_by_alias'),
                        $db->quoteName('a.modified'),
                        $db->quoteName('a.checked_out'),
                        $db->quoteName('a.checked_out_time'),
                        $db->quoteName('a.publish_up'),
                        $db->quoteName('a.publish_down'),
                        $db->quoteName('a.ordering'),
                        $db->quoteName('a.language'),
                        $db->quoteName('a.hits'),
                        $db->quoteName('a.site_url'),
                    ]
                )
            )
            ->select(
                [
                    $db->quoteName('languages.title', 'language_title'),
                    $db->quoteName('languages.image', 'language_image'),
                    $db->quoteName('users_checked.name', 'editor'),
                    $db->quoteName('levels.title', 'access_level'),
                    $db->quoteName('users_created.name', 'author_name'),
                ]
            )
            ->from($db->quoteName('#__ishop_suppliers', 'a'))
            ->join('LEFT',
                $db->quoteName('#__languages', 'languages'),
                $db->quoteName('languages.lang_code') . ' = ' . $db->quoteName('a.language'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_checked'),
                $db->quoteName('users_checked.id') . ' = ' . $db->quoteName('a.checked_out'))
            ->join('LEFT',
                $db->quoteName('#__viewlevels', 'levels'),
                $db->quoteName('levels.id') . ' = ' . $db->quoteName('a.access'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_created'),
                $db->quoteName('users_created.id') . ' = ' . $db->quoteName('a.created_by'));

        // Объединяем с таблицей ассоциаций
        if (Associations::isEnabled()) {
            $subQuery = $db->getQuery(true)
                ->select('COUNT(' . $db->quoteName('asso1.id') . ') > 1')
                ->from($db->quoteName('#__associations', 'asso1'))
                ->join('INNER',
                    $db->quoteName('#__associations', 'asso2'),
                    $db->quoteName('asso1.key') . ' = ' . $db->quoteName('asso2.key'))
                ->where(
                    [
                        $db->quoteName('asso1.id') . ' = ' . $db->quoteName('a.id'),
                        $db->quoteName('asso1.context') . ' = ' . $db->quote('com_ishop.supplier'),
                    ]
                );

            $query->select('(' . $subQuery . ') AS ' . $db->quoteName('association'));
        }

        // Фильтр по уровню доступа
        $access = $this->getState('filter.access');
        if (is_numeric($access)) {
            $access = (int) $access;
            $query
                ->where($db->quoteName('a.access') . ' = :access')
                ->bind(':access', $access, ParameterType::INTEGER);
        } elseif (is_array($access)) {
            $access = ArrayHelper::toInteger($access);
            $query->whereIn($db->quoteName('a.access'), $access);
        }

        // Фильтр по уровню доступа к категориям
        if (!$user->authorise('core.admin')) {
            $groups = $user->getAuthorisedViewLevels();
            $query->whereIn($db->quoteName('a.access'), $groups);
        }

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
            } elseif (stripos($search, 'content:') === 0) {
                // Поиск по контенту
                $search = '%' . substr($search, 8) . '%';
                $query
                    ->where('(' . $db->quoteName('a.introtext') . ' LIKE :search1 OR ' .
                            $db->quoteName('a.fulltext') . ' LIKE :search2)')
                    ->bind([':search1', ':search2'], $search);
            } else {
                // Поиск по заголовку
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query
                    ->where('(' . $db->quoteName('a.title') . ' LIKE :search1 OR ' .
                            $db->quoteName('a.alias') . ' LIKE :search2)')
                    ->bind([':search1', ':search2'], $search);
            }
        }

        // Фильтр по языку
        if ($language = $this->getState('filter.language')) {
            $query
                ->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language);
        }

        // Сортировка списка
        $orderCol = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        $ordering = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);
        $query->order($ordering);

        return $query;
    }

    /**
     * Метод для получения списка прогулок,
     * переопределяем для добавления проверки уровней доступа
     * @return mixed Массив элементов или false
     * @since 1.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        foreach ($items as $item) {
            $item->typeAlias = 'com_ishop.supplier';

            if (isset($item->metadata)) {
                $registry       = new Registry($item->metadata);
                $item->metadata = $registry->toArray();
            }
        }

        return $items;
    }
}
