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
 * Контроллер сравнения com_ishop
 * @since 1.0.0
 */
class CompareController extends BaseController
{
    /**
     * Добавляем товар в список сравнения по его идентификатору
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

        // Устанавливаем заголовки ответа
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();

        try {
            $model = $this->getModel('Compare', 'Site');
            $result = $model->add($product_id);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_COMPARE_ERROR'), true);
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
     * Удаляет товар из списка сравнения по его идентификатору
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
                ->getModel('Compare', 'Site')
                ->remove($product_id);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_COMPARE_ERROR'), true);
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
     * Очищает список сравнения
     *
     *
     * @return void
     * @throws Exception
     * @since 1.0.0
     */
    public function clear()
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';

        // Устанавливаем заголовки ответа
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();

        try {
            $result = $this
                ->getModel('Cart', 'Compare')
                ->remove();

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_COMPARE_ERROR'), true);
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