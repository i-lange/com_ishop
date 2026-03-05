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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

/**
 * Helper компонента com_ishop для форматирования данных
 * @since 1.0.0
 */
class FormatHelper
{
    /**
     * Подготовка float значения к выводу
     *
     * @param   float  $number непосредственно число
     * @param   int  $decimal число знаков после запятой
     * @param   string  $decimal_separator разделитель дробной части
     * @param   string  $thousands_separator разделитель тысяч
     *
     * @return  string  отформатированная строка
     * @since 1.0.0
     */
    public static function renderFloat(float $number, int $decimal = 2, string $decimal_separator = ',', string $thousands_separator = ' ')
    {
        $s = number_format($number, $decimal, $decimal_separator, $thousands_separator);
        $s = rtrim($s, '0');
        return rtrim($s, $decimal_separator);
    }
}