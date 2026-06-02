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
     * Устанавливает флаг доступности товара для заказа.
     *
     * @param   object  $data  Данные товара
     *
     * @return  void
     * @since   1.0.0
     */
    public static function setAvailableState(object $data): void
    {
        $data->available = self::isAvailableForOrder($data->stock ?? 0);
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
