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
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
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
        $db = Factory::getContainer()->get('DatabaseDriver');

        $zones = self::getDeliveryZones($db);
        if (empty($zones)) {
            return false;
        }

        $products = self::getPublishedProducts($db);
        if (!$products) {
            return false;
        }

        $productIds       = array_column($products, 'id');
        $warehouseStock   = self::getStockMap($db, '#__ishop_warehouses_stock', 'warehouse_id', $productIds);
        $supplierStock    = self::getStockMap($db, '#__ishop_suppliers_stock', 'supplier_id', $productIds);
        $now              = Factory::getDate();
        $currentMinutes   = self::timeToMinutes($now->format('H:i'));
        $calculationToday = new DateTime($now->format('Y-m-d'));

        foreach ($products as $product) {
            $productId = (int) $product->id;
            $dateList  = [];

            // Для каждого товара нужно пройти по каждой зоне доставки
            foreach ($zones as $zone) {
                $sourceDays = self::getProductDeliveryDays(
                    $product,
                    $zone,
                    $warehouseStock[$productId] ?? [],
                    $supplierStock[$productId] ?? []
                );

                // Если товар не обеспечен ни складом, ни текущим поставщиком, ни общим stock,
                // для этой зоны не записываем дату доставки. Фронтенд увидит отсутствие ключа зоны.
                if ($sourceDays === null) {
                    continue;
                }

                $plusDays = self::getCutoffDelay($zone, $currentMinutes) + $sourceDays;
                $nearDate = self::findNearestDeliveryDate($calculationToday, $plusDays, $zone);

                if ($nearDate !== null) {
                    $dateList[(int) $zone->id] = $nearDate;
                }
            }

            if (self::hasDeliveryChanged($product->delivery, $dateList)) {
                self::updateProductDelivery($db, $productId, $dateList);
            }
        }

        return true;
    }

    /**
     * Возвращает опубликованные зоны доставки, готовые к расчету дат.
     *
     * Метод заранее декодирует JSON-поля, нормализует repeatable-subform настройки,
     * рассчитывает текущую загруженность и отбрасывает зоны с пустым или битым календарем.
     * Это защищает планировщик от бесконечного поиска даты доставки.
     *
     * @param   object  $db  Объект базы данных Joomla
     *
     * @return  array<int, object>
     * @throws  \Exception
     * @since   1.0.0
     */
    private static function getDeliveryZones(object $db): array
    {
        $published = 1;
        $query = $db->getQuery(true)
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

        if (!$zones) {
            throw new \Exception('Не удалось получить список зон доставки', 404);
        }

        $result = [];

        foreach ($zones as $zone) {
            $zone->id      = (int) $zone->id;
            $zone->attribs = (new Registry($zone->attribs))->toArray();
            $zone->current = (new Registry($zone->current))->toArray();

            $zone->warehouses_delivery = self::normalizeDeliveryRows(
                $zone->attribs['warehouses_delivery'] ?? [],
                'warehouse'
            );
            $zone->suppliers_delivery = self::normalizeDeliveryRows(
                $zone->attribs['suppliers_delivery'] ?? [],
                'supplier'
            );
            $zone->warehouses_default_days = max(0, (int) ($zone->attribs['warehouses_default_days'] ?? 0));
            $zone->suppliers_default_days  = max(0, (int) ($zone->attribs['suppliers_default_days'] ?? 0));
            $zone->default_orders          = max(1, (int) ($zone->attribs['default_orders'] ?? 1));
            $zone->exec_time_minutes       = self::timeToMinutes($zone->attribs['exec-time'] ?? '');
            $zone->calendar                = self::normalizeCalendar($zone->attribs['calendar'] ?? []);

            if (empty($zone->calendar)) {
                Log::add(
                    'Зона доставки #' . $zone->id . ' пропущена: пустой или некорректный календарь доставки.',
                    Log::ERROR,
                    'com_ishop'
                );

                continue;
            }

            $zone->workload = self::getZoneWorkload($zone->current);
            $result[] = $zone;
        }

        return $result;
    }

    /**
     * Возвращает опубликованные товары, для которых нужно пересчитать сроки доставки.
     *
     * В выборку включается текущий supplier_id, общий stock и существующее поле delivery:
     * supplier_id нужен для расчета поставки от текущего поставщика, stock - для fallback
     * доступности, delivery - чтобы не выполнять UPDATE без фактического изменения данных.
     *
     * @param   object  $db  Объект базы данных Joomla
     *
     * @return  array<int, object>
     * @throws  \Exception
     * @since   1.0.0
     */
    private static function getPublishedProducts(object $db): array
    {
        $published = 1;
        $query = $db->getQuery(true)
            ->select([
                $db->qn('id'),
                $db->qn('supplier_id'),
                $db->qn('stock'),
                $db->qn('delivery'),
            ])
            ->from($db->quoteName('#__ishop_products'))
            ->where($db->quoteName('state') . ' = :published')
            ->bind(':published', $published, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Загружает карту положительных остатков из таблицы складов или поставщиков.
     *
     * Карта имеет формат product_id => related_id => true, где related_id - это ID склада
     * или поставщика. Данные грузятся чанками, чтобы не делать запрос для каждого товара
     * и одновременно не создавать слишком длинный IN-список для больших каталогов.
     *
     * @param   object        $db            Объект базы данных Joomla
     * @param   string        $table         Имя таблицы остатков
     * @param   string        $relationField Поле склада или поставщика
     * @param   array<int>    $productIds    ID товаров
     *
     * @return  array<int, array<int, bool>>
     * @throws  \Exception
     * @since   1.0.0
     */
    private static function getStockMap(object $db, string $table, string $relationField, array $productIds): array
    {
        $stockMap = [];
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if (empty($productIds)) {
            return $stockMap;
        }

        foreach (array_chunk($productIds, 1000) as $chunk) {
            $query = $db->getQuery(true)
                ->select([
                    $db->qn('product_id'),
                    $db->qn($relationField, 'relation_id'),
                ])
                ->from($db->quoteName($table))
                ->where($db->quoteName('stock') . ' > 0')
                ->whereIn($db->quoteName('product_id'), $chunk, ParameterType::INTEGER)
                ->group([
                    $db->qn('product_id'),
                    $db->qn($relationField),
                ]);

            $db->setQuery($query);
            $rows = $db->loadObjectList();

            foreach ($rows as $row) {
                $productId  = (int) $row->product_id;
                $relationId = (int) $row->relation_id;

                if ($productId <= 0 || $relationId <= 0) {
                    continue;
                }

                $stockMap[$productId][$relationId] = true;
            }
        }

        return $stockMap;
    }

    /**
     * Определяет количество дней доставки товара для конкретной зоны.
     *
     * Приоритет источников соответствует бизнес-логике каталога: сначала склады зоны,
     * затем текущий поставщик товара, затем общий stock товара. Для поставщиков намеренно
     * используется только product.supplier_id, даже если supplier stock содержит остатки
     * у других поставщиков, потому что текущий поставщик связан с закупочной ценой товара.
     *
     * Важно: списки warehouses_delivery и suppliers_delivery задают индивидуальные сроки
     * для конкретных складов/поставщиков, но не запрещают использовать default-сроки.
     * Поэтому если товар есть у текущего поставщика, а этот поставщик не указан в
     * suppliers_delivery зоны, используется suppliers_default_days.
     *
     * @param   object             $product        Товар
     * @param   object             $zone           Нормализованная зона доставки
     * @param   array<int, bool>   $warehouseStock Склады, где есть товар
     * @param   array<int, bool>   $supplierStock  Поставщики, у которых есть товар
     *
     * @return  int|null Количество дней или null, если товар недоступен для зоны
     * @since   1.0.0
     */
    private static function getProductDeliveryDays(
        object $product,
        object $zone,
        array $warehouseStock,
        array $supplierStock
    ): ?int
    {
        // 1. Складской остаток имеет самый высокий приоритет: если зона перечисляет
        // конкретные склады, ищем пересечение между этими складами и складами, где есть товар.
        if (!empty($zone->warehouses_delivery)) {
            $matchedWarehouses = array_intersect_key($zone->warehouses_delivery, $warehouseStock);

            // Если товар есть на нескольких подходящих складах, берем минимальный срок
            // доставки среди этих складов: это ближайшая возможная доставка со склада.
            if (!empty($matchedWarehouses)) {
                return min($matchedWarehouses);
            }
        } elseif (!empty($warehouseStock)) {
            // Если в зоне не перечислены конкретные склады, она обслуживается любым складом.
            // При наличии товара хотя бы на одном складе используем складской срок по умолчанию.
            return $zone->warehouses_default_days;
        }

        $supplierId = (int) ($product->supplier_id ?? 0);
        $hasCurrentSupplierStock = $supplierId > 0 && isset($supplierStock[$supplierId]);

        // 2. Если складского остатка нет, проверяем текущего поставщика товара.
        // Остатки у других поставщиков намеренно игнорируются: supplier_id определяет
        // поставщика, от которого сейчас считается закупочная цена и срок поставки.
        if ($hasCurrentSupplierStock) {
            if (!empty($zone->suppliers_delivery)) {
                // Если для текущего поставщика указан индивидуальный срок, используем его.
                // Если поставщик не указан в списке зоны, зона все равно может обслуживаться
                // этим поставщиком по общему сроку suppliers_default_days.
                return array_key_exists($supplierId, $zone->suppliers_delivery)
                    ? (int) $zone->suppliers_delivery[$supplierId]
                    : $zone->suppliers_default_days;
            }

            // Пустой список suppliers_delivery означает отсутствие индивидуальных сроков
            // и обслуживание любым текущим поставщиком по сроку по умолчанию.
            return $zone->suppliers_default_days;
        }

        // 3. Последний fallback - общий остаток товара в products.stock. Он покрывает
        // сценарии неограниченного количества (-1) и прямого остатка товара без привязки
        // к остаткам склада или поставщикам.
        if (self::isAvailableForOrder($product->stock ?? 0)) {
            return $zone->suppliers_default_days;
        }

        // 4. Если ни один источник не подтвердил доступность товара, дата доставки
        // для этой зоны не рассчитывается и ключ зоны не будет записан в products.delivery.
        return null;
    }

    /**
     * Проверяет общий остаток товара.
     *
     * @param   int|string|null  $stock  Значение поля products.stock
     *
     * @return  bool
     * @since   1.0.0
     */
    private static function isAvailableForOrder(int|string|null $stock): bool
    {
        $stock = (int) $stock;

        return $stock === -1 || $stock > 0;
    }

    /**
     * Возвращает перенос доставки из-за cutoff-времени зоны.
     *
     * Пустое exec-time означает отсутствие ограничения: заказ не переносится на завтра.
     * Если exec-time заполнен и текущее время больше или равно cutoff, добавляется один день.
     *
     * @param   object  $zone            Нормализованная зона доставки
     * @param   int     $currentMinutes  Текущее время в минутах от начала дня
     *
     * @return  int
     * @since   1.0.0
     */
    private static function getCutoffDelay(object $zone, int $currentMinutes): int
    {
        if ($zone->exec_time_minutes === null) {
            return 0;
        }

        return $currentMinutes >= $zone->exec_time_minutes ? 1 : 0;
    }

    /**
     * Находит ближайшую дату доставки с учетом календаря и лимита заказов зоны.
     *
     * Поиск ограничен одним годом вперед. Если за этот период не найден доступный день,
     * зона считается некорректной для текущего расчета и дата для товара не записывается.
     *
     * @param   DateTime  $baseDate  Дата начала расчета
     * @param   int       $plusDays  Минимальное количество дней доставки
     * @param   object    $zone      Нормализованная зона доставки
     *
     * @return  string|null Дата в формате Y-m-d или null
     * @throws \DateMalformedStringException
     * @since   1.0.0
     */
    private static function findNearestDeliveryDate(DateTime $baseDate, int $plusDays, object $zone): ?string
    {
        $nearDate = clone $baseDate;

        if ($plusDays > 0) {
            $nearDate->modify('+' . $plusDays . ' day');
        }

        for ($attempt = 0; $attempt < 366; $attempt++) {
            $date = $nearDate->format('Y-m-d');

            if (
                in_array((int) $nearDate->format('w'), $zone->calendar, true)
                && (!isset($zone->workload[$date]) || $zone->workload[$date] < $zone->default_orders)
            ) {
                return $date;
            }

            $nearDate->modify('+1 day');
        }

        Log::add(
            'Для зоны доставки #' . $zone->id . ' не найдена доступная дата доставки в пределах 366 дней.',
            Log::ERROR,
            'com_ishop'
        );

        return null;
    }

    /**
     * Преобразует строки repeatable-subform в массив ID => days.
     *
     * Нулевые и пустые ID отбрасываются. Пустой итоговый массив имеет бизнес-смысл
     * "зона обслуживается любым складом/поставщиком" и не является ошибкой.
     *
     * @param   array   $rows   Строки subform
     * @param   string  $idKey  Имя поля с ID: warehouse или supplier
     *
     * @return  array<int, int>
     * @since   1.0.0
     */
    private static function normalizeDeliveryRows(array $rows, string $idKey): array
    {
        $result = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $id  = (int) ($row[$idKey] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $result[$id] = max(0, (int) ($row['days'] ?? 0));
        }

        return $result;
    }

    /**
     * Нормализует календарь доставки зоны.
     *
     * Корректными считаются только значения дней недели 0..6, где 0 - воскресенье.
     *
     * @param   mixed  $calendar  Значение attribs.calendar
     *
     * @return  array<int>
     * @since   1.0.0
     */
    private static function normalizeCalendar(mixed $calendar): array
    {
        if (!is_array($calendar)) {
            return [];
        }

        $days = [];

        foreach ($calendar as $day) {
            if (!is_numeric($day)) {
                continue;
            }

            $day = (int) $day;

            if ($day >= 0 && $day <= 6) {
                $days[$day] = $day;
            }
        }

        return array_values($days);
    }

    /**
     * Рассчитывает загруженность зоны по датам.
     *
     * Переменная $current хранит вручную заданные или синхронизированные ограничения по заказам.
     * Для расчета важны только строки с валидной датой и положительным количеством.
     *
     * @param   array  $current  Данные поля current зоны
     *
     * @return  array<string, int>
     * @since   1.0.0
     */
    private static function getZoneWorkload(array $current): array
    {
        $workload = [];

        foreach ($current as $day) {
            $day   = (array) $day;
            $date  = (string) ($day['date'] ?? '');
            $count = (int) ($day['count'] ?? 0);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $count <= 0) {
                continue;
            }

            $workload[$date] = ($workload[$date] ?? 0) + $count;
        }

        return $workload;
    }

    /**
     * Преобразует время HH:MM в минуты от начала дня.
     *
     * Пустое или некорректное значение возвращает null. Это используется для exec-time,
     * где пустая настройка означает отсутствие cutoff-переноса.
     *
     * @param   mixed  $time  Строка времени
     *
     * @return  int|null
     * @since   1.0.0
     */
    private static function timeToMinutes(mixed $time): ?int
    {
        if (!is_string($time) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return null;
        }

        $hours   = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    /**
     * Проверяет, изменился ли список дат доставки товара.
     *
     * Сравнение идет по массивам, а не по строкам JSON, чтобы не делать UPDATE только
     * из-за другого порядка ключей или старого форматирования Registry.
     *
     * @param   string|null  $currentDelivery  Текущее значение products.delivery
     * @param   array<int, string>  $dateList  Новый список дат по зонам
     *
     * @return  bool
     * @since   1.0.0
     */
    private static function hasDeliveryChanged(?string $currentDelivery, array $dateList): bool
    {
        $current = (new Registry((string) $currentDelivery))->toArray();

        ksort($current);
        ksort($dateList);

        return $current != $dateList;
    }

    /**
     * Обновляет JSON со сроками доставки одного товара.
     *
     * @param   object             $db        Объект базы данных Joomla
     * @param   int                $productId ID товара
     * @param   array<int, string> $dateList  Список дат доставки по зонам
     *
     * @return  void
     * @throws  \Exception
     * @since   1.0.0
     */
    private static function updateProductDelivery(object $db, int $productId, array $dateList): void
    {
        $delivery = (string) new Registry($dateList);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ishop_products'))
            ->where($db->quoteName('id') . ' = :product_id')
            ->bind(':product_id', $productId, ParameterType::INTEGER)
            ->set($db->quoteName('delivery') . ' = :delivery')
            ->bind(':delivery', $delivery, ParameterType::STRING);

        $db->setQuery($query);
        $db->execute();
    }
}
