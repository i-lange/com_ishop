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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Joomla\Database\ParameterType;

/**
 * Модель списка товаров
 * @since 1.0.0
 */
class ProductsModel extends ListModel
{
    /**
     * Конструктор
     * @param array $config Массив параметров, необязательно
     * @param ?MVCFactoryInterface  $factory Фабрика
     * @throws \Exception
     * @since 1.0.0
     * @see JController
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'prefix_id', 'a.prefix_id', 'prefix_title',
                'manufacturer_id', 'a.manufacturer_id', 'manufacturer_title',
                'title', 'a.title',
                'alias', 'a.alias',
                'catid', 'a.catid', 'category_title',
                'state', 'a.state',
                'published', 'a.published',
                'access', 'a.access', 'access_level',
                'featured', 'a.featured',
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
                'hits', 'a.hits',
                'price', 'a.price',
                'stock', 'a.stock',
                'author_id',
                'category_id',
                'level',
                'tag',
            ];

            if (Associations::isEnabled()) {
                $config['filter_fields'][] = 'association';
            }
        }

        parent::__construct($config, $factory);
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

        $forcedLanguage = $input->get('forcedLanguage', '');

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

        $published = $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '');
        $this->setState('filter.published', $published);

        $stock = $this->getUserStateFromRequest($this->context . '.filter.stock', 'filter_stock', '');
        $this->setState('filter.stock', $stock);

        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

        $manufacturer = $this->getUserStateFromRequest($this->context . '.filter.manufacturer', 'filter_manufacturer');
        $this->setState('filter.manufacturer', $manufacturer);

        $prefix = $this->getUserStateFromRequest($this->context . '.filter.prefix', 'filter_prefix');
        $this->setState('filter.prefix', $prefix);

        $categoryId = $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id');
        $this->setState('filter.category_id', $categoryId);

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
        $id .= ':' . serialize($this->getState('filter.access'));
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.featured');
        $id .= ':' . serialize($this->getState('filter.category_id'));
        $id .= ':' . serialize($this->getState('filter.author_id'));
        $id .= ':' . $this->getState('filter.language');
        $id .= ':' . serialize($this->getState('filter.tag'));
        $id .= ':' . $this->getState('filter.stock');
        $id .= ':' . serialize($this->getState('filter.prefix'));
        $id .= ':' . serialize($this->getState('filter.manufacturer'));
        $id .= ':' . $this->getState('filter.checked_out');

        return parent::getStoreId($id);
    }

    /**
     * Составляем запрос к базе данных, для выборки списка товаров
     * @return \Joomla\Database\QueryInterface
     * @throws \Exception
     * @since 1.0.0
     */
    protected function getListQuery()
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        //$user   = Factory::getApplication()->getIdentity();
        $user  = $this->getCurrentUser();

        $query
            ->select(
                $this->getState('list.select',
                    [
                        $db->quoteName('a.id'),
                        $db->quoteName('a.manufacturer_id'),
                        $db->quoteName('a.prefix_id'),
                        $db->quoteName('a.title'),
                        $db->quoteName('a.alias'),
                        $db->quoteName('a.catid'),
                        $db->quoteName('a.state'),
                        $db->quoteName('a.access'),
                        $db->quoteName('a.featured'),
                        $db->quoteName('a.stock'),
                        $db->quoteName('a.price'),
                        $db->quoteName('a.created'),
                        $db->quoteName('a.created_by'),
                        $db->quoteName('a.created_by_alias'),
                        $db->quoteName('a.modified'),
                        $db->quoteName('a.checked_out'),
                        $db->quoteName('a.checked_out_time'),
                        $db->quoteName('a.publish_up'),
                        $db->quoteName('a.publish_down'),
                        $db->quoteName('a.images'),
                        $db->quoteName('a.rating'),
                        $db->quoteName('a.reviews_count'),
                        $db->quoteName('a.ordering'),
                        $db->quoteName('a.language'),
                        $db->quoteName('a.hits'),
                    ]
                )
            )
            ->select(
                [
                    $db->quoteName('languages.title', 'language_title'),
                    $db->quoteName('languages.image', 'language_image'),
                    $db->quoteName('users_checked.name', 'editor'),
                    $db->quoteName('levels.title', 'access_level'),
                    $db->quoteName('cats.title', 'category_title'),
                    $db->quoteName('cats.created_user_id', 'category_uid'),
                    $db->quoteName('cats.level', 'category_level'),
                    $db->quoteName('cats.published', 'category_published'),
                    $db->quoteName('users_created.name', 'author_name'),
                    $db->quoteName('manufacturers.title', 'manufacturer_title'),
                    $db->quoteName('prefixes.title', 'prefix_title'),
                ]
            )
            ->from($db->quoteName('#__ishop_products', 'a'))
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
                $db->quoteName('#__categories', 'cats'),
                $db->quoteName('cats.id') . ' = ' . $db->quoteName('a.catid'))
            ->join('LEFT',
                $db->quoteName('#__users', 'users_created'),
                $db->quoteName('users_created.id') . ' = ' . $db->quoteName('a.created_by'))
            ->join('LEFT',
                $db->quoteName('#__ishop_manufacturers', 'manufacturers'),
                $db->quoteName('manufacturers.id') . ' = ' . $db->quoteName('a.manufacturer_id'))
            ->join('LEFT',
                $db->quoteName('#__ishop_prefixes', 'prefixes'),
                $db->quoteName('prefixes.id') . ' = ' . $db->quoteName('a.prefix_id'));

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
                        $db->quoteName('asso1.context') . ' = ' . $db->quote('com_ishop.product'),
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

        // Фильтр по признаку избранных товаров
        $featured = (string) $this->getState('filter.featured');
        if (in_array($featured, ['0','1'])) {
            $featured = (int) $featured;
            $query->where($db->quoteName('a.featured') . ' = :featured')
                ->bind(':featured', $featured, ParameterType::INTEGER);
        }

        // Фильтр по уровню доступа к категориям
        if (!$user->authorise('core.admin')) {
            $groups = $user->getAuthorisedViewLevels();
            $query->whereIn($db->quoteName('a.access'), $groups);
            $query->whereIn($db->quoteName('cats.access'), $groups);
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

        // Фильтрация по категориям и их уровням вложенности
        $categoryId = $this->getState('filter.category_id', []);
        $level      = (int) $this->getState('filter.level');

        if (!is_array($categoryId)) {
            $categoryId = $categoryId ? [$categoryId] : [];
        }

        // Вариант: Фильтрация по категориям и их уровням вложенности
        if (count($categoryId)) {
            $categoryId       = ArrayHelper::toInteger($categoryId);
            $categoryTable = Factory::getApplication()
                ->bootComponent('com_categories')
                ->getMVCFactory()->createTable('Category', 'Administrator', ['dbo' => $db]);
            $subCatItemsWhere = [];

            foreach ($categoryId as $filter_catid) {
                $categoryTable->load($filter_catid);

                // Поскольку значения в $query->bind() передаются по ссылке, используем здесь $query->bindArray(), чтобы предотвратить перезапись
                $valuesToBind = [$categoryTable->lft, $categoryTable->rgt];
                if ($level) {
                    $valuesToBind[] = $level + $categoryTable->level - 1;
                }
                // Связывание значений и получение имен параметров
                $bounded = $query->bindArray($valuesToBind);

                $categoryWhere = $db->quoteName('cats.lft') . ' >= ' . $bounded[0] . ' AND ' . $db->quoteName('cats.rgt') . ' <= ' . $bounded[1];
                if ($level) {
                    $categoryWhere .= ' AND ' . $db->quoteName('cats.level') . ' <= ' . $bounded[2];
                }
                $subCatItemsWhere[] = '(' . $categoryWhere . ')';
            }

            $query->where('(' . implode(' OR ', $subCatItemsWhere) . ')');
        } elseif ($level) {
            // Вариант: Фильтрация только по уровню вложенности
            $query->where($db->quoteName('cats.level') . ' <= :level')
                ->bind(':level', $level, ParameterType::INTEGER);
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
            // Проверяем, находится ли by_me в массиве
            if (in_array('by_me', $authorId)) {
                // Заменяем by_me на идентификатор текущего пользователя
                $authorId['by_me'] = $user->id;
            }

            $authorId = ArrayHelper::toInteger($authorId);
            $query->whereIn($db->quoteName('a.created_by'), $authorId);
        }

        // Фильтр по наличию товаров
        $stock = (string) $this->getState('filter.stock');
        if (in_array($stock, ['0','1'])) {
            $stock = (int) $stock;
            if ($stock === 1) {
                $stock1 = 0;
                $stock2 = -1;
                $query
                    ->where('(' .$db->quoteName('a.stock') . ' > :stock1 OR ' .
                        $db->quoteName('a.stock') . ' = :stock2)')
                    ->bind(':stock1', $stock1, ParameterType::INTEGER)
                    ->bind(':stock2', $stock2, ParameterType::INTEGER);
            } else {
                $query
                    ->where($db->quoteName('a.stock') . ' = :stock')
                    ->bind(':stock', $stock, ParameterType::INTEGER);
            }
        }

        // Фильтр по производителям
        $manufacturers = $this->getState('filter.manufacturer', []);
        if (!is_array($manufacturers)) {
            $manufacturers = $manufacturers ? [$manufacturers] : [];
        }
        if (count($manufacturers)) {
            $manufacturers = ArrayHelper::toInteger($manufacturers);
            $query->whereIn($db->quoteName('a.manufacturer_id'), $manufacturers);
        }

        // Фильтр по префиксу наименования товара
        $prefixes = $this->getState('filter.prefix', []);
        if (!is_array($prefixes)) {
            $prefixes = $prefixes ? [$prefixes] : [];
        }
        if (count($prefixes)) {
            $prefixes = ArrayHelper::toInteger($prefixes);
            $query->whereIn($db->quoteName('a.prefix_id'), $prefixes);
        }

        // Фильтрация на основе поискового запроса
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            // Поиск по идентификатору товара
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query
                    ->where($db->quoteName('a.id') . ' = :search1 OR ' . $db->quoteName('a.shopmanager_id') . ' = :search2')
                    ->bind([':search1', ':search2'], $search, ParameterType::INTEGER);
            } elseif (stripos($search, 'content:') === 0) {
                // Поиск по контенту
                $search = '%' . substr($search, 8) . '%';
                $query
                    ->where('(' . $db->quoteName('a.introtext') . ' LIKE :search1 OR ' .
                            $db->quoteName('a.fulltext') . ' LIKE :search2)')
                    ->bind([':search1', ':search2'], $search);
            } else {
                // Поиск по модели
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
            $item->typeAlias = 'com_ishop.product';

            if (isset($item->metadata)) {
                $registry       = new Registry($item->metadata);
                $item->metadata = $registry->toArray();
            }
        }

        return $items;
    }
}
