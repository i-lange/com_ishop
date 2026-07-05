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
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;


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
        $this->app->allowCache(false);

        // идентификатор товара
        $product_id			= $this->input->get('product_id', 0, 'int');

        try {
            $this->requireValidPostToken();

            $model = $this->getModel('Compare', 'Site');
            $result = $model->add($product_id);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_COMPARE_ERROR'), true);

                return;
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }
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
        $this->app->allowCache(false);

        // идентификатор товара
        $product_id			= $this->input->get('product_id', 0, 'int');

        try {
            $this->requireValidPostToken();

            $result = $this
                ->getModel('Compare', 'Site')
                ->remove($product_id);

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_COMPARE_ERROR'), true);

                return;
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }
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
        $this->app->allowCache(false);

        try {
            $this->requireValidPostToken();

            $result = $this
                ->getModel('Compare', 'Site')
                ->remove();

            if ($result === false) {
                echo new JsonResponse(false, Text::_('COM_ISHOP_COMPARE_ERROR'), true);

                return;
            }

            echo new JsonResponse($result);
        }
        catch(Exception $e) {
            echo new JsonResponse($e);
        }
    }

    /**
     * Удаляет товар из списка сравнения и возвращает пользователя на страницу сравнения.
     *
     * @return void
     * @throws Exception
     * @since 1.0.26
     */
    public function removeProduct(): void
    {
        $this->app->allowCache(false);

        $productId = $this->input->post->getInt('product_id', 0);

        try {
            $this->requireValidPostToken();

            $result = $productId > 0
                ? $this->getModel('Compare', 'Site')->remove($productId)
                : false;

            if ($result === false) {
                $this->app->enqueueMessage(Text::_('COM_ISHOP_COMPARE_ERROR'), 'error');
            }
        } catch (Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->redirectToCompare();
    }

    /**
     * Удаляет из сравнения все товары указанной категории и возвращает пользователя на страницу сравнения.
     *
     * @return void
     * @throws Exception
     * @since 1.0.26
     */
    public function removeCategory(): void
    {
        $this->app->allowCache(false);

        $categoryId = $this->input->post->getInt('category_id', 0);

        try {
            $this->requireValidPostToken();

            $result = $this
                ->getModel('Compare', 'Site')
                ->removeCategory($categoryId);

            if ($result === false) {
                $this->app->enqueueMessage(Text::_('COM_ISHOP_COMPARE_ERROR'), 'error');
            }
        } catch (Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->redirectToCompare();
    }

    /**
     * Проверяет CSRF token для POST AJAX-запросов.
     *
     * @return void
     * @throws Exception
     * @since 1.0.6
     */
    private function requireValidPostToken(): void
    {
        if (!$this->checkToken('post', false)) {
            throw new Exception(Text::_('JINVALID_TOKEN_NOTICE'), 403);
        }
    }

    /**
     * Возвращает пользователя на список сравнения после POST-действий.
     *
     * @return void
     * @since 1.0.26
     */
    private function redirectToCompare(): void
    {
        $this->setRedirect(Route::_(RouteHelper::getCompareRoute(), false));
    }
}
