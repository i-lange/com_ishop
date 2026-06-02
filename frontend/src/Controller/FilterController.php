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

use Ilange\Component\Ishop\Site\Service\FilterAvailabilityService;
use Ilange\Component\Ishop\Site\Service\FilterRules;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use RuntimeException;

/**
 * Контроллер AJAX-endpoint'ов SEO-фильтра категории com_ishop.
 *
 * Отвечает за предпросмотр результата фильтрации, расчет доступных фасеток
 * для текущего состояния фильтра и сброс session-state категории.
 * Основная особенность preview-логики: счетчик товаров считается по всем
 * выбранным фильтрам, а доступность каждой фасетки — по всем фильтрам,
 * кроме самой этой фасетки.
 *
 * @since 1.0.0
 */
class FilterController extends BaseController
{
    /**
     * Возвращает данные предпросмотра фильтра и готовый ЧПУ URL.
     *
     * Endpoint вызывается модулем фильтра при изменении формы. Метод
     * проверяет CSRF token, нормализует входные значения, считает точное
     * количество товаров после применения всех фильтров и отдельно собирает
     * доступные значения для UI.
     *
     * @return void
     * @throws \Exception Если Joomla application не может закрыть ответ.
     * @since 1.0.0
     */
    public function preview(): void
    {
        $app = Factory::getApplication();
        $this->prepareJsonResponse();

        try {
            $this->checkToken();

            $categoryId = $this->getCategoryId();
            $itemId = $this->input->getInt('Itemid', 0);
            $filters = FilterRules::normalizeFilterInput($this->collectFilterInput());

            $availabilityService = new FilterAvailabilityService();
            // Счетчик должен отражать строгий результат: применены все выбранные фильтры.
            $productIds = $availabilityService->getFilteredProductIds($categoryId, $itemId, $filters);

            echo new JsonResponse([
                'productCount'     => count($productIds),
                // Доступность фасеток считается отдельно, чтобы выбранная опция не скрывала соседние.
                'availableOptions' => $availabilityService->getAvailableOptions($categoryId, $itemId, $filters),
                'sefUrl'           => FilterRules::getFilterRoute($categoryId, $filters, $itemId),
                'baseUrl'          => FilterRules::getBaseCategoryRoute($categoryId, $itemId),
            ]);
        } catch (\Throwable $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Очищает session-state фильтра категории и возвращает базовый URL категории.
     *
     * Endpoint используется кнопкой сброса фильтра. Сброс привязан к паре
     * categoryId + Itemid, потому что CategoryModel хранит состояние фильтра
     * отдельно для разных пунктов меню.
     *
     * @return void
     * @throws \Exception Если Joomla application не может закрыть ответ.
     * @since 1.0.0
     */
    public function reset(): void
    {
        $app = Factory::getApplication();
        $this->prepareJsonResponse();

        try {
            $this->checkToken();

            $categoryId = $this->getCategoryId();
            $itemId = $this->input->getInt('Itemid', 0);

            FilterRules::clearCategoryFilterState($categoryId, $itemId);

            echo new JsonResponse([
                'baseUrl' => FilterRules::getBaseCategoryRoute($categoryId, $itemId),
            ]);
        } catch (\Throwable $e) {
            echo new JsonResponse($e);
        }

        $app->close();
    }

    /**
     * Устанавливает JSON headers.
     *
     * Joomla controller может быть вызван в обычном site context, поэтому
     * endpoint явно выставляет MIME type до вывода JsonResponse.
     *
     * @return void
     * @since 1.0.0
     */
    private function prepareJsonResponse(): void
    {
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';
        $app->setHeader('Content-Type', $app->mimeType . '; charset=' . $app->charSet);
        $app->sendHeaders();
    }

    /**
     * Получает ID категории из запроса.
     *
     * AJAX-форма передает `category_id`, а обычный роутинг Joomla может
     * использовать `id`. Метод поддерживает оба варианта и валидирует,
     * что категория действительно присутствует.
     *
     * @return int Положительный ID категории товаров.
     * @throws RuntimeException Если ID категории отсутствует или равен нулю.
     * @since 1.0.0
     */
    private function getCategoryId(): int
    {
        $categoryId = $this->input->getInt('category_id', 0) ?: $this->input->getInt('id', 0);

        if ($categoryId <= 0) {
            throw new RuntimeException('Invalid category id', 400);
        }

        return $categoryId;
    }

    /**
     * Собирает входные значения фильтра.
     *
     * Метод не принимает решение, какие значения активны. Он только
     * безопасно читает поддерживаемые поля из request input; удаление нулей,
     * дублей и пустых значений выполняет FilterRules::normalizeFilterInput().
     *
     * @return array{
     *     min_price:int,
     *     max_price:int,
     *     good_price:int,
     *     min_width:int,
     *     max_width:int,
     *     min_height:int,
     *     max_height:int,
     *     min_depth:int,
     *     max_depth:int,
     *     min_weight:int,
     *     max_weight:int,
     *     manufacturers:array,
     *     warehouses:array,
     *     ishop_fields:array
     * }
     * @since 1.0.0
     */
    private function collectFilterInput(): array
    {
        return [
            'min_price'    => $this->input->getInt('min_price', 0),
            'max_price'    => $this->input->getInt('max_price', 0),
            'good_price'   => $this->input->getInt('good_price', 0),
            'min_width'    => $this->input->getInt('min_width', 0),
            'max_width'    => $this->input->getInt('max_width', 0),
            'min_height'   => $this->input->getInt('min_height', 0),
            'max_height'   => $this->input->getInt('max_height', 0),
            'min_depth'    => $this->input->getInt('min_depth', 0),
            'max_depth'    => $this->input->getInt('max_depth', 0),
            'min_weight'   => $this->input->getInt('min_weight', 0),
            'max_weight'   => $this->input->getInt('max_weight', 0),
            'manufacturers'=> $this->input->get('manufacturers', [], 'array'),
            'warehouses'   => $this->input->get('warehouses', [], 'array'),
            'ishop_fields' => $this->input->get('ishop_fields', [], 'array'),
        ];
    }

}
