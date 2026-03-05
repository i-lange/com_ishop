<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die;

/**
 * Helper компонента com_ishop для манипуляций с ценами
 * @since 1.0.0
 */
class UserHelper
{
    /**
     * Генерируем случайный пароль-идентификатор для пользователя
     *
     * @param   int     $length
     *
     * @return  string  строковый пароль-идентификатор
     * @since 1.0.0
     */
    public static function generatePassword(int $length = 16):string
    {
        $salt     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $base     = strlen($salt);

        $random = Crypt::genRandomBytes($length + 1);
        $shift  = ord($random[0]);

        $password = '';
        for ($i = 1; $i <= $length; ++$i) {
            $password .= $salt[($shift + ord($random[$i])) % $base];
            $shift += ord($random[$i]);
        }

        return $password;
    }
}