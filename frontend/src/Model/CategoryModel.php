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

use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Content\Site\Helper\QueryHelper;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;
use stdClass;

/**
 * Модель поддерживает получение категории, товаров, связанных с этой категорией,
 * дочерних и родительских категорий
 * @since 1.0.0
 */
class CategoryModel extends ListModel
{
    /**
     * Данные категории
     * @var array
     * @since 1.0.0
     */
    protected $_item = null;

    /**
     * Список товаров категории
     * @var array
     * @since 1.0.0
     */
    protected $_products = null;

    /**
     * Список производителей категории
     * @var array
     * @since 1.0.0
     */
    protected $_manufacturers = null;

    /**
     * Список идентификаторов товаров категории
     * @var array
     * @since 1.0.0
     */
    protected $_all_products_id = null;

    /**
     * Объект фильтрации товаров
     * @var object
     * @since 1.0.0
     */
    protected $_filter_object = null;

    /**
     * Категория слева и справа от этой
     * @var CategoryNode[]|null
     * @since 1.0.0
     */
    protected $_siblings = null;

    /**
     * Массив дочерних категорий
     * @var CategoryNode[]|null
     * @since 1.0.0
     */
    protected $_children = null;

    /**
     * Родительская категория для текущей
     * @var CategoryNode|null
     * @since 1.0.0
     */
    protected $_parent = null;

    /**
     * Контекст модели
     * @var string
     * @since 1.0.0
     */
    protected $_context = 'com_ishop.category';

    /**
     * Категория
     * @var object
     * @since 1.0.0
     */
    protected $_category = null;

    /**
     * Список категорий
     * @var array
     * @since 1.0.0
     */
    protected $_categories = null;

    /**
     * Конструктор
     *
     * @param   array  $config  Ассоциативный массив параметров конфигурации, необязательно
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'alias', 'a.alias',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'catid', 'a.catid', 'category_title',
                'state', 'a.state',
                'access', 'a.access', 'access_level',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'modified', 'a.modified',
                'ordering', 'a.ordering',
                'featured', 'a.featured',
                'language', 'a.language',
                'hits', 'a.hits',
                'price', 'a.price',
                'min_price', 'a.min_price',
                'max_price', 'a.max_price',
                'good_price', 'a.good_price',
                'ishop_fields', 'a.ishop_fields',
                'manufacturers', 'a.manufacturers', 'a.manufacturer_id',
                'warehouses', 'a.warehouses', 'a.warehouse_id',
                'rating', 'a.rating',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
                'author', 'a.author',
                'filter_tag',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Метод для автоматического заполнения модели
     * Этот метод должен вызываться только один раз
     * и предназначен для первого вызове метода getState(),
     * если не установлен флаг для игнорирования запроса
     * Вызов getState в этом методе приведет к рекурсии
     *
     * @param   string  $ordering   Поле для сортировки
     * @param   string  $direction  Направление сортировки
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = 'id', $direction = 'DESC')
    {
        $app = Factory::getApplication();

        // Состояние публикации
        $this->setState('filter.published', 1);

        // Устанавливаем параметры из запроса
        $pk  = $app->getInput()->getInt('id');
        $this->setState('category.id', $pk);
        $this->setState('filter.category_id', $pk);

        // Загружаем параметры компонента.
        // Объединяем глобальные параметры и параметры пункта меню
        $params = $app->getParams();
        $this->setState('params', $params);

        // Обрабатываем параметры доступа
        if (!$params->get('show_noauth')) {
            $this->setState('filter.access', true);
        } else {
            $this->setState('filter.access', false);
        }

        // Идентификатор для состояния пользователя из запроса
        $itemid = $app->getInput()->get('id', 0, 'int') . ':' . $app->getInput()->get('Itemid', 0, 'int');

        // Фильтрация по тегу
        $value = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.tag', 'filter_tag', 0, 'int');
        $this->setState('filter.tag', $value);

        // Фильтрация по тексту (поиск)
        $value = $app->getUserStateFromRequest('com_ishop.category.list.' . $itemid . '.filter-search', 'filter-search', '', 'string');
        $this->setState('list.filter', $value);

        // Фильтрация, поле сортировки
        $orderCol = $app->getUserStateFromRequest('com_ishop.category.list.' . $itemid . '.filter_order', 'filter_order', 'a.price', 'string');
        if (!in_array($orderCol, $this->filter_fields)) {
            $orderCol = 'a.price';
        }
        $this->setState('list.ordering', $orderCol);

        // Фильтрация, порядок сортировки
        $listOrder = $app->getUserStateFromRequest('com_ishop.category.list.' . $itemid . '.filter_order_Dir', 'filter_order_Dir', 'ASC', 'cmd');
        if (!in_array(strtoupper($listOrder), ['ASC', 'DESC'])) {
            $listOrder = 'ASC';
        }
        $this->setState('list.direction', $listOrder);

        // С какой страницы выводим список
        $value =  $app->getInput()->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        // Максимальное число записей на страницу
        $value = $app->getInput()->get('limit', $app->get('list_limit', 0), 'uint');
        $this->setState('list.limit', $value);

        // Показывать ли подкатегории и сколько
        $this->setState('filter.max_category_levels', $params->get('show_subcategory_content', '1'));
        $this->setState('filter.subcategories', true);

        // Фильтрация по языку
        $this->setState('filter.language', Multilanguage::isEnabled());

        // Шаблон вывода
        $this->setState('layout', $app->getInput()->getString('layout'));

        // Показывать ли избранные записи
        $this->setState('filter.featured', $params->get('show_featured'));

        // Фильтрация по минимальной цене
        $min_price = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.min_price', 'min_price', 0, 'float');
        if ($min_price == '') {
            $min_price = 0;
        }
        $this->setState('filter.min_price', $min_price);

        // Фильтрация по максимальной цене
        $max_price = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.max_price', 'max_price', 0, 'float');
        if ($max_price == '') {
            $max_price = 0;
        }
        $this->setState('filter.max_price', $max_price);

        // Фильтрация по наличию скидок
        $good_price = (int) $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.good_price', 'good_price', 0, 'uint');
        // Фильтр по скидкам работает, если скидки включены в настройках компонента
        if ($params->get('discounts_use', 0)) {
            $this->setState('filter.good_price', $good_price);
        } else {
            $this->setState('filter.good_price', 0);
        }

        // Фильтрация по минимальной ширине
        $min_width = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.min_width', 'min_width', 0, 'float');
        if ($min_width == '') {
            $min_width = 0;
        }
        $this->setState('filter.min_width', $min_width);

        // Фильтрация по максимальной ширине
        $max_width = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.max_width', 'max_width', 0, 'float');
        if ($max_width == '') {
            $max_width = 0;
        }
        $this->setState('filter.max_width', $max_width);

        // Фильтрация по минимальной высоте
        $min_height = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.min_height', 'min_height', 0, 'float');
        if ($min_height == '') {
            $min_height = 0;
        }
        $this->setState('filter.min_height', $min_height);

        // Фильтрация по максимальной высоте
        $max_height = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.max_height', 'max_height', 0, 'float');
        if ($max_height == '') {
            $max_height = 0;
        }
        $this->setState('filter.max_height', $max_height);

        // Фильтрация по минимальной глубине
        $min_depth = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.min_depth', 'min_depth', 0, 'float');
        if ($min_depth == '') {
            $min_depth = 0;
        }
        $this->setState('filter.min_depth', $min_depth);

        // Фильтрация по максимальной глубине
        $max_depth = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.max_depth', 'max_depth', 0, 'float');
        if ($max_depth == '') {
            $max_depth = 0;
        }
        $this->setState('filter.max_depth', $max_depth);

        // Фильтрация по весу
        $min_weight = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.min_weight', 'min_weight', 0, 'float');
        if ($min_weight == '') {
            $min_weight = 0;
        }
        $this->setState('filter.min_weight', $min_weight);

        // Фильтрация по весу
        $max_weight = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.max_weight', 'max_weight', 0, 'float');
        if ($max_weight == '') {
            $max_weight = 0;
        }
        $this->setState('filter.max_weight', $max_weight);

        // Фильтрация по характеристикам
        $ishop_fields = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.ishop_fields', 'ishop_fields', [], 'array');
        $this->setState('filter.ishop_fields', $ishop_fields);

        // Фильтрация по списку производителей
        $manufacturers = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.manufacturers', 'manufacturers', [], 'array');
        if (isset($manufacturers[0]) && $manufacturers[0] == 0) {
            array_shift($manufacturers);
        }
        $this->setState('filter.manufacturers', $manufacturers);

        // Фильтрация по наличию товаров на складах
        $warehouses = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.warehouses', 'warehouses', [], 'array');
        if (isset($warehouses[0]) && $warehouses[0] == 0) {
            array_shift($warehouses);
        }
        $this->setState('filter.warehouses', $warehouses);

        // Фильтрация по id производителя
        $manufacturer_id = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.manufacturer_id', 'manufacturer_id', 0, 'uint');
        $this->setState('filter.manufacturer_id', $manufacturer_id);

        // Фильтрация по id склада
        $warehouse_id = $this->getUserStateFromRequest('com_ishop.category.filter.' . $itemid . '.warehouse_id', 'warehouse_id', false, 'uint');
        $this->setState('filter.warehouse_id', $warehouse_id);

        //parent::populateState($ordering, $direction);
    }

    /**
     * Метод получает список записей в категории
     *
     * @return mixed Массив записей или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItems()
    {
        if ($this->_products === null && $category = $this->getCategory()) {
            $limit = $this->getState('list.limit');

            $model = $this
                ->bootComponent('com_ishop')
                ->getMVCFactory()
                ->createModel('Products', 'Site', ['ignore_request' => true]);

            $model->setState('params', Factory::getApplication()->getParams());
            $model->setState('filter.category_id', $category->id);
            $model->setState('filter.published', $this->getState('filter.published'));
            $model->setState('filter.access', $this->getState('filter.access'));
            $model->setState('filter.language', $this->getState('filter.language'));
            $model->setState('filter.featured', $this->getState('filter.featured'));
            $model->setState('filter.tag', $this->getState('filter.tag'));
            $model->setState('filter.subcategories', $this->getState('filter.subcategories'));
            $model->setState('filter.max_category_levels', $this->getState('filter.max_category_levels'));

            $model->setState('list.ordering', $this->getState('list.ordering', 'a.price'));
            $model->setState('list.start', $this->getState('list.start'));
            $model->setState('list.limit', $limit);
            $model->setState('list.direction', $this->getState('list.direction', 'ASC'));
            $model->setState('list.filter', $this->getState('list.filter'));

            $model->setState('filter.min_price', $this->getState('filter.min_price'));
            $model->setState('filter.max_price', $this->getState('filter.max_price'));
            $model->setState('filter.good_price', $this->getState('filter.good_price'));
            $model->setState('filter.min_width', $this->getState('filter.min_width'));
            $model->setState('filter.max_width', $this->getState('filter.max_width'));
            $model->setState('filter.min_height', $this->getState('filter.min_height'));
            $model->setState('filter.max_height', $this->getState('filter.max_height'));
            $model->setState('filter.min_depth', $this->getState('filter.min_depth'));
            $model->setState('filter.max_depth', $this->getState('filter.max_depth'));
            $model->setState('filter.min_weight', $this->getState('filter.min_weight'));
            $model->setState('filter.max_weight', $this->getState('filter.max_weight'));
            $model->setState('filter.ishop_fields', $this->getState('filter.ishop_fields'));
            $model->setState('filter.manufacturers', $this->getState('filter.manufacturers'));
            $model->setState('filter.warehouses', $this->getState('filter.warehouses'));
            $model->setState('filter.manufacturer_id', $this->getState('filter.manufacturer_id'));
            $model->setState('filter.warehouse_id', $this->getState('filter.warehouse_id'));

            if ($limit >= 0) {
                $this->_products = $model->getItems();

                if ($this->_products === false) {
                    // Если не удалось получить список товаров из базы данных
                    throw new RuntimeException(implode("\n", $model->getError()), 500);
                }
            } else {
                $this->_products = [];
            }

            $this->_pagination = $model->getPagination();
        }

        return $this->_products;
    }

    /**
     * Собираем параметры сортировки для списка товаров
     *
     * @return  string  order by для запроса
     *
     * @throws \Exception
     * @since   1.0.0
     */
    protected function _buildOrderBy()
    {
        $app       = Factory::getApplication();
        $db        = $this->getDatabase();
        $params    = $this->getState('params');
        $itemid    = $app->getInput()->get('id', 0, 'int') . ':' . $app->getInput()->get('Itemid', 0, 'int');
        $orderCol  = $app->getUserStateFromRequest('com_ishop.category.list.' . $itemid . '.filter_order', 'filter_order', '', 'string');
        $orderDirn = $app->getUserStateFromRequest('com_ishop.category.list.' . $itemid . '.filter_order_Dir', 'filter_order_Dir', '', 'cmd');
        $orderBy   = ' ';

        if (!in_array($orderCol, $this->filter_fields)) {
            $orderCol = null;
        }

        if (!in_array(strtoupper($orderDirn), ['ASC', 'DESC'])) {
            $orderDirn = 'DESC';
        }

        if ($orderCol && $orderDirn) {
            $orderBy .= $db->escape($orderCol) . ' ' . $db->escape($orderDirn) . ', ';
        }

        $articleOrderBy   = $params->get('orderby_sec', 'rdate');
        $articleOrderDate = $params->get('order_date');
        $categoryOrderBy  = $params->def('orderby_pri', '');
        $secondary        = QueryHelper::orderbySecondary($articleOrderBy, $articleOrderDate, $this->getDatabase()) . ', ';
        $primary          = QueryHelper::orderbyPrimary($categoryOrderBy);

        $orderBy .= $primary . ' ' . $secondary . ' a.created ';

        return $orderBy;
    }

    /**
     * Метод возвращает объект пагинации для набора данных категории
     *
     * @return  \Joomla\CMS\Pagination\Pagination  объект пагинации
     *
     * @throws \Exception
     * @since   1.0.0
     */
    public function getPagination()
    {
        if (empty($this->_pagination)) {
            return null;
        }

        return $this->_pagination;
    }

    /**
     * Метод получения данных текущей категории
     *
     * @return object
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCategory()
    {
        if (!is_object($this->_item)) {
            $categories = Factory::getApplication()->bootComponent('com_ishop')->getCategory();
            $this->_item = $categories->get($this->getState('category.id', 'root'));

            if (is_object($this->_item)) {
                $this->_children = $this->_item->getChildren();                
                $this->_parent = false;

                if ($this->_item->getParent()) {
                    $this->_parent = $this->_item->getParent();
                }

                $this->_rightsibling = $this->_item->getSibling();
                $this->_leftsibling = $this->_item->getSibling(false);
            } else {
                $this->_children = false;
                $this->_parent = false;
            }
        }

        return $this->_item;
    }

    /**
     * Получаем родительскую категорию
     *
     * @return CategoryNode|null Объект категории или false, если произошла ошибка
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function getParent()
    {
        if (!is_object($this->_item)) {
            $this->getCategory();
        }

        return $this->_parent;
    }

    /**
     * Получаем дочерние категории
     *
     * @return array|CategoryNode[]|null Массив категорий или false, если произошла ошибка
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function &getChildren()
    {
        if (!is_object($this->_item)) {
            $this->getCategory();
        }

        // Order subcategories
        if ($this->_children) {
            $params = $this->getState()->get('params');

            $orderByPri = $params->get('orderby_pri');

            if ($orderByPri === 'alpha' || $orderByPri === 'ralpha') {
                $this->_children = ArrayHelper::sortObjects($this->_children, 'title', ($orderByPri === 'alpha') ? 1 : (-1));
            }
        }

        return $this->_children;
    }

    /**
     * Получаем смежные категории слева
     *
     * @return  mixed  Массив категорий или false, если произошла ошибка
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function &getLeftSibling()
    {
        if (!is_object($this->_item)) {
            $this->getCategory();
        }

        return $this->_leftsibling;
    }

    /**
     * Получаем смежные категории справа
     *
     * @return  mixed  Массив категорий или false, если произошла ошибка
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function &getRightSibling()
    {
        if (!is_object($this->_item)) {
            $this->getCategory();
        }

        return $this->_rightsibling;
    }

    /**
     * Увеличивает счетчик просмотров категории
     *
     * @param int $pk Идентификатор категории, необязательно
     *
     * @return bool True если успешно
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function hit(int $pk = 0)
    {
        $input = Factory::getApplication()->input;
        $hitcount = $input->getInt('hitcount', 1);

        if ($hitcount) {
            $pk = (int) ($pk ?: $this->getState('category.id'));

            $table = Factory::getApplication()
                ->bootComponent('com_categories')
                ->getMVCFactory()->createTable('Category', 'Administrator');
            $table->hit($pk);
        }

        return true;
    }

    /**
     * Возвращает объект фильтра для текущей категории
     *
     * @return object фильтр
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function getFilterObject()
    {
        if ($this->_filter_object === null) {
            $result = new stdClass();
            $result->empty = true;

            // Список характеристик для фильтра
            $result->ishop_fields = $this->getFilterFields();
            if (!empty($result->ishop_fields)) {
                $result->empty = false;
            }

            // Min-Max цены, вес и габариты для фильтра
            $result->main = $this->getFilterMain();
            if (!empty($result->main)) {
                $result->empty = false;
            }

            // Список производителя для фильтра
            $result->manufacturers = $this->getFilterManufacturers();
            if (!empty($result->manufacturers)) {
                $result->empty = false;
            }

            // Список активных складов для фильтра
            $warehouses = $this->getMVCFactory()->createModel('Warehouses', 'Site', ['ignore_request' => true]);
            //$warehouses->setState('filter.point', false);
            $result->warehouses = $warehouses->getItems();

            // Получаем активные (выбранные пользователем) значения фильтра
            // Изначально количество выбранных опций = 0
            $result->active_count = 0;

            // Если нужно фильтровать по характеристикам
            $result->active['fields'] = $this->getState('filter.ishop_fields', []);
            foreach ($result->active['fields'] as $id => $field) {
                $type = $result->ishop_fields[$id]->type;

                if ($type === 0) { // Числовые значение
                    if (!empty($field['min']) || !empty($field['max'])) {
                        $result->active_count++;
                    }
                } elseif ($type === 2) { // Да или Нет
                    if (!empty($field)) {
                        $result->active_count++;
                    }
                } elseif (!empty($field)) { // Строковые из списка
                    $result->active_count += (count($field) - 1);
                }
            }

            // Если нужно фильтровать по цене
            $result->active['min_price'] = $this->getState('filter.min_price', 0);
            $result->active['max_price'] = $this->getState('filter.max_price', 0);
            if ($result->active['min_price'] > 0 || $result->active['max_price'] > 0) {
                $result->active_count++;
            }

            // Если нужно фильтровать по наличию скидок
            $result->active['good_price'] = $this->getState('filter.good_price', 0);
            if ($result->active['good_price']) {
                $result->active_count++;
            }

            // Если нужно фильтровать по ширине
            $result->active['min_width'] = $this->getState('filter.min_width', 0);
            $result->active['max_width'] = $this->getState('filter.max_width', 0);
            if ($result->active['min_width'] > 0 || $result->active['max_width'] > 0) {
                $result->active_count++;
            }

            // Если нужно фильтровать по высоте
            $result->active['min_height'] = $this->getState('filter.min_height', 0);
            $result->active['max_height'] = $this->getState('filter.max_height', 0);
            if ($result->active['min_height'] > 0 || $result->active['max_height'] > 0) {
                $result->active_count++;
            }

            // Если нужно фильтровать по глубине
            $result->active['min_depth'] = $this->getState('filter.min_depth', 0);
            $result->active['max_depth'] = $this->getState('filter.max_depth', 0);
            if ($result->active['min_depth'] > 0 || $result->active['max_depth'] > 0) {
                $result->active_count++;
            }

            // Если нужно фильтровать по весу
            $result->active['min_weight'] = $this->getState('filter.min_weight', 0);
            $result->active['max_weight'] = $this->getState('filter.max_weight', 0);
            if ($result->active['min_weight'] > 0 || $result->active['max_weight'] > 0) {
                $result->active_count++;
            }

            // Если нужно фильтровать по бренду
            $result->active['manufacturers'] = $this->getState('filter.manufacturers', []);
            $result->active_count += count($result->active['manufacturers']);

            // Если нужно фильтровать по наличию на складах
            $result->active['warehouses'] = $this->getState('filter.warehouses', []);
            $result->active_count += count($result->active['warehouses']);

            $this->_filter_object = $result;
        }

        return $this->_filter_object;
    }

    /**
     * Метод получает список идентификаторов всех записей в категории
     *
     * @return mixed Массив записей или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItemsId()
    {
        if ($this->_all_products_id === null && $category = $this->getCategory()) {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);
            $query
                ->select($db->quoteName('a.product_id'))
                ->from($db->quoteName('#__ishop_filter_cat_' . $category->id, 'a'));
            $db->setQuery($query);
            $this->_all_products_id = $db->loadColumn();

            if ($this->_all_products_id === false) {
                // Если не удалось получить список идентификаторов товаров из базы данных
                throw new RuntimeException('Не удалось загрузить список товаров', 500);
            }

            // Если список пуст, попробуем получить список из дочерних категорий
            if (empty($this->_all_products_id )) {
                $query
                    ->clear()
                    ->select($db->quoteName('a.id'))
                    ->from($db->quoteName('#__ishop_products', 'a'))
                    ->where($db->quoteName('a.state') . ' = ' . 1);

                $levels = (int) $this->getState('filter.max_category_levels', 1);
                $type = $this->getState('filter.category_id.include', true) ? ' = ' : ' <> ';
                // Создаем подзапрос для списка подкатегорий
                $subQuery = $db->getQuery(true)
                    ->select($db->quoteName('sub.id'))
                    ->from($db->quoteName('#__categories', 'sub'))
                    ->join(
                        'INNER',
                        $db->quoteName('#__categories', 'this'),
                        $db->quoteName('sub.lft') . ' > ' . $db->quoteName('this.lft')
                        . ' AND ' . $db->quoteName('sub.rgt') . ' < ' . $db->quoteName('this.rgt')
                    )
                    ->where($db->quoteName('this.id') . ' = :subCategoryId');
                $query->bind(':subCategoryId', $category->id, ParameterType::INTEGER);

                if ($levels >= 0) {
                    $subQuery->where($db->quoteName('sub.level') . ' <= ' . $db->quoteName('this.level') . ' + :levels');
                    $query->bind(':levels', $levels, ParameterType::INTEGER);
                }

                // Добавляем подзапрос к основному запросу
                $query->where(
                    '(' . $db->quoteName('a.catid') . $type . ':categoryId OR ' . $db->quoteName('a.catid') . ' IN (' . $subQuery . '))'
                );
                $query->bind(':categoryId', $category->id, ParameterType::INTEGER);
                $db->setQuery($query);
                $this->_all_products_id = $db->loadColumn();

                if ($this->_all_products_id === false) {
                    // Если не удалось получить список идентификаторов товаров из базы данных
                    throw new RuntimeException('Не удалось загрузить список товаров', 500);
                }
            }
        }

        return $this->_all_products_id;
    }

    /**
     * Формируем массив необходимых характеристик
     * для фильтрации
     *
     * @param   int    $category_id      Идентификатор категории
     * @param   int    $manufacturer_id  Массив необходимых характеристик
     *
     * @return  array массив для фильтрации
     * @throws \Exception
     * @since 1.0.0
     */
    public function getFilterFields(int $category_id = 0, int $manufacturer_id = 0)
    {
        $category_id = (int) ($category_id ?: $this->getState('category.id'));
        $manufacturer_id = (int) ($manufacturer_id ?: $this->getState('manufacturer_id'));

        $params = new Registry($this->getCategory()->params);
        $fields = $params->get('filter_fields', []);

        $products_id = $this->getItemsId();

        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);

        $query
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.state'),
                $db->quoteName('a.type'),
                $db->quoteName('a.unit'),
                ' CASE WHEN ' . $db->qn('a.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->qn('values.value') .
                ' ORDER BY ' . $db->qn('values.ordering') . ' SEPARATOR ' . $db->q('||') . ')' .
                ' WHEN ' . $db->qn('a.type') .
                ' = 0 THEN CONCAT(MIN(' . $db->qn('fields_map.value') . '), '  . $db->q(', ') . ', MAX(' . $db->qn('fields_map.value') . '))' .
                ' ELSE ' . $db->q('') .
                ' END AS ' . $db->qn('values'),
                ' CASE WHEN ' . $db->qn('a.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->qn('values.id') .
                ' ORDER BY ' . $db->qn('values.ordering') . ' SEPARATOR ' . $db->q('||') . ')' .
                ' ELSE ' . $db->q('') .
                ' END AS ' . $db->qn('values_id'),
            ])
            ->from($db->quoteName('#__ishop_fields', 'a'))
            ->join('LEFT',
                $db->quoteName('#__languages', 'languages'),
                $db->quoteName('languages.lang_code') . ' = ' . $db->quoteName('a.language'))
            ->join('LEFT',
                $db->quoteName('#__viewlevels', 'levels'),
                $db->quoteName('levels.id') . ' = ' . $db->quoteName('a.access'))
            ->join('INNER',
                $db->quoteName('#__ishop_fields_map', 'fields_map'),
                $db->quoteName('fields_map.field_id') . ' = ' . $db->quoteName('a.id'))
            ->join('INNER',
                $db->quoteName('#__ishop_products', 'products'),
                $db->quoteName('products.id') . ' = ' . $db->quoteName('fields_map.product_id'))
            ->join('LEFT',
                $db->quoteName('#__ishop_values', 'values'),
                '(' .$db->quoteName('a.type') . ' = 1 AND ' .
                $db->quoteName('values.id') . ' = ' . $db->quoteName('fields_map.value') . ')');

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

        // Фильтрация по состоянию публикации характеристики
        $published = 1;
        $query
            ->where($db->quoteName('a.state') . ' = :state')
            ->bind(':state', $published, ParameterType::INTEGER);

        // Фильтр по языку
        if ($language = $this->getState('filter.language')) {
            $query
                ->where($db->quoteName('a.language') . ' = :language')
                ->bind(':language', $language);
        }

        // Фильтр по категории
        if ($category_id > 0) {
            $query
                ->where($db->quoteName('products.catid') . ' = :catid')
                ->bind(':catid', $category_id, ParameterType::INTEGER);
        }

        // Фильтр по производителю
        if ($manufacturer_id > 0) {
            $query
                ->where($db->quoteName('products.manufacturer_id') . ' = :manuf_id')
                ->bind(':manuf_id', $manufacturer_id, ParameterType::INTEGER);
        }

        // Фильтр по списку идентификаторов товаров
        if ($products_id && is_array($products_id)) {
            $query->whereIn($db->quoteName('products.id'), $products_id);
        }

        // Отбираем только искомые характеристики
        $query->whereIn($db->quoteName('a.id'), $fields);

        // Сортировка списка
        $query->order('a.ordering ASC');

        // Группировка
        $query->group([
            $db->quoteName('a.id'),
            $db->quoteName('a.title'),
            $db->quoteName('a.type'),
            $db->quoteName('a.unit'),
        ]);

        $db->setQuery($query);

        try {
            $ishop_fields = (array) $db->loadObjectList();
            $ishop_fields = array_combine(array_column($ishop_fields, 'id'), $ishop_fields);
        } catch (RuntimeException $e) {
            Log::add('Не удалось получить список характеристик для фильтра: ' . $query->__toString(), Log::ERROR, 'ishop');
            $ishop_fields = [];
        }

        return $ishop_fields;
    }

    /**
     * Получаем минимальную и максимальную цены по категории
     *
     * @return  array массив с ценами
     * @throws \Exception
     * @since 1.0.0
     */
    public function getFilterMain()
    {
        if ($this->_all_products_main === null) {
            $products_id = $this->getItemsId();

            if (empty($products_id)) {
                $this->_all_products_main = [];
                return $this->_all_products_main;
            }

            $db = $this->getDatabase();
            $query = $db->getQuery(true);
            $none = 0;
            $query
                ->select('MIN(' . $db->qn('a.price')  . ') AS ' . $db->qn('min_price'))
                ->select('MAX(' . $db->qn('a.price')  . ') AS ' . $db->qn('max_price'))
                ->select('MIN(' . $db->qn('a.width')  . ') AS ' . $db->qn('min_width'))
                ->select('MAX(' . $db->qn('a.width')  . ') AS ' . $db->qn('max_width'))
                ->select('MIN(' . $db->qn('a.height') . ') AS ' . $db->qn('min_height'))
                ->select('MAX(' . $db->qn('a.height') . ') AS ' . $db->qn('max_height'))
                ->select('MIN(' . $db->qn('a.depth')  . ') AS ' . $db->qn('min_depth'))
                ->select('MAX(' . $db->qn('a.depth')  . ') AS ' . $db->qn('max_depth'))
                ->select('MIN(' . $db->qn('a.weight')  . ') AS ' . $db->qn('min_weight'))
                ->select('MAX(' . $db->qn('a.weight')  . ') AS ' . $db->qn('max_weight'))
                ->from($db->quoteName('#__ishop_products', 'a'))
                ->whereIn($db->quoteName('a.id'), $products_id);
            $db->setQuery($query);

            $this->_all_products_main = $db->loadObject();

            if ($this->_all_products_main === false) {
                // Если не удалось получить цены товаров из базы данных
                throw new RuntimeException('Не удалось загрузить список параметров товаров', 500);
            }
        }

        return $this->_all_products_main;
    }

    /**
     * Получаем список всех производителей по категории
     *
     * @return  array массив производителей
     * @throws \Exception
     * @since 1.0.0
     */
    public function getFilterManufacturers()
    {
        if ($this->_manufacturers === null) {
            $products_id = $this->getItemsId();

            if (empty($products_id)) {
                $this->_manufacturers = [];
                return $this->_manufacturers;
            }

            $db = $this->getDatabase();
            $query = $db->getQuery(true);
            $query
                ->select('DISTINCT ' . $db->quoteName('a.manufacturer_id', 'id'))
                ->select($db->quoteName('manufacturer.title'))
                ->from($db->quoteName('#__ishop_products', 'a'))
                ->join(
                    'INNER',
                    $db->quoteName('#__ishop_manufacturers', 'manufacturer'),
                    $db->quoteName('manufacturer.id') . ' = ' . $db->quoteName('a.manufacturer_id')
                )
                ->whereIn($db->quoteName('a.id'), $products_id)
                ->order('manufacturer.ordering ASC');
            $db->setQuery($query);
            $this->_manufacturers = $db->loadAssocList();

            if ($this->_manufacturers === false) {
                // Если не удалось получить цены товаров из базы данных
                throw new RuntimeException('Не удалось загрузить список производителей', 500);
            }
        }

        return $this->_manufacturers;
    }
}
