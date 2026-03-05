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

use Ilange\Component\Ishop\Administrator\Helper\DeliveryHelper;
use Ilange\Component\Ishop\Administrator\Helper\DiscountHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;

/**
 * Класс контроллера списка скидок
 * @since 1.0.0
 */
class DiscountsController extends AdminController
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
     * @since   3.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('calculate', 'calculatePrices');
    }

    /**
     * Пересчет цен с учетом скидок
     *
     * @throws \Exception
     * @since 1.0.0
     */
    public function calculatePrices()
    {
        if (DiscountHelper::calculate()) {
            $this->setMessage(Text::_('COM_ISHOP_EXPORT_DISCOUNT_CALCULATE_SUCCESS'));
        } else {
            $this->setMessage(Text::_('COM_ISHOP_EXPORT_DISCOUNT_CALCULATE_ERROR'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ishop&view=discounts', false));
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
    public function getModel($name = 'Discount', $prefix = 'Administrator', $config = ['ignore_request' => true]): object
    {
        return parent::getModel($name, $prefix, $config);
    }
}