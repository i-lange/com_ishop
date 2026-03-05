<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Input\Json;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Ilange\Component\Ishop\Administrator\Helper\IshopHelper;

/**
 * Контроллер отображения компонента com_ishop
 * @since 1.0.0
 */
class DisplayController extends BaseController
{
    /**
     * Метод отображения шаблона вывода
     *
     * @param   bool   $cachable   Если true, вывод будет кэшироваться
     * @param   array  $urlparams  Массив параметров URL и их типов, см. {@link InputFilter::clean()}.
     *
     * @return DisplayController
     * @throws \Exception
     * @since 1.0.0
     */
    public function display($cachable = false, $urlparams = []): DisplayController
    {
        $vName = $this->input->getCmd('view', 'categories');
        $this->input->set('view', $vName);

        if (in_array($vName, ['categories', 'manufacturers', 'suppliers'])) {
            $cachable = true;
        }

        parent::display($cachable, $urlparams);

        if ($this->input->exists('ishop_fields') ||
            $this->input->exists('filter_order') ||
            $this->input->exists('active_zone')) {

            $this->setRedirect($_SERVER['REQUEST_URI']);
        }

        if ($vName === 'checkout' && $this->input->exists('products')) {
            $this->app->setUserState('com_ishop.checkout.products', $this->input->getInt('products', []));

            $this->setRedirect($_SERVER['REQUEST_URI']);
        }

        if ($vName === 'compare' && $this->input->exists('category_id')) {
            $this->app->setUserState('com_ishop.compare.category_id', $this->input->getInt('category_id',0));

            $this->setRedirect($_SERVER['REQUEST_URI']);
        }

        return $this;
    }
}