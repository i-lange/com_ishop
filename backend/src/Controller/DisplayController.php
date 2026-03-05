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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Контроллер отображения компонента com_ishop
 * @since 1.0.0
 */
class DisplayController extends BaseController
{
    /**
     * Шаблон вывода по умолчанию
     * @var string
     * @since 1.0.0
     */
    protected $default_view = 'products';

    /**
     * Метод отображения шаблона вывода
     * @param bool $cachable Если true, вывод будет кэшироваться
     * @param array $urlparams Массив параметров URL и их типов, см. {@link InputFilter::clean()}.
     * @return BaseController|bool
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view   = $this->input->get('view', 'products');
        $layout = $this->input->get('layout', 'products');
        $id     = $this->input->getInt('id');

        // Проверяем форму
        if ($view == 'product' && $layout == 'edit' && !$this->checkEditId('com_ishop.edit.product', $id)) {
            // Каким-то образом человек просто зашел в форму - мы этого не допускаем
            if (!count($this->app->getMessageQueue())) {
                $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_ishop&view=products', false));

            return false;
        }

        return parent::display($cachable, $urlparams);
    }
}
