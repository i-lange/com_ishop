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
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Модель товара
 * @since 1.0.0
 */
class ProductModel extends ItemModel
{
    /**
     * Строка контекста модели
     * @var string
     * @since 1.0.0
     */
    protected $_context = 'com_ishop.product';

    /**
     * Метод для автоматического заполнения модели
     * Вызов getState в этом методе приведет к рекурсии
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    protected function populateState()
    {
        $app = Factory::getApplication();

        // Устанавливаем состояние из запроса - id товара
        $pk = $app->getInput()->getInt('id');
        $this->setState('product.id', $pk);

        // Отступ списка
        $offset = $app->getInput()->getUint('limitstart');
        $this->setState('list.offset', $offset);

        // Загружаем параметры компонента
        $params = $app->getParams();
        $this->setState('params', $params);

        $user = $this->getCurrentUser();

        // Если установлено значение $pk, то проверяем доступ по всему asset, в противном случае - только по имени компонента
        $asset = empty($pk) ? 'com_ishop' : 'com_ishop.product.' . $pk;

        if ((!$user->authorise('core.edit.state', $asset)) && (!$user->authorise('core.edit', $asset))) {
            $this->setState('filter.published', 1);
            $this->setState('filter.archived', 2);
        }

        $this->setState('filter.language', Multilanguage::isEnabled());
    }

    /**
     * Метод получения данных записи
     * @param int $pk Идентификатор записи
     * @return object|bool Объект данных записи при успехе, иначе false
     * @throws \Exception
     * @since 1.0.0
     */
    public function getItem($pk = null)
    {
        $user = $this->getCurrentUser();

        $pk = (int) ($pk ?: $this->getState('product.id'));

        if ($this->_item === null) {
            $this->_item = [];
        }

        if (!isset($this->_item[$pk])) {
            try {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true);

                $query
                    ->select(
                        $this->getState(
                        'item.select',
                        [
                            $db->quoteName('a.id'),
                            $db->quoteName('a.manufacturer_id'),
                            $db->quoteName('a.supplier_id'),
                            $db->quoteName('a.prefix_id'),
                            $db->quoteName('a.title'),
                            $db->quoteName('a.alias'),
                            $db->quoteName('a.introtext'),
                            $db->quoteName('a.fulltext'),
                            $db->quoteName('a.state'),
                            $db->quoteName('a.type'),
                            $db->quoteName('a.catid'),
                            $db->quoteName('a.created'),
                            $db->quoteName('a.created_by'),
                            $db->quoteName('a.created_by_alias'),
                            $db->quoteName('a.modified'),
                            $db->quoteName('a.modified_by'),
                            $db->quoteName('a.publish_up'),
                            $db->quoteName('a.publish_down'),
                            $db->quoteName('a.images'),
                            $db->quoteName('a.attribs'),
                            $db->quoteName('a.metadata'),
                            $db->quoteName('a.metatitle'),
                            $db->quoteName('a.metakey'),
                            $db->quoteName('a.metadesc'),
                            $db->quoteName('a.access'),
                            $db->quoteName('a.hits'),
                            $db->quoteName('a.language'),
                            $db->quoteName('a.gtin'),
                            $db->quoteName('a.price'),
                            $db->quoteName('a.sale_price'),
                            $db->quoteName('a.old_price'),
                            $db->quoteName('a.cost_price'),
                            $db->quoteName('a.stock'),
                            $db->quoteName('a.related'),
                            $db->quoteName('a.similar'),
                            $db->quoteName('a.offers'),
                            $db->quoteName('a.services'),
                            $db->quoteName('a.width'),
                            $db->quoteName('a.height'),
                            $db->quoteName('a.depth'),
                            $db->quoteName('a.weight'),
                            $db->quoteName('a.width_pkg'),
                            $db->quoteName('a.height_pkg'),
                            $db->quoteName('a.depth_pkg'),
                            $db->quoteName('a.weight_pkg'),
                            $db->quoteName('a.equipment'),
                            $db->quoteName('a.delivery'),
                            $db->quoteName('a.country'),
                            $db->quoteName('a.warranty'),
                            $db->quoteName('a.rating'),
                            $db->quoteName('a.reviews_count'),
                            $db->quoteName('a.bitrix24_id'),
                        ]
                        )
                    )
                    ->select(
                        [
                            $db->quoteName('category.title', 'category_title'),
                            $db->quoteName('category.access', 'category_access'),
                            $db->quoteName('category.params', 'category_params'),
                            $db->quoteName('manufacturer.title', 'manufacturer_title'),
                            $db->quoteName('supplier.title', 'supplier_title'),
                            $db->quoteName('prefix.title', 'prefix'),
                            $db->quoteName('user.name', 'author'),
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
                    )
                    ->join(
                        'LEFT',
                        $db->quoteName('#__users', 'user'),
                        $db->quoteName('user.id') . ' = ' . $db->quoteName('a.created_by'))
                    ->where(
                        [
                            $db->quoteName('a.id') . ' = :pk',
                            $db->quoteName('category.published') . ' > 0',
                        ]
                    )
                    ->bind(':pk', $pk, ParameterType::INTEGER);

                // Фильтрация по языку
                if ($this->getState('filter.language')) {
                    $query->whereIn(
                        $db->quoteName('a.language'),
                        [Factory::getApplication()->getLanguage()->getTag(), '*'],
                        ParameterType::STRING
                    );
                }

                if (!$user->authorise('core.edit.state', 'com_ishop.product.' . $pk)
                    && !$user->authorise('core.edit', 'com_ishop.product.' . $pk)) {
                    // Фильтрация по началу и окончанию публикации
                    $nowDate = Factory::getDate()->toSql();

                    $query
                        ->extendWhere(
                            'AND',
                            [
                                $db->quoteName('a.publish_up') . ' IS NULL',
                                $db->quoteName('a.publish_up') . ' <= :publishUp',
                            ],
                            'OR'
                        )
                        ->extendWhere(
                            'AND',
                            [
                                $db->quoteName('a.publish_down') . ' IS NULL',
                                $db->quoteName('a.publish_down') . ' >= :publishDown',
                            ],
                            'OR'
                        )
                        ->bind([':publishUp', ':publishDown'], $nowDate);
                }

                // Фильтрация по состоянию публикации
                $published = $this->getState('filter.published');
                $archived  = $this->getState('filter.archived');

                if (is_numeric($published)) {
                    $query->whereIn($db->quoteName('a.state'), [(int) $published, (int) $archived]);
                }

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    throw new \Exception(Text::_('COM_ISHOP_ERROR_PRODUCT_NOT_FOUND'), 404);
                }

                // Проверяем состояние, если установлен фильтр
                if ((is_numeric($published) || is_numeric($archived)) &&
                    ($data->state != $published && $data->state != $archived)) {
                    throw new \Exception(Text::_('COM_ISHOP_ERROR_PRODUCT_NOT_FOUND'), 404);
                }

                // Дополнительные атрибуты
                $data->attribs = (new Registry($data->attribs))->toArray();
                foreach ($data->attribs as $key => $element) {
                    if ((int) $element === 0) {
                        unset($data->attribs[$key]);
                    }
                }
                $data->params = $this->getState('params');

                // Конвертируем сериализованные данные в объекты
                $data->category_params = new Registry($data->category_params);
                $data->metadata = new Registry($data->metadata);
                $data->images = json_decode($data->images);
                $data->equipment = json_decode($data->equipment);

                // Устанавливаем полное наименование товара
                $data->fullname = $data->prefix . ' ' . $data->manufacturer_title . ' ' . $data->title;
                // Получаем полный список характеристик товара
                $data->fields = self::getFields($data->id, $data->category_params);
                $group_default = (int) $data->params->get('group_default', 0);
                $group_sizes = (int) $data->params->get('group_sizes', 0);
                $group_other = (int) $data->params->get('group_other', 0);

                // Если установлена основная группа характеристик,
                // ее нужно переставить на первую позицию
                if (isset($data->fields[$group_default])) {
                    $tmp_group = $data->fields[$group_default];
                    unset($data->fields[$group_default]);
                    $data->fields = [$group_default => $tmp_group] + $data->fields;
                    unset($tmp_group);

                    // Также добавим сюда данные по комплектации, стране и гарантии на товар
                    $defaults = ['equipment', 'country', 'warranty'];
                    foreach ($defaults as $def) {
                        if (!isset($data->fields[$group_default]->fields[$def])) {
                            $fieldObj = new \stdClass();
                            $fieldObj->field_title   = Text::_('COM_ISHOP_PRODUCT_' . $def);
                            $fieldObj->field_type    = 1; // список
                            $fieldObj->field_images  = '{}';
                            $fieldObj->field_icon    = $def;
                            $fieldObj->field_color   = '';
                            $fieldObj->field_unit    = '';
                            $fieldObj->field_value   = $data->$def;
                            $fieldObj->field_value_images  = '{}';
                            $fieldObj->field_value_icon    = '';
                            $fieldObj->field_value_hint    = '';

                            if ($def === 'equipment') {
                                $fieldObj->field_value = '';
                                foreach ($data->$def as $item) {
                                    $fieldObj->field_value .= $item->item . ' ' . $item->count . ' ' . $item->unit . "<br>";
                                }
                            }

                            $data->fields[$group_default]->fields[$def] = $fieldObj;
                        }

                    }
                    unset($defaults);
                }

                // Если установлена группа характеристик "Прочее",
                // ее нужно переставить на последнюю позицию
                if (isset($data->fields[$group_other])) {
                    $tmp_group = $data->fields[$group_other];
                    unset($data->fields[$group_other]);
                    $data->fields[$group_other] = $tmp_group;
                    unset($tmp_group);
                }

                // Если установлена группа характеристик "Габариты",
                // ее нужно переставить на самую последнюю позицию
                if (isset($data->fields[$group_sizes])) {
                    $tmp_group = $data->fields[$group_sizes];
                    unset($data->fields[$group_sizes]);
                    $data->fields[$group_sizes] = $tmp_group;
                    unset($tmp_group);

                    // Также добавим сюда данные по весу и размерам
                    $sizes = ['width', 'height', 'depth', 'weight', 'width_pkg', 'height_pkg', 'depth_pkg', 'weight_pkg'];
                    foreach ($sizes as $size) {
                        if (!isset($data->fields[$group_sizes]->fields[$size])) {
                            $fieldObj = new \stdClass();
                            $fieldObj->field_title   = Text::_('COM_ISHOP_PRODUCT_' . $size);
                            $fieldObj->field_type    = 0; // число
                            $fieldObj->field_images  = '{}';
                            $fieldObj->field_icon    = $size;
                            $fieldObj->field_color   = '';
                            $fieldObj->field_unit    = ($size == 'weight' || $size == 'weight_pkg') ? Text::_('COM_ISHOP_KG') : Text::_('COM_ISHOP_SM');
                            $fieldObj->field_value   = (float) $data->$size;
                            $fieldObj->field_value_images  = '{}';
                            $fieldObj->field_value_icon    = '';
                            $fieldObj->field_value_hint    = '';
                            $data->fields[$group_sizes]->fields[$size] = $fieldObj;
                        }
                    }

                    unset($sizes);
                }

                // Разрешения на доступ к чтению
                if ($access = $this->getState('filter.access')) {
                    $data->params->set('access-view', true);
                } else {
                    // Если фильтр доступа не установлен, макет берет на себя часть ответственности за отображение ограниченной информации
                    $user   = $this->getCurrentUser();
                    $groups = $user->getAuthorisedViewLevels();

                    if ($data->catid == 0 || $data->category_access === null) {
                        $data->params->set('access-view', in_array($data->access, $groups));
                    } else {
                        $data->params->set(
                            'access-view',
                            in_array($data->access, $groups) && in_array($data->category_access, $groups)
                        );
                    }
                }

                // Устанавливаем нахождение товара
                // в списках пользователя
                $this->setInLists($data);

                // Устанавливаем данные текущей зоны доставки
                $this->setDeliveryZone($data);

                // Устанавливаем доступность товара для заказа
                $this->setAvailableState($data);

                // Устанавливаем параметры оплаты частями
                $this->setPaymentsPart($data);

                // Устанавливаем параметры скидок
                $this->setDiscounts($data);

                $this->_item[$pk] = $data;

            } catch (\Exception $e) {
                if ($e->getCode() == 404) {
                    // Необходимо пройти через обработчик ошибок, чтобы Redirect заработал
                    throw $e;
                }
                $this->_item[$pk] = false;
            }
        }

        return $this->_item[$pk];
    }

    /**
     * Получаем характеристики товара
     *
     * @param   int|null  $pk  Идентификатор товара
     * @param   object|null  $params  Параметры категории
     *
     * @return array массив характеристик
     * @throws \Exception
     * @since 1.0.0
     */
    public function getFields(int $pk = null, object $params = null): array
    {
        if (!$pk) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select([
                $db->quoteName('field.id', 'field_id'),
                $db->quoteName('field.title', 'field_title'),
                $db->quoteName('field.type', 'field_type'),
                $db->quoteName('field.images', 'field_images'),
                $db->quoteName('field.icon', 'field_icon'),
                $db->quoteName('field.color', 'field_color'),
                $db->quoteName('field.unit', 'field_unit'),
                // Значение характеристик
                'CASE ' .
                    // Для типа number конвертируем в строку
                    'WHEN ' . $db->quoteName('field.type') . ' = 0 THEN CAST(' . $db->quoteName('a.value') . ' AS CHAR)' .
                    // Для типа list берем значение из ishop_values
                    'WHEN ' . $db->quoteName('field.type') . ' = 1 THEN ' . $db->quoteName('value.value') .
                    // Для типа bool преобразуем в Да/Нет
                    'WHEN ' . $db->quoteName('field.type') . ' = 2 THEN IF(' .  $db->quoteName('a.value') . " > 0, 'y', 'n')" .
                ' END AS ' . $db->quoteName('field_value'),
                // Изображения характеристик
                'CASE ' .
                // Для типа list берем изображение из ishop_values
                ' WHEN ' . $db->quoteName('field.type') . ' = 1 THEN ' . $db->quoteName('value.images') .
                // Для других типов изображение не требуется
                ' ELSE '  . $db->quote('{}') .
                ' END AS ' . $db->quoteName('field_value_images'),
                // Иконки характеристик
                'CASE ' .
                // Для типа list берем иконку из ishop_values
                ' WHEN ' . $db->quoteName('field.type') . ' = 1 THEN ' . $db->quoteName('value.icon') .
                // Для других типов иконка не требуется
                ' ELSE '  . $db->quote('') .
                ' END AS ' . $db->quoteName('field_value_icon'),
                $db->quoteName('a.hint', 'field_value_hint'),
            ])
            ->from($db->quoteName('#__ishop_fields_map', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__ishop_fields', 'field'),
                $db->quoteName('field.id') . ' = ' . $db->quoteName('a.field_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_values', 'value'),
                $db->quoteName('value.id') . ' = ' . $db->quoteName('a.value') .
                ' AND ' . $db->quoteName('field.type') . ' = 1'
            )
            ->where($db->quoteName('a.product_id') . ' = ' . $pk)
            ->order($db->quoteName('field.ordering') . ', ' . $db->quoteName('value.ordering'));

        $db->setQuery($query);
        $fields =  $db->loadObjectList('field_id');

        // Если не установлены параметры категории,
        // просто вернем все характеристики в основной группе
        if (!$params) {
            $params = ComponentHelper::getParams('com_ishop');
            $group_default_id = (int) $params->get('group_default', 0);
            $query
                ->clear()
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('images'),
                    $db->quoteName('icon'),
                ])
                ->from($db->quoteName('#__ishop_groups'))
                ->where($db->quoteName('id') . ' = ' . $group_default_id);
            $db->setQuery($query);
            $group =  $db->loadObject();

            if ($group) {
                $group->fields = $fields;

                return [
                    'root' => $group,
                ];
            }

            $group = new \stdClass();
            $group->title = Text::_('COM_ISHOP_PRODUCT_FIELDS');
            $group->fields = $fields;

            return [
                'root' => $group,
            ];
        }

        // Если параметры категории установлены,
        // собираем массив групп характеристик
        $groups = $params->toArray();
        $groups = $groups['fields_groups'];
        $ids = array_column($groups, 'group');
        $groups = array_combine($ids, array_column($groups, 'field'));
        $fields_ids = array_column($fields, 'field_id');

        // Получаем список групп для данной категории
        $query
            ->clear()
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('images'),
                $db->quoteName('icon'),
            ])
            ->from($db->quoteName('#__ishop_groups'))
            ->whereIn($db->quoteName('id'), $ids)
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $group_list =  $db->loadObjectList('id');
        unset($ids);

        // Собираем все характеристики, группируя
        // в соответствии с настройками категории товара
        foreach ($group_list as $groupId => $group) {
            $result = array_intersect($groups[$groupId], $fields_ids);
            // По умолчанию список характеристик пуст
            $group->fields = [];

            // если для данной группы не заданы
            // характеристики, пропускаем ее
            if (empty($result)) {
                continue;
            }

            // Проходим по каждой характеристике
            foreach ($result as $id) {
                $group->fields[$id] = $fields[$id];
            }
        }

        return $group_list;
    }

    /**
     * Увеличивает счетчик просмотров
     *
     * @param   int|null  $pk  Идентификатор записи, необязательно
     *
     * @return bool True если успешно
     * @throws \Exception
     * @since 1.0.0
     */    
    public function hit(int $pk = null)
    {
        $input = Factory::getApplication()->getInput();
        $hitcount = $input->getInt('hitcount', 1);

        if ($hitcount) {
            $pk = (int) ($pk ?: $this->getState('product.id'));

            $table = $this->getTable();
            $table->hit($pk);
        }

        return true;
    }

    /**
     * Устанавливает нахождение товара:
     * в корзине пользователя,
     * в избранном,
     * в сравнении
     *
     * @param   object  $data  Данные товара
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    private function setInLists(object $data)
    {
        // Текущая корзина пользователя
        $cart = $this->getMVCFactory()->createModel('Cart', 'Site')->getCartList();
        // Текущий список избранного пользователя
        $wishlist = $this->getMVCFactory()->createModel('Wishlist', 'Site')->getWishlistList();
        // Текущий список сравнения пользователя
        $compare = $this->getMVCFactory()->createModel('Compare', 'Site')->getCompareList();

        // Проверим, находится ли товар в корзине
        $data->incart = in_array($data->id, array_keys($cart));
        if ($data->incart) {
            $data->incart_count = $cart[$data->id];
        }

        // Проверим, находится ли товар в избранном
        $data->inwishlist = in_array($data->id, $wishlist);

        // Проверим, находится ли товар в сравнении
        $data->incompare = in_array($data->id, $compare);

        unset($cart);
        unset($wishlist);
        unset($compare);
    }

    /**
     * Устанавливает данные текущей зоны доставки
     *
     * @param   object  $data  Данные товара
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    private function setDeliveryZone(object $data)
    {
        // Текущая зона доставки
        $zonesModule = $this->getMVCFactory()->createModel('Zones', 'Site');
        $active_zone = $zonesModule->getActive();
        $data->active_zone = $zonesModule->getZone();

        // Текущая дата
        $today = new DateTime();
        // Завтра
        $tomorrow = (clone $today)->modify('+1 day');
        // Послезавтра
        $day_after = (clone $today)->modify('+2 day');
        $data->delivery = json_decode($data->delivery, true);
        if (!empty($data->delivery[$active_zone])) {
            try {
                $date = new DateTime($data->delivery[$active_zone]);

                if ($date->format('Y-m-d') == $today->format('Y-m-d')) {
                    $data->delivery = Text::_('DATE_FORMAT_TODAY');
                } elseif ($date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
                    $data->delivery = Text::_('DATE_FORMAT_TOMORROW');
                } elseif ($date->format('Y-m-d') == $day_after->format('Y-m-d')) {
                    $data->delivery = Text::_('DATE_FORMAT_DAY_AFTER');
                } elseif ($date < $today) {
                    $data->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
                } else {
                    // Любая другая будущая дата
                    $data->delivery = HTMLHelper::_('date', $date->format('Y-m-d'), Text::_('DATE_FORMAT_FUTURE'));
                }
            } catch (\Exception $e) {
                // Обработка невалидных дат
                $data->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
            }
        } else {
            $data->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
        }
    }

    /**
     * Устанавливает доступность товара дял заказа
     *
     * @param   object  $data  Данные товара
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    private function setAvailableState(object $data)
    {
        // Доступность товара для заказа
        if ($data->stock > 0 || $data->stock === -1 ) {
            $data->available = true;
        } else {
            $data->available = false;
        }
    }

    /**
     * Устанавливает параметры оплаты частями
     *
     * @param   object  $data  Данные товара
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    private function setPaymentsPart(object $data)
    {
        $params = ComponentHelper::getParams('com_ishop');

        // Если нужно показывать расчет оплаты частями
        $parts = false;
        if ($params->get('payments_use', 0) && $params->get('parts_use', 0)) {
            $parts = $this->getMVCFactory()->createModel('Parts', 'Site')->getItems();

            foreach ($parts as $part) {
                if (!$part->attribs['prod_label_show']) {
                    unset($part);
                }
            }
        }

        // Проверим, какие варианты оплаты подходят для текущего товара
        $data->parts = [];

        if (!empty($parts)) {
            foreach ($parts as $part) {
                // Изначально проверяем,
                // что данная оплата частями применима
                // для текущего товара

                // Если категории заданы
                if (!empty($part->attribs['cats']) && !in_array($data->catid, $part->attribs['cats'])) {
                    continue;
                }

                // Если производители заданы
                if (!empty($part->attribs['manufacturers']) && !in_array($data->manufacturer_id, $part->attribs['manufacturers'])) {
                    continue;
                }

                // Определяем, какая цена используется для расчета оплаты
                $partPrice = PriceHelper::getPartPrice($data, $part->attribs['price_mode'] ?? 1);

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
                $current->prod_label = $part->attribs['prod_label'];
                $current->prod_label_param = $part->attribs['prod_label_param'];
                $current->first_part = $part->attribs['first_part'];
                $current->min_payment = min(array_column($rules, 'monthly_payment'));
                $current->min_rate = min(array_column($part->attribs['parts_rules'], 'percent'));
                $current->max_period = max(array_column($part->attribs['parts_rules'], 'period'));
                $current->rules = $rules;
                unset($rules);

                $data->parts[] = $current;
            }
        }
    }

    /**
     * Устанавливает параметры скидок
     *
     * @param   object  $data  Данные товара
     *
     * @return void
     * @throws \Exception
     * @since 1.0.0
     */
    private function setDiscounts(object $data)
    {
        $params = ComponentHelper::getParams('com_ishop');

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

        // По-умолчанию размер скидки в процентах равен 0
        $data->discount_size = 0;

        // Если применение скидок отключено - завершаем обработку товара
        if (!$canUseDiscounts) {
            return;
        }

        // Если применение скидок включено
        // Проверим, используются ли предустановленные скидки
        if ($canUseManualDiscounts) {
            // Если для товара были заданы старая цена и цена со скидкой,
            // рассчитаем размер скидки в процентах
            if ($data->old_price > 0 && $data->sale_price > 0) {
                $data->discount_size = round(100 - ($data->sale_price / $data->old_price * 100));
            }
        }

        // Проверим, используются ли автоматические скидки
        // Автоматические скидки применяются, если на товар не действует предустановленные скидки
        // Однако у товара должна быть установлена старая цена
        if ($canUseAutoDiscounts && ($data->discount_size === 0) && $data->old_price > 0) {
            if (!$min_percent && !$min_value) {
                return;
            }

            switch ($AutoDiscountsMode) {
                // 1 - От цены закупки
                case 1:
                    // Для расчета от цены закупки у товара должны быть заданы:
                    // - цена закупки товара cost_price
                    // - основная цена товара price
                    if ($data->cost_price > 0 && $data->price > 0) {
                        $current_percent = round(100 - ($data->cost_price / $data->price * 100));
                        $current_value   = $data->price - $data->cost_price;

                        if ($min_percent > 0 && $min_percent <= $current_percent) {
                            $data->discount_size = round(100 - ($data->price / $data->old_price * 100));
                            break;
                        }

                        if ($min_value > 0 && $min_value <= $current_value) {
                            $data->discount_size = round(100 - ($data->price / $data->old_price * 100));
                        }
                    }

                    break;

                // 2 - От старой цены
                case 2:
                    // Для расчета от старой цены у товара должны быть заданы:
                    // - старая цена товара old_price
                    // - основная цена товара price
                    if ($data->price > 0) {
                        $current_percent = round(100 - ($data->price / $data->old_price * 100));
                        $current_value   = $data->old_price - $data->price;

                        if ($min_percent > 0 && $min_percent <= $current_percent) {
                            $data->discount_size = $current_percent;
                            break;
                        }

                        if ($min_value > 0 && $min_value <= $current_value) {
                            $data->discount_size = $current_percent;
                        }
                    }

                    break;

                // 3 - От основной цены
                case 3:
                    // Для расчета от основной цены у товара должны быть заданы:
                    // - основная цена товара price
                    // - цена товара со скидкой sale_price
                    if ($data->price > 0 && $data->sale_price > 0) {
                        $current_percent = round(100 - ($data->sale_price / $data->price * 100));
                        $current_value   = $data->price - $data->sale_price;

                        if ($min_percent > 0 && $min_percent <= $current_percent) {
                            $data->discount_size = round(100 - ($data->price / $data->old_price * 100));
                            break;
                        }

                        if ($min_value > 0 && $min_value <= $current_value) {
                            $data->discount_size = round(100 - ($data->price / $data->old_price * 100));
                        }
                    }

                    break;
            }
        }
    }
}
