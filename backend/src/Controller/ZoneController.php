<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Класс контроллера для зоны доставки
 * @since 1.0.0
 */
class ZoneController extends FormController
{
    /**
     * Метод выполнения пакетных операций
     *
     * @param   object  $model  Модель
     *
     * @return  bool   True в случае успеха, false в противном случае
     * @throws \Exception
     * @since 1.0.0
     */
    public function batch($model = null)
    {
        $this->checkToken();
        $model = $this->getModel('Zone', 'Administrator', []);
        $this->setRedirect(Route::_('index.php?option=com_ishop&view=zones' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }
}