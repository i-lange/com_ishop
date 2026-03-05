<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Helper;

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Класс helper
 * @since 1.0.0
 */
class DeliveryHelper
{
    /**
     * Пересчитывает ближайшие даты доставки
     * для товаров в каталоге
     *
     * @return  bool
     * @throws \Exception
     * @since 1.0.0
     */
    public static function update()
    {
        // Получим список зон доставки с параметрами расчета
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $published = 1;
        $query
            ->select([
                $db->qn('id'),
                $db->qn('attribs'),
                $db->qn('current'),
            ])
            ->from($db->quoteName('#__ishop_delivery_zones'))
            ->where($db->quoteName('state') . ' = :published')
            ->bind(':published', $published, ParameterType::INTEGER);
        $db->setQuery($query);

        $zones = $db->loadObjectList();
        if (empty($zones)) {
            throw new \Exception('Не удалось получить список зон доставки', 404);
        }

        foreach ($zones as $zone) {
            // Преобразуем сериализованные данные в массивы
            $zone->attribs = (new Registry($zone->attribs))->toArray();
            $zone->current = (new Registry($zone->current))->toArray();

            // Преобразуем данные в удобные для поиска массивы
            $zone->attribs['warehouses_delivery'] = array_column($zone->attribs['warehouses_delivery'], 'days', 'warehouse');
            $zone->attribs['suppliers_delivery'] = array_column($zone->attribs['suppliers_delivery'], 'days', 'supplier');

            // Рассчитаем текущую загруженность по зонам доставки
            $workload = [];
            foreach ($zone->current as $day) {
                $date = $day['date'];

                if (!isset($workload[$date])) {
                    $workload[$date] = 0;
                }
                $workload[$date] += $day['count'];
            }
            $zone->workload = $workload;
        }

        // Получим список активных товаров
        $query
            ->clear()
            ->select([
                $db->qn('id'),
                $db->qn('supplier_id'),
            ])
            ->from($db->quoteName('#__ishop_products'))
            ->where($db->quoteName('state') . ' = :published')
            ->bind(':published', $published, ParameterType::INTEGER);
        $db->setQuery($query);
        unset($published);

        if (!$products = $db->loadObjectList()) {
            return false;
        }
        $products = array_column($products, 'supplier_id', 'id');

        // Проходим по каждому товару
        foreach ($products as $product_id => $supplier_id) {
            $date_list = [];

            // Сразу пытаемся получить список складов
            // с остатками данного товара,
            // чтобы избежать повторных запросов
            $stock = 0;
            $query
                ->clear()
                ->select('DISTINCT ' . $db->qn('warehouse_id'))
                ->from($db->quoteName('#__ishop_warehouses_stock'))
                //->whereIn($db->quoteName('warehouse_id'), array_keys($zone->attribs['warehouses_delivery']), ParameterType::INTEGER)
                ->where($db->quoteName('product_id') . ' = :product_id')
                ->bind(':product_id', $product_id, ParameterType::INTEGER)
                ->where($db->quoteName('stock') . ' > :stock')
                ->bind(':stock', $stock, ParameterType::INTEGER);
            $db->setQuery($query);
            $houses = $db->loadColumn();
            unset($stock);

            // Текущее время Joomla, приводим к числу
            $currentTime = strtotime((new Date('now'))->format('H:i'));

            // Для каждого товара нужно пройти по каждой зоне доставки
            foreach ($zones as $zone) {
                // Изначально доставка доступна день в день
                $plusDays = 0;
                $weGotIt = false;
                $execTime = 0;

                if (isset($zone->attribs['exec-time'])) {
                    $execTime = strtotime($zone->attribs['exec-time']);
                }

                // Проверим текущее время, если оно больше,
                // чем крайни срок принятия заказов сегодня на сегодня -
                // значит переносим срок на завтра
                if ($currentTime >= $execTime) {
                    $plusDays++;
                }

                // Приоритет всегда у доставки со склада,
                // проверим, есть ли товар на одном
                // из обеспечивающих зону доставки складов
                if (!empty($zone->attribs['warehouses_delivery'])) {
                    if (!empty($houses)) {
                        // Ищем пересечение массивов наличия на складах
                        // со списком складов обеспечения для зоны доставки
                        $find_houses = array_intersect_key($zone->attribs['warehouses_delivery'], array_flip($houses));
                        // Сохраняем минимальное число дней
                        // на доставку, если пересечение есть
                        if (!empty($find_houses)) {
                            $plusDays += min($find_houses);
                            $weGotIt = true;
                        }
                    }
                }
                // Если список складов пуст, предполагаем,
                // что зона обеспечивается любым складом
                elseif (!empty($houses)) {
                    // Сохраняем минимальное число дней на доставку
                    $plusDays += $zone->attribs['warehouses_default_days'];
                    $weGotIt = true;
                }

                // Далее по приоритету доставка от поставщика,
                // проверим, есть ли настройки для поставщика данного товара
                if (!$weGotIt && !empty($zone->attribs['suppliers_delivery'])) {
                    if (in_array($supplier_id, array_keys($zone->attribs['suppliers_delivery']))) {
                        // Сохраняем минимальное число дней на доставку
                        $plusDays += $zone->attribs['suppliers_delivery'][$supplier_id];
                    } else {
                        // Сохраняем минимальное число дней на доставку по-умолчанию
                        $plusDays += $zone->attribs['suppliers_default_days'];
                    }
                    $weGotIt = true;
                }
                // Если список поставщиков пуст, предполагаем,
                // что все поставщики имеют срок поставки по-умолчанию
                elseif (!$weGotIt && empty($zone->attribs['suppliers_delivery'])) {
                    // Сохраняем минимальное число дней на доставку
                    $plusDays += $zone->attribs['suppliers_default_days'];
                    $weGotIt = true;
                }

                // Далее по приоритету просто берем значение,
                // заданное для текущей зоны по-умолчанию
                if (!$weGotIt) {
                    $plusDays += $zone->attribs['suppliers_default_days'];
                }

                // Теперь вычисляем, какой день действительно подходит,
                // с учетом выходных и календаря доставок по зоне
                // Текущая дата (сегодня)
                $nearDate = new DateTime();
                if ($plusDays > 0) {
                    // Переходим к следующей дате
                    $nearDate->modify('+' . $plusDays . ' day');
                }

                // Проверяем, что данный день есть в расписании доставок
                $isDayInCalendar = in_array((int) $nearDate->format('w'), $zone->attribs['calendar']);
                // Проверяем, что на данный день не превышено количество доставок
                $date = $nearDate->format('Y-m-d');
                $isDayAvailable = !isset($zone->workload[$date]) || (isset($zone->workload[$date]) && ($zone->workload[$date] < $zone->attribs['default_orders']));

                // Перематываем вперед, пока не попадем на доступную дату доставки
                while (!$isDayInCalendar || !$isDayAvailable) {
                    $nearDate->modify('+1 day');
                    // Проверяем, что данный день есть в расписании доставок
                    $isDayInCalendar = in_array((int) $nearDate->format('w'), $zone->attribs['calendar']);
                    // Проверяем, что на данный день не превышено количество доставок
                    $date = $nearDate->format('Y-m-d');
                    $isDayAvailable = !isset($zone->workload[$date]) || (isset($zone->workload[$date]) && ($zone->workload[$date] < $zone->attribs['default_orders']));
                }

                $date_list[$zone->id] = $nearDate->format('Y-m-d');
            }

            // Теперь сохраняем ближайшие зоны доставки как строку json
            $date_list = (string) new Registry($date_list);

            $query
                ->clear()
                ->update($db->quoteName('#__ishop_products'))
                ->where($db->quoteName('id') . ' = :product_id')
                ->bind(':product_id', $product_id, ParameterType::INTEGER)
                ->set($db->quoteName('delivery') . ' = :data')
                ->bind(':data', $date_list, ParameterType::STRING);
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }
}
