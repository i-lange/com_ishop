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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;


/**
 * Контроллер корзины com_ishop
 * @since 1.0.0
 */
class CartController extends BaseController
{
    /**
     * Добавляем товар в корзину по его идентификатору
     *
     *
     * @return void
     * @throws Exception
     * @since 1.0.0
     */
    public function add()
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';

        // идентификатор товара
        $product_id			= $this->input->get('product_id', 0, 'int');
        $quantity	        = $this->input->get('quantity', 1, 'int');

        // Устанавливаем заголовки ответа
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();

        try {
            $result = $this
                ->getModel('Cart', 'Site')
                ->cartAdd($product_id, $quantity);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_CART_ERROR'), true);
                $app->close();
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Меняем количество товара в корзине по его идентификатору
     *
     *
     * @return void
     * @throws Exception
     * @since 1.0.0
     */
    public function change()
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';

        // идентификатор товара
        $product_id			= $this->input->get('product_id', 0, 'int');
        $quantity	        = $this->input->get('quantity', 1, 'int');

        // Устанавливаем заголовки ответа
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();

        try {
            $result = $this
                ->getModel('Cart', 'Site')
                ->cartChange($product_id, $quantity);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_CART_ERROR'), true);
                $app->close();
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Удаляет товар из корзины по его идентификатору
     *
     *
     * @return void
     * @throws Exception
     * @since 1.0.0
     */
    public function remove()
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';

        // идентификатор товара
        $product_id			= $this->input->get('product_id', 0, 'int');

        // Устанавливаем заголовки ответа
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();

        try {
            $result = $this
                ->getModel('Cart', 'Site')
                ->cartRemove($product_id);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_CART_ERROR'), true);
                $app->close();
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Пересчитывает метрики корзины
     * исходя из текущих выбранных товаров
     * для оформления заказа
     *
     * @return void
     * @throws Exception
     * @since 1.0.0
     */
    public function reload()
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';

        // идентификаторы товаров
        $filter_products = $this->input->get('filter_products', 0, 'int');

        // Устанавливаем заголовки ответа
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();

        try {
            $result = $this
                ->getModel('Cart', 'Site')
                ->getCart($filter_products, false);

            if ($result === false) {
                echo new JsonResponse($result, Text::_('COM_ISHOP_CART_ERROR'), true);
                $app->close();
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }
}