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

use Ilange\Component\Ishop\Administrator\Helper\FieldHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;

/**
 * Класс контроллера списка характеристик
 * @since 1.0.0
 */
class FieldsController extends AdminController
{
    /**
     * Конструктор
     *
     * @param   array                 $config   Необязательный ассоциативный массив параметров конфигурации.
     *                                          Распознаваемые значения ключей включают 'name', 'default_task',
     *                                          model_path' и 'view_path' (этот список не является исчерпывающим).
     * @param   ?MVCFactoryInterface  $factory  Фабрика
     * @param   ?CMSApplication       $app      Приложение для диспетчера
     * @param   ?Input                $input    Вход
     *
     * @throws \Exception
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('update_filter', 'updateFilterForCategories');
    }

    /**
     * Метод последовательно пересоздает таблицы
     * для фильтрации товаров по каждой категории,
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function updateFilterForCategories()
    {
        if (FieldHelper::updateFilterForCategories()) {
            $this->setMessage(Text::_('COM_ISHOP_FIELDS_UPDATE_FILTER_SUCCESS'));
        } else {
            $this->setMessage(Text::_('COM_ISHOP_FIELDS_UPDATE_FILTER_ERROR'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ishop&view=fields', false));
    }

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
    public function getModel($name = 'Field', $prefix = 'Administrator', $config = ['ignore_request' => true]): object
    {
        return parent::getModel($name, $prefix, $config);
    }
}