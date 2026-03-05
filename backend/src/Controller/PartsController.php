<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Класс контроллера списка оплат частями
 * @since 1.0.0
 */
class PartsController extends AdminController
{
    /**
     * Прокси метод для метода getModel
     *
     * @param   string  $name    Имя модели, необязательно
     * @param   string  $prefix  Префикс класса, необязательно
     * @param   array   $config  Массив параметров, необязательно
     *
     * @return object Возвращает модель
     * @since 1.0.0
     */
    public function getModel($name = 'Part', $prefix = 'Administrator', $config = ['ignore_request' => true]): object
    {
        return parent::getModel($name, $prefix, $config);
    }
}