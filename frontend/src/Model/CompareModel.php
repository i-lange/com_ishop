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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

/**
 * Модель списка сравнения com_iShop
 * @since 1.0.0
 */
class CompareModel extends BaseDatabaseModel
{
    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        $params	= $app->getParams();
        $this->setState('params', $params);

        // Получаем и устанавливаем id категории
        $category_id = $app->getUserStateFromRequest('com_ishop.compare.category_id', 'category_id', 0, 'int');
        // Если не выбрана конкретная категория для просмотра
        // по умолчанию установим первую из списка
        if ($category_id === 0) {
            $category_id = $this->getCategory();
        } elseif ($this->isEmptyCategory($category_id)) {
            // Сбросим состояние пользователя,
            // так как в ней нет товаров для сравнения
            $category_id = $this->getCategory();
            $app->setUserState('com_ishop.compare.category_id', 0);
        }

        $this->setState('category_id', $category_id);
    }

    /**
     * Метод получения количества товаров в списке сравнения
     *
     * @return int Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCount()
    {
        return count($this->getCompareList());
    }

    /**
     * Метод получения списка идентификаторов в списке сравнения
     *
     * @return array Массив идентификаторов
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCompareList()
    {
        // Получаем данные пользователя
        $userData = $this->getMVCFactory()->createModel('User', 'Site')->getItem();

        if (empty($userData) || empty($userData->compare)) {
            return [];
        }

        return $userData->compare;
    }

    /**
     * Метод получения данных списка товаров в сравнении
     *
     * @param array $pks массив идентификаторов товаров
     * @param bool $full флаг объема данных по товарам
     * @param int $category_id идентификатор категории для фильтрации
     *
     * @return array данные списка товаров в сравнении
     * @throws \Exception
     * @since 1.0.0
     */
    public function getProducts(array $pks, bool $full = false, int $category_id = 0)
    {
        if (empty($pks)) {
            return [];
        }

        $model = $this->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]);
        $model->setState('filter.warehouse_id', false);
        $model->setState('params', Factory::getApplication()->getParams());

        // Фильтрация по уровню доступа
        $model->setState('filter.access', true);
        // Фильтрация по состоянию публикации
        $model->setState('filter.published', 1);
        // Язык
        $model->setState('filter.language', Multilanguage::isEnabled());
        // Добавляем фильтрацию по списку товаров в сравнении
        $model->setState('filter.products', $pks);

        // Добавляем фильтрацию по категории, если установлен такой параметр
        if ($category_id > 0) {
            $model->setState('filter.category_id', $category_id);
        }

        // Поля сортировки (всегда хотим получать в том порядке, в каком добавлял пользователь)
        $model->setState('list.ordering', 'FIELD(a.id, '. implode(',', $pks) . ')');
        // Направление сортировки
        $model->setState('list.direction', '');
        // Количество товаров на странице, все
        $model->setState('list.limit', 0);

        // Если флаг не установлен, значит полные данные не нужны
        $db = $this->getDatabase();
        if (!$full) {
            $model->setState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.stock'),
                    $db->quoteName('a.catid'),
                    $db->quoteName('a.manufacturer_id'),
                    $db->quoteName('a.price'),
                    $db->quoteName('a.old_price'),
                    $db->quoteName('a.sale_price'),
                    $db->quoteName('a.cost_price'),
                    $db->quoteName('a.delivery'),
                    $db->quoteName('a.gtin'),
                    $db->quoteName('a.attribs'),
                ]
            );
        } else {
            $model->setState(
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
                    $db->quoteName('a.width'),
                    $db->quoteName('a.height'),
                    $db->quoteName('a.depth'),
                    $db->quoteName('a.weight'),
                    $db->quoteName('a.width_pkg'),
                    $db->quoteName('a.height_pkg'),
                    $db->quoteName('a.depth_pkg'),
                    $db->quoteName('a.weight_pkg'),
                    $db->quoteName('a.equipment'),
                    $db->quoteName('a.country'),
                    $db->quoteName('a.warranty'),
                ]
            );
        }

        return $model->getItems();
    }

    /**
     * Метод получения данных списка сравнения текущего пользователя
     *
     * @return array Объект данных
     * @throws \Exception
     * @since 1.0.0
     */
    public function getCompare(array $filter = [], bool $full = true)
    {
        $compare = [];
        $pks = (!empty($filter)) ? $filter : $this->getCompareList();

        // если товаров нет в списке сравнения
        // возвращаем пустой объект
        if (empty($pks)) {
            return $compare;
        }

        $params = ComponentHelper::getParams('com_ishop');
        $group_default = (int) $params->get('group_default', 0);
        $group_sizes = (int) $params->get('group_sizes', 0);
        $group_other = (int) $params->get('group_other', 0);

        // Получим идентификатор категории, товары которой
        // будем сравнивать
        $catId = $this->getState('category_id');
        // Получим полные данные товаров для сравнения
        $products = $this->getProducts($pks, $full);

        // Список категорий
        $categories_ids = array_unique(array_column($products, 'catid'));
        // Получаем массив stdClass категорий с полями:
        // id, title, alias, params
        // в качестве индексов выступают значения id
        $compare = $this
            ->getMVCFactory()
            ->createModel('Categories', 'Site', ['ignore_request' => true])
            ->getListByIds($categories_ids);
        unset($categories_ids);

        // Переместим активную категорию в начало массива
        $active = $compare[$catId];
        unset($compare[$catId]);
        $compare = [$catId => $active] + $compare;
        unset($active);

        // Параметры активной категории
        $compare[$catId]->params = new Registry($compare[$catId]->params);

        // Проходим по всем товарам, сохраняем список сравнения
        foreach ($products as $product) {
            $prodId = $product->id;

            // Защитимся от несуществующих категорий
            if (!isset($compare[$product->catid])) {
                continue;
            }

            // Устанавливаем основные свойства
            if (!isset($compare[$product->catid]->products)) {
                $compare[$product->catid]->products = [];
                $compare[$product->catid]->groups = [];
                $compare[$product->catid]->count = 0;
            }

            // Подсчет товаров в списках
            $compare[$product->catid]->count++;

            // Далее нас интересуют только товары из активной категории
            if ($product->catid !== $catId) {
                continue;
            }

            // Сохраняем данные товара
            $compare[$catId]->products[$product->id] = $product;

            // Загрузим список характеристик данного товара
            $groups = $this
                ->getMVCFactory()
                ->createModel('Product', 'Site', ['ignore_request' => true])
                ->getFields($product->id, $compare[$catId]->params);

            foreach ($groups as $groupId => $group) {
                // Устанавливаем свойства группы характеристик
                if (!isset($compare[$catId]->groups[$groupId])) {
                    $fieldObj = new \stdClass();
                    $fieldObj->title = $group->title;
                    $fieldObj->images = $group->images;
                    $fieldObj->icon = $group->icon;
                    $fieldObj->fields = [];
                    $compare[$catId]->groups[$groupId] = $fieldObj;
                }

                foreach ($group->fields as $fieldId => $field) {
                    // Устанавливаем свойства текущей характеристики
                    if (!isset($compare[$catId]->groups[$groupId]->fields[$fieldId])) {
                        $fieldObj = new \stdClass();
                        $fieldObj->title   = $field->field_title;
                        $fieldObj->type    = $field->field_type;
                        $fieldObj->unit    = $field->field_unit;
                        $fieldObj->images  = $field->field_images;
                        $fieldObj->icon    = $field->field_icon;
                        $fieldObj->color   = $field->field_color;
                        // Флаг наличия разных значений характеристики
                        $fieldObj->ismixed = false;
                        $fieldObj->products = [];
                        $compare[$catId]->groups[$groupId]->fields[$fieldId] = $fieldObj;
                    }

                    // Устанавливаем значение характеристики для текущего товара
                    $fieldObj = new \stdClass();
                    $fieldObj->value   = $field->field_value;
                    $fieldObj->images  = $field->field_value_images;
                    $fieldObj->icon    = $field->field_value_icon;
                    $fieldObj->hint    = $field->field_value_hint;
                    $compare[$catId]->groups[$groupId]->fields[$fieldId]->products[$prodId] = $fieldObj;
                }

                // Если это группа с габаритами, в нее нужно добавить значения габаритов и веса товара
                if ($groupId === $group_sizes) {
                    $sizes = ['width', 'height', 'depth', 'weight', 'width_pkg', 'height_pkg', 'depth_pkg', 'weight_pkg'];

                    foreach ($sizes as $size) {
                        if (!isset($compare[$catId]->groups[$groupId]->fields[$size])) {
                            $fieldObj = new \stdClass();
                            $fieldObj->title   = Text::_('COM_ISHOP_PRODUCT_' . $size);
                            $fieldObj->type    = 0; // число
                            $fieldObj->unit    = ($size == 'weight' || $size == 'weight_pkg') ? Text::_('COM_ISHOP_KG') : Text::_('COM_ISHOP_SM');
                            $fieldObj->images  = '{}';
                            $fieldObj->icon    = $size;
                            $fieldObj->color   = '';
                            // Флаг наличия разных значений характеристики
                            $fieldObj->ismixed = false;
                            $fieldObj->products = [];
                            $compare[$catId]->groups[$groupId]->fields[$size] = $fieldObj;
                        }

                        // Устанавливаем значение габаритов для текущего товара
                        if (!empty($product->$size) && $product->$size > 0) {
                            $fieldObj = new \stdClass();
                            $fieldObj->value   = (float) $product->$size;
                            $fieldObj->images  = '{}';
                            $fieldObj->icon    = '';
                            $fieldObj->hint    = '';
                            $compare[$catId]->groups[$groupId]->fields[$size]->products[$prodId] = $fieldObj;
                        }
                    }

                    unset($sizes);

                } elseif ($groupId === $group_default) {
                    $defaults = ['equipment', 'country', 'warranty'];

                    foreach ($defaults as $def) {
                        if (!isset($compare[$catId]->groups[$groupId]->fields[$def])) {
                            $fieldObj = new \stdClass();
                            $fieldObj->title   = Text::_('COM_ISHOP_PRODUCT_' . $def);
                            $fieldObj->type    = 1; // список
                            $fieldObj->unit    = '';
                            $fieldObj->images  = '{}';
                            $fieldObj->icon    = $def;
                            $fieldObj->color   = '';
                            // Флаг наличия разных значений характеристики
                            $fieldObj->ismixed = false;
                            $fieldObj->products = [];
                            $compare[$catId]->groups[$groupId]->fields[$def] = $fieldObj;
                        }

                        // Устанавливаем значения
                        if (!empty($product->$def)) {
                            if ($def === 'equipment') {
                                $tem_value = '';
                                foreach ($product->$def as $item) {
                                    $tem_value .= $item->item . ' ' . $item->count . ' ' . $item->unit . "<br>";
                                }

                                $product->$def = $tem_value;
                            }
                            $fieldObj = new \stdClass();
                            $fieldObj->value   = $product->$def;
                            $fieldObj->images  = '{}';
                            $fieldObj->icon    = '';
                            $fieldObj->hint    = '';
                            $compare[$catId]->groups[$groupId]->fields[$def]->products[$prodId] = $fieldObj;
                        }
                    }
                }
            }
        }

        unset($products);

        foreach ($compare as $id => $category) {
            // Параметры категорий больше не нужны
            unset($category->params);

            // Подготовим данные активной категории
            if ($id === $catId) {
                // Если установлена основная группа характеристик,
                // ее нужно переставить на первую позицию
                if (isset($category->groups[$group_default])) {
                    $tmp_group = $category->groups[$group_default];
                    unset($category->groups[$group_default]);
                    $category->groups = [$group_default => $tmp_group] + $category->groups;
                    unset($tmp_group);
                }

                // Если установлена группа характеристик "Прочее",
                // ее нужно переставить на последнюю позицию
                if (isset($category->groups[$group_other])) {
                    $tmp_group = $category->groups[$group_other];
                    unset($category->groups[$group_other]);
                    $category->groups[$group_other] = $tmp_group;
                    unset($tmp_group);
                }

                // Если установлена группа характеристик "Габариты",
                // ее нужно переставить на самую последнюю позицию
                if (isset($category->groups[$group_sizes])) {
                    $tmp_group = $category->groups[$group_sizes];
                    unset($category->groups[$group_sizes]);
                    $category->groups[$group_sizes] = $tmp_group;
                    unset($tmp_group);
                }

                // Установим флаг для характеристик,
                // в которых есть различия между товарами
                foreach ($category->groups as $group) {
                    foreach ($group->fields as $field) {
                        if (count(array_unique(array_column($field->products,'value'))) > 1) {
                            $field->ismixed = true;
                        }
                    }
                }
            }
        }

        return $compare;
    }

    /**
     * Добавляет товар в список сравнения
     * по его идентификатору
     *
     * @param   int  $id        Идентификатор товара
     *
     * @return array|false объект с данными сравнения
     * @throws \Exception
     * @since 1.0.0
     */
    public function add(int $id = 0)
    {
        if (!$id) {
            return false;
        }

        $params = ComponentHelper::getParams('com_ishop');
        // Проверим, включена ли возможность сравнения товаров
        // в настройках компонента
        $use_compare = $params->get('use_compare', false);
        if (!$use_compare) {
            return false;
        }

        // Получим значение максимального числа
        // товаров в списке сравнения
        $max = $params->get('compare_max_count', 99);
        if (!$max) {
            return false;
        }

        // Получим значение максимального числа
        // товаров в одной группе (категории) сравнения
        $group_max = $params->get('compare_group_max_count', 9);
        if (!$group_max) {
            return false;
        }

        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $compare = (new Registry($data->compare))->toArray();
        // Сначала проверим, нет ли этого товара в списке
        $key = array_search($id, $compare, true);
        // Если такой товар нашелся - удаляем его
        if ($key !== false) {
            unset($compare[$key]);
        }

        // Удаляем последний элемент, если превышен лимит
        if (count($compare) > $max) {
            array_pop($compare);
        }

        $catid = $this->getCategory($id);
        if ($catid === false) {
            return false;
        }

        $products = $this->getProducts($compare, false);
        // Посчитаем количество товаров из данной категории
        $cat_count = 0;
        foreach ($products as $product) {
            if ($product->catid == $catid) {
                $cat_count++;
            }
        }

        // Если проходим лимит по категории
        if ($cat_count < $group_max) {
            // Добавляем новый элемент в начало
            array_unshift($compare, $id);
        }

        // Сохраняем список сравнения
        $data->compare = (string) new Registry($compare);
        $user->setData($data, 'compare');

        // Возвращаем данные обновленного списка сравнения
        return ['count' => count($compare), 'products' => $compare];
    }

    /**
     * Удаляет товар из списка сравнения по его идентификатору
     * или очищает список, если id не задан
     *
     * @param int $id Идентификатор товара
     *
     * @return array|false объект с данными сравнения
     * @throws \Exception
     * @since 1.0.0
     */
    public function remove(int $id = 0)
    {
        $user = $this->getMVCFactory()->createModel('User', 'Site');
        $app = Factory::getApplication();
        $data = $user->getItem();
        if ($data === false) {
            return false;
        }

        $compare = (new Registry($data->compare))->toArray();

        // Если не указан идентификатор товара,
        // очищаем весь список
        if (!$id || empty($compare)) {
            $data->compare = (string) new Registry([]);
            $user->setData($data, 'compare');
            // Сбросим состояние пользователя,
            // так как список сравнения пуст
            $app->setUserState('com_ishop.compare.category_id', 0);

            // Возвращаем данные обновленного списка
            return ['count' => 0, 'products' => []];
        }

        // Сначала проверим наличие этого товара в списке
        $key = array_search($id, $compare, true);
        // Если такой товар нашелся - удаляем его
        if ($key !== false) {
            unset($compare[$key]);
        }

        // Сохраняем список сравнения пользователя
        $data->compare = (string) new Registry($compare);
        $user->setData($data, 'compare');

        // Возвращаем данные обновленного списка сравнения
        return ['count' => count($compare), 'products' => $compare];
    }

    /**
     * Возвращает идентификатор категории товара
     * по его идентификатору
     *
     * @param int $id Идентификатор товара
     *
     * @return int идентификатор категории
     * @throws \Exception
     * @since 1.0.0
     */
    private function getCategory(int $id = 0)
    {
        if (!$id) {
            // если не указан идентификатор,
            // пытаемся вернуть категорию
            // первого товара в списке сравнения
            $pks = $this->getCompareList();

            if (empty($pks)) {
                return 0;
            }

            $id = array_shift($pks);
            unset($pks);
        }

        $product = $this->getMVCFactory()->createTable('Product', 'Administrator');

        if ($product->load($id)) {
            return $product->catid;
        }

        return false;
    }

    /**
     * Проверяем, есть ли в категории товары
     * для сравнения по ее идентификатору
     *
     * @param int $id Идентификатор категории
     *
     * @return bool пусто или нет
     * @throws \Exception
     * @since 1.0.0
     */
    private function isEmptyCategory(int $id)
    {
        if (!$id) {
            return false;
        }

        // Идентификаторы товаров в сравнении
        $pks = $this->getCompareList();

        // Список товаров в сравнении в данной категории
        $products = $this->getProducts($pks, false, $id);

        // Если количество равно нулю, значит пусто
        return count($products) === 0;
    }
}
