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

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;

/**
 * Класс контроллера списка товаров
 * @since 1.0.0
 */
class ProductsController extends AdminController
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
     * @throws Exception
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        if ($this->input->get('view') == 'featured') {
            $this->view_list = 'featured';
        }

        $this->registerTask('unfeatured', 'featured');
    }

    /**
     * Метод добавляет или удаляет товары из избранных
     *
     * @return  void
     *
     * @throws Exception
     * @since   1.0.0
     */
    public function featured()
    {
        $this->checkToken();
        $user        = $this->app->getIdentity();
        $ids         = (array) $this->input->get('cid', [], 'int');
        $values      = ['featured' => 1, 'unfeatured' => 0];
        $task        = $this->getTask();
        $value       = ArrayHelper::getValue($values, $task, 0, 'int');
        $redirectUrl = 'index.php?option=com_ishop&view=' . $this->view_list . $this->getRedirectToListAppend();

        // Проверки доступа
        foreach ($ids as $i => $id) {
            if ($id === 0) {
                unset($ids[$i]);
                continue;
            }

            if (!$user->authorise('core.edit.state', 'com_ishop.product.' . (int) $id)) {
                // Отсекаем те элементы, которые нельзя изменить
                unset($ids[$i]);
                $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'notice');
            }
        }

        if (empty($ids)) {
            $this->app->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
            $this->setRedirect(Route::_($redirectUrl, false));
            return;
        }

        $model = $this->getModel();

        try {
            $model->featured($ids, $value);
        } catch (Exception $e) {
            $this->setRedirect(Route::_($redirectUrl, false), $e->getMessage(), 'error');
            return;
        }

        if ($value == 1) {
            $message = Text::plural('COM_ISHOP_N_ITEMS_FEATURED', count($ids));
        } else {
            $message = Text::plural('COM_ISHOP_N_ITEMS_UNFEATURED', count($ids));
        }

        $this->setRedirect(Route::_($redirectUrl, false), $message);
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
    public function getModel($name = 'Product', $prefix = 'Administrator', $config = ['ignore_request' => true]): object
    {
        return parent::getModel($name, $prefix, $config);
    }
}