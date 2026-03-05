<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use stdClass;

defined('_JEXEC') or die;

/**
 * Helper товара com_ishop
 * @since 1.0.0
 */
class ProductHelper
{
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