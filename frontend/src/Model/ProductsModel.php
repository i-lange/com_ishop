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

use DateTime;
use Ilange\Component\Ishop\Site\Helper\PriceHelper;
use Ilange\Component\Ishop\Site\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Эта модель поддерживает получение списка товаров
 * @since 1.0.0
 */
class ProductsModel extends ListModel
{
    /**
     * Конструктор
     * @param array $config Ассоциативный массив параметров конфигурации, необязательно
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
                'ordering', 'a.ordering',
                'featured', 'a.featured',
                'language', 'a.language',
                'hits', 'a.hits',
                'min_price', 'a.min_price',
                'max_price', 'a.max_price',
                'ishop_fields', 'a.ishop_fields',
                'manufacturers', 'a.manufacturers', 'a.manufacturer_id',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
                'images', 'a.images',
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
        $input = $app->getInput();

        // Загружаем параметры компонента.
        // Объединяем глобальные параметры и параметры пункта меню
        $params = $app->getParams();
        $this->setState('params', $params);

        // Количество товаров на странице
        $value = $input->get('limit', $app->get('list_limit', 0), 'uint');
        $this->setState('list.limit', $value);

        // С какого элемента начинать
        $value = $input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        // Фильтрация, теги
        $value = $input->get('filter_tag', 0, 'uint');
        $this->setState('filter.tag', $value);

        // Фильтрация, поле сортировки
        $orderCol = $input->get('filter_order', 'a.ordering');
        if (!in_array($orderCol, $this->filter_fields)) {
            $orderCol = 'a.ordering';
        }
        $this->setState('list.ordering', $orderCol);

        // Фильтрация, порядок сортировки
        $listOrder = $input->get('filter_order_Dir', 'ASC');
        if (!in_array(strtoupper($listOrder), ['ASC', 'DESC'])) {
            $listOrder = 'ASC';
        }
        $this->setState('list.direction', $listOrder);

        // Фильтрация, состояние публикации
        $user = $this->getCurrentUser();
        if ((!$user->authorise('core.edit.state', 'com_ishop')) && (!$user->authorise('core.edit', 'com_ishop'))) {
            $this->setState('filter.published', 1);
        }

        // Фильтрация, язык
        $this->setState('filter.language', Multilanguage::isEnabled());


        // Показывать не авторизованным
        if ((!$params->get('show_noauth'))) {
            $this->setState('filter.access', true);
        } else {
            $this->setState('filter.access', false);
        }

        // Шаблон вывода
        $this->setState('layout', $input->getString('layout'));

        // Фильтрация по минимальной цене
        $value = $input->get('min_price',  0);
        if ($value == '') {
            $value = 0;
        }
        $this->setState('filter.min_price', $value);

        // Фильтрация по максимальной цене
        $value = $input->get('max_price', 0);
        if ($value == '') {
            $value = 0;
        }
        $this->setState('filter.max_price', $value);

        // Фильтрация, характеристики товаров
        $ishop_fields = $input->get('ishop_fields', []);
        if (!empty($ishop_fields)) {
            $this->setState('filter.ishop_fields', $ishop_fields);
        }

        // Фильтрация по списку производителей
        $value = $input->get('manufacturers',  []);
        if (isset($value[0]) && $value[0] == 0) {
            array_shift($value);
        }
        $this->setState('filter.manufacturers', $value);

        // Фильтрация по наличию товаров на складах
        $value = $input->get('warehouses',  []);
        if (isset($value[0]) && $value[0] == 0) {
            array_shift($value);
        }
        $this->setState('filter.warehouses', $value);

        // Фильтрация по id производителя
        $value = $input->get('manufacturer_id', 0);
        $this->setState('filter.manufacturer_id', $value);

        // Фильтрация по id склада
        $value = $input->get('warehouse_id', false);
        $this->setState('filter.warehouse_id', $value);

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
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.featured');
        $id .= ':' . serialize($this->getState('filter.product_id'));
        $id .= ':' . $this->getState('filter.product_id.include');
        $id .= ':' . serialize($this->getState('filter.category_id'));
        $id .= ':' . $this->getState('filter.category_id.include');
        $id .= ':' . serialize($this->getState('filter.author_id'));
        $id .= ':' . $this->getState('filter.author_id.include');
        $id .= ':' . serialize($this->getState('filter.author_alias'));
        $id .= ':' . $this->getState('filter.author_alias.include');
        $id .= ':' . $this->getState('filter.date_filtering');
        $id .= ':' . $this->getState('filter.date_field');
        $id .= ':' . $this->getState('filter.start_date_range');
        $id .= ':' . $this->getState('filter.end_date_range');
        $id .= ':' . $this->getState('filter.relative_date');
        $id .= ':' . serialize($this->getState('filter.tag'));

        return parent::getStoreId($id);
    }


    /**
     * Основной запрос для получения списка товаров на основе состояния модели
     * @return \Joomla\Database\QueryInterface
     * @throws \Exception
     * @since 1.0.0
     */
    protected function getListQuery()
    {
        $user = $this->getCurrentUser();
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $conditionArchived    = 2;
        $conditionUnpublished = 0;

        $query
            ->select(
                $this->getState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.manufacturer_id'),
                    $db->quoteName('a.supplier_id'),
                    $db->quoteName('a.prefix_id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.alias'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                    $db->quoteName('a.catid'),
                    'CASE WHEN ' . $db->quoteName('a.publish_up') . ' IS NULL THEN ' . $db->quoteName('a.created')
                    . ' ELSE ' . $db->quoteName('a.publish_up') . ' END AS ' . $db->quoteName('publish_up'),
                    $db->quoteName('a.publish_down'),
                    $db->quoteName('a.images'),
                    $db->quoteName('a.attribs'),
                    $db->quoteName('a.metadata'),
                    $db->quoteName('a.access'),
                    $db->quoteName('a.hits'),
                    $db->quoteName('a.stock'),
                    $db->quoteName('a.price'),
                    $db->quoteName('a.old_price'),
                    $db->quoteName('a.sale_price'),
                    $db->quoteName('a.cost_price'),
                    $db->quoteName('a.delivery'),
                    $db->quoteName('a.gtin'),
                    $db->quoteName('a.rating'),
                    $db->quoteName('a.reviews_count'),
                    $db->quoteName('a.bitrix24_id'),
                    $db->quoteName('a.featured'),
                    $db->quoteName('a.language'),
                    $db->quoteName('a.ordering'),
                ]
            )
        )
            ->select(
                [
                    $db->quoteName('category.title', 'category_title'),
                    $db->quoteName('category.access', 'category_access'),
                    $db->quoteName('manufacturer.title', 'manufacturer_title'),
                    $db->quoteName('supplier.title', 'supplier_title'),
                    $db->quoteName('prefix.title', 'prefix'),
                    $query->concatenate(
                        [
                            $db->quoteName('prefix.title'),
                            $db->quoteName('manufacturer.title'),
                            $db->quoteName('a.title'),
                        ], ' ' ) . ' AS ' . $db->quoteName('fullname'),

                ]
            )
            ->from($db->quoteName('#__ishop_products', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__categories', 'category'),
                $db->quoteName('category.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->join(
                'INNER',
                $db->quoteName('#__ishop_manufacturers', 'manufacturer'),
                $db->quoteName('manufacturer.id') . ' = ' . $db->quoteName('a.manufacturer_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__ishop_suppliers', 'supplier'),
                $db->quoteName('supplier.id') . ' = ' . $db->quoteName('a.supplier_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__ishop_prefixes', 'prefix'),
                $db->quoteName('prefix.id') . ' = ' . $db->quoteName('a.prefix_id')
            );


        // Фильтрация, по уровню доступа
        if ($this->getState('filter.access', true)) {
            $groups = $this->getState('filter.viewlevels', $user->getAuthorisedViewLevels());
            $query->whereIn($db->quoteName('a.access'), $groups)
                ->whereIn($db->quoteName('category.access'), $groups);
        }


        // Фильтрация, по состоянию публикации
        $condition = $this->getState('filter.published');
        if (is_numeric($condition) && $condition == 2) {
            /**
             * Если категория в архиве, то товар должен быть опубликован или заархивирован
             * Если категория опубликована, то товар должен быть заархивирован
             */
            $query
                ->where('((' . $db->quoteName('category.published') . ' = 2 AND ' . $db->quoteName('a.state') . ' > :conditionUnpublished)'
                        . ' OR (' . $db->quoteName('category.published') . ' = 1 AND ' . $db->quoteName('category.state') . ' = :conditionArchived))')
                ->bind(':conditionUnpublished', $conditionUnpublished, ParameterType::INTEGER)
                ->bind(':conditionArchived', $conditionArchived, ParameterType::INTEGER);
        } elseif (is_numeric($condition)) {
            $condition = (int) $condition;

            // Категория должна быть опубликована
            $query
                ->where($db->quoteName('category.published') . ' = 1 AND ' . $db->quoteName('a.state') . ' = :condition')
                ->bind(':condition', $condition, ParameterType::INTEGER);
        } elseif (is_array($condition)) {
            // Категория должна быть опубликована
            $query->where(
                $db->quoteName('category.published') . ' = 1 AND ' . $db->quoteName('a.state')
                . ' IN (' . implode(',', $query->bindArray($condition)) . ')'
            );
        }


        // Фильтрация, по производителям
        $manufacturers  = $this->getState('filter.manufacturers');
        $manufacturers = ArrayHelper::toInteger($manufacturers);
        if ($manufacturers && $manufacturers[0] !== 0) {
            $query->where('a.manufacturer_id IN (' . implode(',', $manufacturers) . ')');
        } else {
            // Фильтрация, по id конкретного производителя
            $manufacturer_id  = (int) $this->getState('filter.manufacturer_id');
            if ($manufacturer_id) {
                $query
                    ->where($db->quoteName('a.manufacturer_id') . ' = :brand_id')
                    ->bind(':brand_id', $manufacturer_id, ParameterType::INTEGER);
            }
        }

        // Фильтрация, по цене товаров
        $min_price = $this->getState('filter.min_price', 0);
        $max_price = $this->getState('filter.max_price', 0);
        if ($min_price || $max_price) {
            $min_price = ($min_price) ? round($min_price, 2) - 0.01 : null;
            $max_price = ($max_price) ? round($max_price, 2) + 0.01 : null;

            if ($min_price) $query->where($db->qn('a.price') . ' >= ' . $min_price);
            if ($max_price) $query->where($db->qn('a.price') . ' <= ' . $max_price);
        }

        // Фильтрация, по ширине
        $min_width = $this->getState('filter.min_width', 0);
        $max_width = $this->getState('filter.max_width', 0);
        if ($min_width || $max_width) {
            $min_width = ($min_width) ? round($min_width, 2) - 0.01 : null;
            $max_width = ($max_width) ? round($max_width, 2) + 0.01 : null;

            if ($min_width) $query->where($db->qn('a.width') . ' >= ' . $min_width);
            if ($max_width) $query->where($db->qn('a.width') . ' <= ' . $max_width);
        }

        // Фильтрация, по высоте
        $min_height = $this->getState('filter.min_height', 0);
        $max_height = $this->getState('filter.max_height', 0);
        if ($min_height || $max_height) {
            $min_height = ($min_height) ? round($min_height, 2) - 0.01 : null;
            $max_height = ($max_height) ? round($max_height, 2) + 0.01 : null;

            if ($min_height) $query->where($db->qn('a.height') . ' >= ' . $min_height);
            if ($max_height) $query->where($db->qn('a.height') . ' <= ' . $max_height);
        }

        // Фильтрация, по глубине
        $min_depth = $this->getState('filter.min_depth', 0);
        $max_depth = $this->getState('filter.max_depth', 0);
        if ($min_depth || $max_depth) {
            $min_depth = ($min_depth) ? round($min_depth, 2) - 0.01 : null;
            $max_depth = ($max_depth) ? round($max_depth, 2) + 0.01 : null;

            if ($min_depth) $query->where($db->qn('a.depth') . ' >= ' . $min_depth);
            if ($max_depth) $query->where($db->qn('a.depth') . ' <= ' . $max_depth);
        }

        // Фильтрация, по весу
        $min_weight = $this->getState('filter.min_weight', 0);
        $max_weight = $this->getState('filter.max_weight', 0);
        if ($min_weight || $max_weight) {
            $min_weight = ($min_weight) ? round($min_weight, 2) - 0.01 : null;
            $max_weight = ($max_weight) ? round($max_weight, 2) + 0.01 : null;

            if ($min_weight) $query->where($db->qn('a.weight') . ' >= ' . $min_weight);
            if ($max_weight) $query->where($db->qn('a.weight') . ' <= ' . $max_weight);
        }

        // Фильтрация, по характеристикам товаров
        $ishop_fields = $this->getState('filter.ishop_fields', []);
        $categoryId = $this->getState('filter.category_id', 0);
        if (!empty($ishop_fields) && is_numeric($categoryId) && $categoryId > 0) {
            $filter_count = 0;
            foreach ($ishop_fields as $field_id => $value) {
                // В зависимости от типа характеристики
                if (is_numeric($value) && $value > 0) {
                    // Это тип Да/Нет
                    $query->where($db->qn('map.field_' . $field_id) . ' >= ' . $value);
                    $filter_count++;
                } elseif (is_array($value)) {
                    if (isset($value['min']) || isset($value['max'])) {
                        // Это числовой тип, фильтруем от и/или до
                        $min = (int) $value['min'];
                        $max = (int) $value['max'];

                        if ($min > 0) {
                            $query->where($db->qn('map.field_' . $field_id) . ' >= ' . $min);
                            $filter_count++;
                        }
                        if ($max > 0) {
                            $query->where($db->qn('map.field_' . $field_id) . ' <= ' . $max);
                            $filter_count++;
                        }
                    } else {
                        // Это список текстовых значений, фильтруем по вхождению
                        if (isset($value[0]) && $value[0] == 0) {
                            array_shift($value);
                        }
                        if (!empty($value)) {
                            $query->whereIn($db->qn('map.field_' . $field_id), $value);
                            $filter_count++;
                        }
                    }
                }
            }
            if ($filter_count) {
                // определяем имя таблицы для фильтрации
                $tableName = '#__ishop_filter_cat_' . $categoryId;
                $query->join(
                    'INNER',
                    $db->quoteName($tableName, 'map'),
                    $db->quoteName('map.product_id') . ' = ' . $db->quoteName('a.id')
                );
            }
        }

        // Фильтрация, по одной либо нескольким категориям
        $categoryId = $this->getState('filter.category_id');
        if (is_numeric($categoryId)) {
            $type = $this->getState('filter.category_id.include', true) ? ' = ' : ' <> ';

            // Проверка подкатегорий
            $includeSubcategories = $this->getState('filter.subcategories', false);
            if ($includeSubcategories) {
                $categoryId = (int) $categoryId;
                $levels     = (int) $this->getState('filter.max_category_levels', 1);

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

                $query->bind(':subCategoryId', $categoryId, ParameterType::INTEGER);

                if ($levels >= 0) {
                    $subQuery->where($db->quoteName('sub.level') . ' <= ' . $db->quoteName('this.level') . ' + :levels');
                    $query->bind(':levels', $levels, ParameterType::INTEGER);
                }

                // Добавляем подзапрос к основному запросу
                $query->where(
                    '(' . $db->quoteName('a.catid') . $type . ':categoryId OR ' . $db->quoteName('a.catid') . ' IN (' . $subQuery . '))'
                );
                $query->bind(':categoryId', $categoryId, ParameterType::INTEGER);

            } else {
                $query->where($db->quoteName('a.catid') . $type . ':categoryId');
                $query->bind(':categoryId', $categoryId, ParameterType::INTEGER);
            }
        } elseif (is_array($categoryId) && (count($categoryId) > 0)) {
            $categoryId = ArrayHelper::toInteger($categoryId);

            if (!empty($categoryId)) {
                if ($this->getState('filter.category_id.include', true)) {
                    $query->whereIn($db->quoteName('a.catid'), $categoryId);
                } else {
                    $query->whereNotIn($db->quoteName('a.catid'), $categoryId);
                }
            }
        }

        // Фильтрация, языку контента
        if ($this->getState('filter.language')) {
            $query->whereIn($db->quoteName('a.language'), [Factory::getApplication()->getLanguage()->getTag(), '*'], ParameterType::STRING);
        }

        // Фильтрация, по списку идентификаторов товаров
        $id_list = $this->getState('filter.products', []);
        if (!empty($id_list)) {
            $query->whereIn($db->quoteName('a.id'), $id_list);
        }

        // Фильтрация, по наличию на складах
        $warehouses  = $this->getState('filter.warehouses');
        $warehouses = ArrayHelper::toInteger($warehouses);
        if ($warehouses && $warehouses[0] !== 0) {
            // Дополнительный запрос для проверки
            // наличия товаров хотя бы на одном из складов #__ishop_warehouses_stock
            $stockQuery = $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__ishop_warehouses_stock', 'ws'))
                ->where($db->quoteName('ws.product_id') . ' = ' . $db->quoteName('a.id'))
                ->where($db->quoteName('ws.warehouse_id') . ' IN (' . implode(',', $query->bindArray($warehouses)) . ')');

            $query->where('EXISTS (' . $stockQuery . ')');
        } elseif ($this->getState('filter.warehouse_id', false) !== false) {
            // Фильтрация, по наличию на конкретном складе
            $warehouse_id  = (int) $this->getState('filter.warehouse_id');

            // Дополнительный запрос для проверки наличия
            $stockQuery = $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__ishop_warehouses_stock', 'ws'))
                ->where($db->quoteName('ws.product_id') . ' = ' . $db->quoteName('a.id'));

            if ($warehouse_id > 0) {
                // наличие товаров на указанном складе #__ishop_warehouses_stock
                $stockQuery->where($db->quoteName('ws.warehouse_id') . ' = ' . $warehouse_id);
            }

            $query->where('EXISTS (' . $stockQuery . ')');
        }

        // Фильтрация, по одному либо по группе тегов
        $tagId = $this->getState('filter.tag');
        if (is_array($tagId) && count($tagId) === 1) {
            $tagId = current($tagId);
        }

        if (is_array($tagId)) {
            $tagId = ArrayHelper::toInteger($tagId);

            if ($tagId) {
                $subQuery = $db->getQuery(true)
                    ->select('DISTINCT ' . $db->quoteName('content_item_id'))
                    ->from($db->quoteName('#__contentitem_tag_map'))
                    ->where(
                        [
                            $db->quoteName('tag_id') . ' IN (' . implode(',', $query->bindArray($tagId)) . ')',
                            $db->quoteName('type_alias') . ' = ' . $db->quote('com_ishop.product'),
                        ]
                    );

                $query->join(
                    'INNER',
                    '(' . $subQuery . ') AS ' . $db->quoteName('tagmap'),
                    $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
                );
            }

        } elseif ($tagId = (int) $tagId) {
            $query
                ->join('INNER',
                    $db->quoteName('#__contentitem_tag_map', 'tagmap'),
                    $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
                    . ' AND ' . $db->quoteName('tagmap.type_alias') . ' = ' . $db->quote('com_ishop.product'))
                ->where($db->quoteName('tagmap.tag_id') . ' = :tagId')
                ->bind(':tagId', $tagId, ParameterType::INTEGER);
        }


        // Порядок сортировки списка
        $query->order('(a.stock = 0) ASC, ' .
            $db->escape($this->getState('list.ordering', 'a.price')) . ' ' .
            $db->escape($this->getState('list.direction', 'DESC'))
        );

        return $query;
    }

    /**
     * Метод для получения списка товаров,
     * переопределяем
     * @return mixed Массив элементов или false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();
        $params = ComponentHelper::getParams('com_ishop');

        // Если нужно показывать расчет оплаты частями
        $parts = false;
        if ($params->get('payments_use', 0) && $params->get('parts_use', 0)) {
            $parts = $this->getMVCFactory()->createModel('Parts', 'Site')->getItems();

            foreach ($parts as $part) {
                if (!$part->attribs['cats_label_show']) {
                    unset($part);
                }
            }
        }

        // Текущая зона доставки
        $active_zone = $this->getMVCFactory()->createModel('Zones', 'Site')->getActive();
        // Текущая дата
        $today = new DateTime();
        // Завтра
        $tomorrow = (clone $today)->modify('+1 day');
        // Послезавтра
        $day_after = (clone $today)->modify('+2 day');

        // Текущая корзина пользователя
        $cart = $this->getMVCFactory()->createModel('Cart', 'Site')->getCartList();
        // Текущий список избранного пользователя
        $wishlist = $this->getMVCFactory()->createModel('Wishlist', 'Site')->getWishlistList();
        // Текущий список сравнения пользователя
        $compare = $this->getMVCFactory()->createModel('Compare', 'Site')->getCompareList();

        // Флаг использования скидок на сайте
        $canUseDiscounts = $params->get('discounts_use', 0);
        // Флаг использования предустановленных скидок на сайте
        $canUseManualDiscounts = $params->get('discounts_use_manual', 0);
        // Флаг использования автоматических скидок на сайте
        $canUseAutoDiscounts = $params->get('discounts_use_auto', 0);
        // Параметры расчета автоматических скидок
        $min_percent = $params->get('discounts_auto_percent', 0);
        $min_value   = $params->get('discounts_auto_value', 0);
        // Способ вычисления автоматических скидок
        // 1 - От цены закупки
        // 2 - От старой цены
        // 3 - От основной цены
        $AutoDiscountsMode = $params->get('discounts_auto_mode', 1);

        foreach ($items as $item) {
            // Дополнительные атрибуты
            $item->attribs = (new Registry($item->attribs))->toArray();
            foreach ($item->attribs as $key => $element) {
                if ((int) $element === 0) {
                    unset($item->attribs[$key]);
                }
            }

            // Если получили данные комплектации
            if (isset($item->equipment)) {
                $item->equipment = json_decode($item->equipment);
            }

            // Проверим, какие варианты оплаты подходят для текущего товара
            $item->parts = [];
            if (!empty($parts)) {
                foreach ($parts as $part) {
                    // Изначально проверяем,
                    // что данная оплата частями применима
                    // для текущего товара

                    // Если категории заданы
                    if (!empty($part->attribs['cats']) && !in_array($item->catid, $part->attribs['cats'])) {
                        continue;
                    }

                    // Если производители заданы
                    if (!empty($part->attribs['manufacturers']) && !in_array($item->manufacturer_id, $part->attribs['manufacturers'])) {
                        continue;
                    }

                    // Определяем, какая цена используется для расчета оплаты
                    $partPrice = PriceHelper::getPartPrice($item, $part->attribs['price_mode'] ?? 1);

                    // Если товар подходит, нужно рассчитать оплату
                    // Проходим по каждому из сроков кредитования
                    $rules = [];
                    foreach ($part->attribs['parts_rules'] as $rule) {
                        $rules[$rule['period']] = ProductHelper::calculatePaymentParts($partPrice, $rule['period'], $part->attribs['first_part'], $rule['percent']);
                    }

                    $current = new stdClass();
                    $current->title = $part->title;
                    $current->alias =  $part->alias;
                    $current->type =  $part->type;
                    $current->introtext = $part->introtext;
                    $current->icon = $part->icon;
                    $current->cats_label = $part->attribs['cats_label'];
                    $current->cats_label_param = $part->attribs['cats_label_param'];
                    $current->first_part = $part->attribs['first_part'];
                    $current->min_payment = min(array_column($rules, 'monthly_payment'));
                    $current->min_rate = min(array_column($part->attribs['parts_rules'], 'percent'));
                    $current->max_period = max(array_column($part->attribs['parts_rules'], 'period'));
                    $current->rules = $rules;
                    unset($rules);

                    $item->parts[] = $current;
                }
            }

            // Проверим, находится ли товар в корзине
            $item->incart = in_array($item->id, array_keys($cart));
            if ($item->incart) {
                $item->incart_count = $cart[$item->id];
            }

            // Проверим, находится ли товар в избранном
            $item->inwishlist = in_array($item->id, $wishlist);

            // Проверим, находится ли товар в сравнении
            $item->incompare = in_array($item->id, $compare);

            if (isset($item->images)) {
                $item->images = json_decode($item->images);
            }

            $item->delivery = json_decode($item->delivery, true);
            $item->delivery_date = '';

            if (!empty($item->delivery[$active_zone])) {
                $item->delivery_date = $item->delivery[$active_zone];

                try {
                    $date = new DateTime($item->delivery[$active_zone]);

                    if ($date->format('Y-m-d') == $today->format('Y-m-d')) {
                        $item->delivery = Text::_('DATE_FORMAT_TODAY');
                    } elseif ($date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
                        $item->delivery = Text::_('DATE_FORMAT_TOMORROW');
                    } elseif ($date->format('Y-m-d') == $day_after->format('Y-m-d')) {
                        $item->delivery = Text::_('DATE_FORMAT_DAY_AFTER');
                    } elseif ($date < $today) {
                        $item->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
                    } else {
                        // Любая другая будущая дата
                        $item->delivery = HTMLHelper::_('date', $date->format('Y-m-d'), Text::_('DATE_FORMAT_FUTURE'));
                    }
                } catch (\Exception $e) {
                    // Обработка невалидных дат
                    $item->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
                }
            } else {
                $item->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
            }

            // Доступность товара для заказа
            if ($item->stock > 0 || $item->stock === -1 ) {
                $item->available = true;
            } else {
                $item->available = false;
            }

            // Полное наименование товара
            $item->fullname = $item->prefix . ' ' . $item->manufacturer_title . ' ' . $item->title;

            // По-умолчанию размер скидки в процентах равен 0
            $item->discount_size = 0;

            // Если применение скидок отключено - переходим к следующему товару
            if (!$canUseDiscounts) {
                continue;
            }

            // Если применение скидок включено
            // Проверим, используются ли предустановленные скидки
            if ($canUseManualDiscounts) {
                // Если для товара были заданы старая цена и цена со скидкой,
                // рассчитаем размер скидки в процентах
                if ($item->old_price > 0 && $item->sale_price > 0) {
                    $item->discount_size = round(100 - ($item->sale_price / $item->old_price * 100));
                }
            }

            // Проверим, используются ли автоматические скидки
            // Автоматические скидки применяются, если на товар не действует предустановленные скидки
            // Однако у товара должна быть установлена старая цена
            if ($canUseAutoDiscounts && ($item->discount_size === 0) && $item->old_price > 0) {
                if (!$min_percent && !$min_value) {
                    continue;
                }

                switch ($AutoDiscountsMode) {
                    // 1 - От цены закупки
                    case 1:
                        // Для расчета от цены закупки у товара должны быть заданы:
                        // - цена закупки товара cost_price
                        // - основная цена товара price
                        if ($item->cost_price > 0 && $item->price > 0) {
                            $current_percent  = round(100 - ($item->cost_price / $item->price * 100));
                            $current_value    = $item->price - $item->cost_price;

                            if ($min_percent > 0 && $min_percent <= $current_percent) {
                                $item->discount_size = round(100 - ($item->price / $item->old_price * 100));
                                break;
                            }

                            if ($min_value > 0 && $min_value <= $current_value) {
                                $item->discount_size = round(100 - ($item->price / $item->old_price * 100));
                            }
                        }

                        break;

                    // 2 - От старой цены
                    case 2:
                        // Для расчета от старой цены у товара должны быть заданы:
                        // - старая цена товара old_price
                        // - основная цена товара price
                        if ($item->price > 0) {
                            $current_percent = round(100 - ($item->price / $item->old_price * 100));
                            $current_value   = $item->old_price - $item->price;

                            if ($min_percent > 0 && $min_percent <= $current_percent) {
                                $item->discount_size = $current_percent;
                                break;
                            }

                            if ($min_value > 0 && $min_value <= $current_value) {
                                $item->discount_size = $current_percent;
                            }
                        }

                        break;

                    // 3 - От основной цены
                    case 3:
                        // Для расчета от основной цены у товара должны быть заданы:
                        // - основная цена товара price
                        // - цена товара со скидкой sale_price
                        if ($item->price > 0 && $item->sale_price > 0) {
                            $current_percent = round(100 - ($item->sale_price / $item->price * 100));
                            $current_value   = $item->price - $item->sale_price;

                            if ($min_percent > 0 && $min_percent <= $current_percent) {
                                $item->discount_size = round(100 - ($item->price / $item->old_price * 100));
                                break;
                            }

                            if ($min_value > 0 && $min_value <= $current_value) {
                                $item->discount_size = round(100 - ($item->price / $item->old_price * 100));
                            }
                        }

                        break;
                }
            }
        }

        return $items;
    }

    /**
     * Метод для получения списка идентификаторов товаров
     *
     * @return array Массив элементов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItemsId()
    {
        $db     = $this->getDatabase();
        $query = $this->getListQuery();

        if (empty($query)) {
            return [];
        }

        // Изменяем запрос на получение товаров,
        // чтобы получить только список идентификаторов
        if ($query instanceof QueryInterface) {
            $query = clone $query;
            $query->clear('select')
                ->select($db->quoteName('a.id'))
                ->clear('order')
                ->clear('limit');
        }

        $db->setQuery($query);

        return $db->loadColumn();
    }

    /**
     * Метод для получения
     * минимальной и максимальной цены
     *
     * @return array Массив элементов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getMinMaxPrice()
    {
        $db     = $this->getDatabase();
        $query = $this->getListQuery();

        if (empty($query)) {
            return [];
        }

        // Изменяем запрос на получение товаров,
        // чтобы получить только цены
        if ($query instanceof QueryInterface) {
            $query = clone $query;
            $query->clear('select')
                ->select('MIN(' . $db->qn('a.price') . ') AS ' .$db->qn('min_price'))
                ->select('MAX(' . $db->qn('a.price') . ') AS ' .$db->qn('max_price'))
                ->clear('order')
                ->clear('limit');
        }

        $db->setQuery($query);

        return $db->loadRow();
    }

    /**
     * Метод для получения
     * списка всех производителей
     *
     * @return array Массив элементов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getManufacturers()
    {
        $db     = $this->getDatabase();
        $query = $this->getListQuery();

        if (empty($query)) {
            return [];
        }

        // Изменяем запрос на получение товаров,
        // чтобы получить только цены
        if ($query instanceof QueryInterface) {
            $query = clone $query;
            $query->clear('select')
                ->select('DISTINCT ' . $db->quoteName('a.manufacturer_id', 'id'))
                ->select($db->quoteName('manufacturer.title'))
                ->clear('order')
                ->clear('limit');
        }

        $db->setQuery($query);

        return $db->loadAssocList();
    }
}