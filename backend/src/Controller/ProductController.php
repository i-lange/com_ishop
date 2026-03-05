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

use Ilange\Component\Ishop\Administrator\Helper\ProductHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;

/**
 * Класс контроллера для товара
 * @since 1.0.0
 */
class ProductController extends FormController
{
    /**
     * Конструктор
     * @param   array                      $config  Необязательный ассоциативный массив параметров конфигурации.
     *                                              Распознаваемые значения ключей включают
     *                                              'name', 'default_task', 'model_path' и 'view_path'
     *                                              (этот список не является исчерпывающим).
     * @param   ?MVCFactoryInterface|null  $factory
     * @param   ?CMSApplication|null       $app
     * @param   ?Input|null                $input
     * @throws \Exception
     * @since 1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('set_images', 'setImages');
    }

    /**
     * Метод привязывает фото товара к карточке
     * @throws \Exception
     * @since   1.0.0
     */
    public function setImages()
    {
        // Идентификатор товара
        $pk = $this->input->get('id', 0, 'int');

        if (!$pk) {
            $this->setMessage(Text::_('COM_ISHOP_SET_IMAGES_NO_PK'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ishop&view=product&layout=edit&id=' . $pk, false));
        }

        if (ProductHelper::setImages($pk)) {
            $this->setMessage(Text::_('COM_ISHOP_SET_IMAGES_SUCCESS'));
        } else {
            $this->setMessage(Text::_('COM_ISHOP_SET_IMAGES_ERROR'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ishop&view=product&layout=edit&id=' . $pk, false));
    }

    /**
     * Метод отмены редактирования
     * @param   string  $key  Имя первичного ключа
     * @return  bool
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
     * Переопределение метода проверки возможности добавления товара
     * @param   array  $data  Массив входных данных
     * @return  bool
     * @since 1.0.0
     */
    protected function allowAdd($data = [])
    {
        $categoryId = ArrayHelper::getValue($data, 'catid', $this->input->getInt('filter_category_id'), 'int');

        if ($categoryId) {
            return $this->app->getIdentity()->authorise('core.create', 'com_ishop.category.' . $categoryId);
        }

        return parent::allowAdd();
    }

    /**
     * Переопределение метода для проверки возможности редактирования товара
     * @param   array   $data  Массив входных данных
     * @param   string  $key   Имя столбца первичного ключа
     * @return  bool
     * @since 1.0.0
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        $recordId = (int)isset($data[$key]) ? $data[$key] : 0;
        $user     = $this->app->getIdentity();

        // Если id=0, вызываем метод родительского контроллера
        if (!$recordId) {
            return parent::allowEdit($data, $key);
        }

        // Проверяем разрешения на редактирование
        if ($user->authorise('core.edit', 'com_ishop.product.' . $recordId)) {
            return true;
        }

        // Проверяем разрешения на редактирование собственных
        if ($user->authorise('core.edit.own', 'com_ishop.product.' . $recordId)) {
            $record = $this->getModel()->getItem($recordId);

            if (empty($record)) {
                return false;
            }

            return $user->id == $record->created_by;
        }

        return false;
    }

    /**
     * Метод выполнения пакетных операций
     * @param   object  $model  Модель
     * @return  bool   True в случае успеха, false в противном случае
     * @throws \Exception
     * @since 1.0.0
     */
    public function batch($model = null)
    {
        $this->checkToken();

        /** @var \Ilange\Component\Ishop\Administrator\Model\ProductModel $model */
        $model = $this->getModel('Product', 'Administrator', []);
        $this->setRedirect(
            Route::_('index.php?option=com_ishop&view=products' . $this->getRedirectToListAppend(), false)
        );

        return parent::batch($model);
    }
}