<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

use DateTime;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use stdClass;

defined('_JEXEC') or die;

/**
 * Helper товара com_ishop
 * @since 1.0.0
 */
class ProductHelper
{
    /**
     * Проверяет, доступен ли товар для заказа.
     *
     * @param   int|string|null  $stock  Значение остатка товара
     *
     * @return  bool
     * @since   1.0.0
     */
    public static function isAvailableForOrder(int|string|null $stock): bool
    {
        $stock = (int) $stock;

        return $stock === -1 || $stock > 0;
    }

    /**
     * Устанавливает флаг доступности товара для заказа с учетом текущей зоны доставки.
     *
     * Проверка идет от наиболее точных источников наличия к общему остатку товара:
     * сначала учитываются склады, обслуживающие активную зону доставки, затем поставщики,
     * обслуживающие эту зону, и только после этого используется общий остаток в поле stock.
     *
     * @param   object  $data  Данные товара
     *
     * @return  void
     * @throws \Exception
     * @since   1.0.0
     */
    public static function setAvailableState(object $data): void
    {
        $data->available = false;

        $productId = (int) ($data->id ?? 0);
        $zoneId    = (int) ($data->active_zone->id ?? 0);

        // Без идентификатора товара нельзя проверить специализированные таблицы остатков.
        // В этом сценарии сохраняем прежнее поведение: доступность определяется общим stock.
        if ($productId <= 0 || $zoneId <= 0) {
            $data->available = self::isAvailableForOrder($data->stock ?? 0);

            return;
        }

        $deliveryRules = self::getZoneDeliveryRules($zoneId);

        if (!$deliveryRules['loaded']) {
            $data->available = self::isAvailableForOrder($data->stock ?? 0);

            return;
        }

        // Пустой список складов в настройках зоны означает, что зона обслуживается любым складом.
        if (self::hasWarehouseStock($productId, $deliveryRules['warehouses'])) {
            $data->available = true;

            return;
        }

        // Пустой список поставщиков в настройках зоны означает, что зона обслуживается любым поставщиком.
        if (self::hasSupplierStock($productId, $deliveryRules['suppliers'])) {
            $data->available = true;

            return;
        }

        $data->available = self::isAvailableForOrder($data->stock ?? 0);
    }

    /**
     * Формирует HTML-кнопку добавления товара в корзину с учетом режима управления количеством.
     *
     * @param   object  $product  Данные товара
     * @param   object  $params   Параметры компонента
     * @param   string  $class    CSS-классы кнопки
     *
     * @return  string HTML кнопки корзины
     * @since   1.0.0
     */
    public static function renderCartButton(object $product, object $params, string $class = 'btn w-100'): string
    {
        $productId = (int) ($product->id ?? 0);

        if ($productId <= 0) {
            return '';
        }

        $isSimple     = (bool) $params->get('cart_button_simple', true);
        $isInCart     = !empty($product->incart);
        $quantity     = max(1, (int) ($product->incart_count ?? 1));
        $title        = htmlspecialchars(Text::_('COM_ISHOP_ADD_TO_CART'), ENT_QUOTES, 'UTF-8');
        $delivery     = htmlspecialchars((string) ($product->delivery ?? Text::_('COM_ISHOP_ADD_TO_CART')), ENT_QUOTES, 'UTF-8');
        $buttonClass  = trim($class . ($isInCart ? ' active' : ''));
        $buttonSimple = $isSimple ? 'true' : 'false';
        $innerHtml    = '<svg class="svg"><use href="/icons_v3.svg#cart"/></svg><span>' . $delivery . '</span>';

        if ($isSimple || !$isInCart) {
            return '<button class="' . htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') . '"'
                . ' title="' . $title . '"'
                . ' data-tocart="' . $productId . '"'
                . ' data-tocart-simple="' . $buttonSimple . '">'
                . $innerHtml
                . '</button>';
        }

        $buttonClass = trim($class . ' btn-control active');

        return '<button class="' . htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') . '"'
            . ' title="' . $title . '"'
            . ' data-tocart="' . $productId . '"'
            . ' data-tocart-simple="false"'
            . ' data-original-html="' . htmlspecialchars($innerHtml, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="btn_decrease">-</span>'
            . '<span class="btn_quantity">' . $quantity . '</span>'
            . '<span class="btn_increase">+</span>'
            . '</button>';
    }

    /**
     * Возвращает настройки складов и поставщиков, обслуживающих зону доставки.
     *
     * Данные зоны кэшируются на время текущего запроса, потому что setAvailableState()
     * вызывается для каждого товара в списках, корзине и карточке товара. Это позволяет
     * не загружать одну и ту же запись зоны повторно для каждого товара.
     *
     * @param   int  $zoneId  ID зоны доставки
     *
     * @return  array{loaded: bool, warehouses: array<int, int>, suppliers: array<int, int>}
     * @throws \Exception
     * @since   1.0.0
     */
    private static function getZoneDeliveryRules(int $zoneId): array
    {
        static $cache = [];

        if (isset($cache[$zoneId])) {
            return $cache[$zoneId];
        }

        $cache[$zoneId] = [
            'loaded'     => false,
            'warehouses' => [],
            'suppliers'  => [],
        ];

        $model = Factory::getApplication()
            ->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createModel('Zone', 'Administrator');

        $zone = $model->getItem($zoneId);

        if (!$zone) {
            return $cache[$zoneId];
        }

        $cache[$zoneId]['loaded'] = true;

        if (empty($zone->attribs) || !is_array($zone->attribs)) {
            return $cache[$zoneId];
        }

        $cache[$zoneId]['warehouses'] = self::normalizeDeliveryRows(
            $zone->attribs['warehouses_delivery'] ?? [],
            'warehouse'
        );
        $cache[$zoneId]['suppliers'] = self::normalizeDeliveryRows(
            $zone->attribs['suppliers_delivery'] ?? [],
            'supplier'
        );

        return $cache[$zoneId];
    }

    /**
     * Преобразует строки repeatable-subform зоны доставки в массив ID => days.
     *
     * В настройках зоны repeatable-subform хранит набор строк с ID склада/поставщика
     * и количеством дней доставки. Для проверки наличия важны только реальные ID:
     * значения 0/null/пустые строки отбрасываются, чтобы пустая настройка зоны означала
     * "без ограничения по складам/поставщикам".
     *
     * @param   array   $rows   Строки subform из attribs зоны
     * @param   string  $idKey  Имя поля с ID: warehouse или supplier
     *
     * @return  array<int, int> Массив ID => days
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

            $result[$id] = (int) ($row['days'] ?? 0);
        }

        return $result;
    }

    /**
     * Проверяет наличие товара на складах, обслуживающих текущую зону доставки.
     *
     * Если список складов пустой, ограничение по складам не добавляется: это соответствует
     * правилу, что зона доставки обслуживается любым складом.
     *
     * @param   int         $productId     ID товара
     * @param   array<int>  $warehouseIds  ID складов, обслуживающих зону
     *
     * @return  bool
     * @since   1.0.0
     */
    private static function hasWarehouseStock(int $productId, array $warehouseIds): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__ishop_warehouses_stock'))
            ->where($db->quoteName('product_id') . ' = :product_id')
            ->where($db->quoteName('stock') . ' > 0')
            ->bind(':product_id', $productId, ParameterType::INTEGER);

        if (!empty($warehouseIds)) {
            $warehouseIds = array_keys($warehouseIds);
            $query->whereIn($db->quoteName('warehouse_id'), $warehouseIds, ParameterType::INTEGER);
        }

        $db->setQuery($query, 0, 1);

        return (bool) $db->loadResult();
    }

    /**
     * Проверяет наличие товара у поставщиков, обслуживающих текущую зону доставки.
     *
     * Если список поставщиков пустой, ограничение по поставщикам не добавляется: это
     * соответствует правилу, что зона доставки обслуживается любым поставщиком.
     *
     * @param   int         $productId    ID товара
     * @param   array<int>  $supplierIds  ID поставщиков, обслуживающих зону
     *
     * @return  bool
     * @since   1.0.0
     */
    private static function hasSupplierStock(int $productId, array $supplierIds): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__ishop_suppliers_stock'))
            ->where($db->quoteName('product_id') . ' = :product_id')
            ->where($db->quoteName('stock') . ' > 0')
            ->bind(':product_id', $productId, ParameterType::INTEGER);

        if (!empty($supplierIds)) {
            $supplierIds = array_keys($supplierIds);
            $query->whereIn($db->quoteName('supplier_id'), $supplierIds, ParameterType::INTEGER);
        }

        $db->setQuery($query, 0, 1);

        return (bool) $db->loadResult();
    }

    /**
     * Устанавливает данные текущей зоны доставки и форматирует срок доставки.
     *
     * @param   object  $data  Данные товара
     *
     * @return  void
     * @throws \DateMalformedStringException
     * @throws \Exception
     * @since   1.0.0
     */
    public static function setDeliveryZone(object $data): void
    {
        $zonesModule = Factory::getApplication()->bootComponent('com_ishop')->getMVCFactory()->createModel('Zones', 'Site');
        $activeZone = $zonesModule->getActive();
        $data->active_zone = $zonesModule->getZone();

        $today = new DateTime();
        $tomorrow = (clone $today)->modify('+1 day');
        $dayAfter = (clone $today)->modify('+2 day');

        $data->delivery = json_decode($data->delivery, true);
        $data->delivery_date = '';

        if (!empty($data->delivery[$activeZone])) {
            $data->delivery_date = $data->delivery[$activeZone];

            try {
                $date = new DateTime($data->delivery[$activeZone]);

                if ($date->format('Y-m-d') == $today->format('Y-m-d')) {
                    $data->delivery = Text::_('DATE_FORMAT_TODAY');
                } elseif ($date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
                    $data->delivery = Text::_('DATE_FORMAT_TOMORROW');
                } elseif ($date->format('Y-m-d') == $dayAfter->format('Y-m-d')) {
                    $data->delivery = Text::_('DATE_FORMAT_DAY_AFTER');
                } elseif ($date < $today) {
                    $data->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
                } else {
                    // Любая другая будущая дата
                    $data->delivery = HTMLHelper::_('date', $date->format('Y-m-d'), Text::_('DATE_FORMAT_FUTURE'));
                }
            } catch (\Exception) {
                // Обработка невалидных дат
                $data->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
            }
        } else {
            $data->delivery = Text::_('COM_ISHOP_ADD_TO_CART');
        }
    }

    /**
     * Расчет наценки по уровню доступа пользователя
     *
     * @param   object  $data    Объект товара
     * @param   object  $params  Параметры
     *
     * @return  void Меняем исходный объект
     * @throws \Exception
     * @since 1.0.0
     */
    public static function calculateProductMarkup(object $data, object $params) {
        // Уровни доступа текущего пользователя
        $access_levels = Factory::getApplication()->getIdentity()->getAuthorisedViewLevels();
        $markup_params = $params->get('markup_users_params', []);

        if (empty($markup_params)) {
            return;
        }

        $round = $params->get('roundPrice', 0);
        foreach ($markup_params as $params) {
            if ($params->access > 0 && in_array($params->access, $access_levels)) {
                // Основная цена товара
                if ($data->price > 0) {
                    // $params->percent_value всегда в процентах
                    $data->price = round($data->price + ($data->price * $params->percent_value / 100), $round);
                }
                // Старая цена товара (зачеркнутая)
                if ($data->old_price > 0) {
                    // $params->percent_value всегда в процентах
                    $data->old_price = round($data->old_price + ($data->old_price * $params->percent_value / 100), $round);
                }
                // Цена товара со всеми действующими скидками
                if ($data->sale_price > 0) {
                    // $params->percent_value всегда в процентах
                    $data->sale_price = round($data->sale_price + ($data->sale_price * $params->percent_value / 100), $round);
                }

                // Выходим после первой подходящей наценки
                break;
            }
        }
    }

    /**
     * Расчет оплаты товара частями по формуле аннуитетного платежа
     *
     * @param   float $price    Цена товара для расчета
     * @param   int $period     Срок кредитования в месяцах
     * @param   float $first    Размер первоначального взноса, значение в процентах
     * @param   float $rate     Процентная ставка в год, значение в процентах
     *
     * @return  object  объект с данными расчета
     * @since 1.0.0
     */
    public static function calculatePaymentParts(float $price, int $period, float $first = 0, float $rate = 0)
    {
        $params = ComponentHelper::getParams('com_ishop');
        $round = (int) $params->get('defaultCurrency', 0);

        // Объект с результатами расчетов
        $result = new stdClass();
        // Если требуется первоначальный взнос, рассчитаем тело
        if ($first > 0) {
            $first = $price * $first / 100;
            $price = $price - $first;
        }

        if ($rate > 0) {
            // Значение ставки процента в месяц
            $monthlyRate = $rate / 12 / 100;
            // Нумератор
            $numerator = $monthlyRate * pow(1 + $monthlyRate, $period);
            // Деноминатор
            $denominator = pow(1 + $monthlyRate, $period) - 1;
            // Размер платежа в месяц
            $result->monthly_payment = round($price * ($numerator / $denominator), $round);
            // Общая сумма при оплате частями
            $result->total_payment = round($first + ($result->monthly_payment * $period), $round);
            // Сумма переплаты
            $result->over_payment = round($result->total_payment - $price - $first, $round);
        } else {
            // Размер платежа в месяц
            $result->monthly_payment = round($price / $period, $round);
            // Общая сумма при оплате частями
            $result->total_payment = round($first + $price, $round);
            // Сумма переплаты
            $result->over_payment = 0;
        }

        $result->rate = $rate;

        return $result;
    }
}
