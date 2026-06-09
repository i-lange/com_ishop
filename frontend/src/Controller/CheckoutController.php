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

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;


/**
 * Контроллер оформления заказа
 * @since 1.0.0
 */
class CheckoutController extends BaseController
{
    /**
     * Сохраняет заказ, оформленный через AJAX на странице checkout.
     *
     * @return void
     * @throws Exception
     * @since 1.0.11
     */
    public function save(): void
    {
        $this->app->allowCache(false);

        try {
            $this->requireValidPostToken();

            $result = $this
                ->getModel('Checkout', 'Site')
                ->saveOrder([
                    'name'     => $this->input->post->getString('name', ''),
                    'phone'    => $this->input->post->getString('phone', ''),
                    'payment'  => $this->input->post->getString('payment', ''),
                    'shipping' => $this->input->post->getString('shipping', ''),
                    'point'    => $this->input->post->getString('point', ''),
                    'address'  => $this->input->post->getString('address', ''),
                    'confirm'  => $this->input->post->getBool('confirm', false),
                    'code'     => $this->input->post->getString('code', ''),
                ]);

            echo new JsonResponse($result, Text::_('COM_ISHOP_CHECKOUT_SAVE_SUCCESS'));
        }
        catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Применяет промокод к текущему checkout через AJAX.
     *
     * @return void
     * @throws Exception
     * @since 1.0.11
     */
    public function code(): void
    {
        $this->app->allowCache(false);

        try {
            $this->requireValidPostToken();

            $result = $this
                ->getModel('Checkout', 'Site')
                ->applyCode($this->input->post->getString('code', ''));

            echo new JsonResponse($result, Text::_('COM_ISHOP_CHECKOUT_CODE_APPLIED'));
        }
        catch (Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Проверяет CSRF token для POST AJAX-запросов checkout.
     *
     * @return void
     * @throws Exception
     * @since 1.0.11
     */
    private function requireValidPostToken(): void
    {
        if (!$this->checkToken('post', false)) {
            throw new Exception(Text::_('JINVALID_TOKEN_NOTICE'), 403);
        }
    }
}
