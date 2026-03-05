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
 * Класс контроллера для производителя
 * @since 1.0.0
 */
class ManufacturerController extends FormController
{
    /**
     * Метод отмены редактирования
     *
     * @param   string  $key  Имя первичного ключа
     *
     * @return  bool
     *
     * @since   1.0.0
     */
    public function cancel($key = null)
    {
        $result = parent::cancel($key);

        // При редактировании в модальном окне происходит перенаправление на модальную верстку
        if ($result && $this->input->get('layout') === 'modal') {
            $id     = $this->input->get('id');
            $return =
                'index.php?option=' . $this->option .
                '&view=' . $this->view_item .
                $this->getRedirectToItemAppend($id) .
                '&layout=modalreturn&from-task=cancel';

            $this->setRedirect(Route::_($return, false));
        }

        return $result;
    }

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
        $model = $this->getModel('Manufacturer', 'Administrator', []);
        $this->setRedirect(Route::_('index.php?option=com_ishop&view=manufacturers' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }
}