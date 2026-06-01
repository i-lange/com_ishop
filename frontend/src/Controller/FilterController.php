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

use Ilange\Component\Ishop\Site\Service\FilterRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
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
     * Кеш наборов товаров для расчета фасеток в рамках одного preview-запроса.
     *
     * Один и тот же набор фильтров может понадобиться нескольким фасеткам,
     * особенно когда пользователь еще ничего не выбрал. Кеш защищает
     * endpoint от повторного создания CategoryModel и повторных SQL-запросов.
     *
     * @var array<string, array<int, int>>
     * @since 1.0.0
     */
    private array $filteredProductIdsCache = [];

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

            // Счетчик должен отражать строгий результат: применены все выбранные фильтры.
            $productIds = $this->getFilteredProductIds($categoryId, $itemId, $filters);

            echo new JsonResponse([
                'productCount'     => count($productIds),
                // Доступность фасеток считается отдельно, чтобы выбранная опция не скрывала соседние.
                'availableOptions' => $this->getAvailableOptions($categoryId, $itemId, $filters),
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

    /**
     * Подготавливает input для CategoryModel в URL-driven режиме.
     *
     * CategoryModel читает фильтры из Joomla input в populateState().
     * Для AJAX endpoint'а мы имитируем прямую загрузку SEO URL: выставляем
     * `filter_route=1`, category id, Itemid и все request keys, которые
     * модель ожидает от FilterRules::parse().
     *
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return void
     * @since 1.0.0
     */
    private function primeCategoryInput(int $categoryId, int $itemId, array $filters): void
    {
        // `id` нужен CategoryModel как ID текущей категории, `category_id` нужен AJAX-контексту.
        $this->input->set('id', $categoryId);
        $this->input->set('category_id', $categoryId);
        $this->input->set('filter_route', 1);

        if ($itemId > 0) {
            $this->input->set('Itemid', $itemId);
        }

        // Явно записываем 0 для отсутствующих диапазонов, чтобы старые input-значения не протекали между расчетами фасеток.
        foreach ([
            'min_price', 'max_price', 'good_price',
            'min_width', 'max_width',
            'min_height', 'max_height',
            'min_depth', 'max_depth',
            'min_weight', 'max_weight',
        ] as $key) {
            $this->input->set($key, $filters[$key] ?? 0);
        }

        $this->input->set('manufacturers', $filters['manufacturers'] ?? []);
        $this->input->set('warehouses', $filters['warehouses'] ?? []);
        $this->input->set('ishop_fields', $filters['ishop_fields'] ?? []);
    }

    /**
     * Получает ID товаров с учетом переданного состояния фильтра.
     *
     * Создает свежую CategoryModel, подготавливает input и делегирует расчет
     * ProductsModel через CategoryModel::getFilteredItemsId(). Свежая модель
     * нужна потому, что состояние Joomla MVC модели кешируется после первого
     * populateState(), а для фасеток в одном request требуется много разных
     * состояний фильтра.
     *
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return array<int, int> Список ID товаров, подходящих под переданный фильтр.
     * @throws \Exception Если CategoryModel или ProductsModel не смогут выполнить запрос.
     * @since 1.0.0
     */
    private function getFilteredProductIds(int $categoryId, int $itemId, array $filters): array
    {
        $cacheKey = $categoryId . ':' . $itemId . ':' . serialize($filters);

        // В рамках preview одинаковые состояния часто повторяются, например пустой фильтр для разных фасеток.
        if (isset($this->filteredProductIdsCache[$cacheKey])) {
            return $this->filteredProductIdsCache[$cacheKey];
        }

        $this->primeCategoryInput($categoryId, $itemId, $filters);

        $model = Factory::getApplication()
            ->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createModel('Category', 'Site');

        $productIds = ArrayHelper::toInteger((array) $model->getFilteredItemsId());
        $this->filteredProductIdsCache[$cacheKey] = $productIds;

        return $productIds;
    }

    /**
     * Собирает доступные значения для фасеток с учетом текущего состояния фильтра.
     *
     * Для каждой фасетки используется свой набор товаров: все активные
     * фильтры остаются включенными, но фильтр самой фасетки временно
     * исключается. Это позволяет не скрывать альтернативные значения внутри
     * выбранной группы, но корректно блокировать значения, невозможные из-за
     * других групп фильтров.
     *
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return array{
     *     manufacturers:array<int, int>,
     *     warehouses:array<int, int>,
     *     ishop_fields:array,
     *     price_range:array{min:int,max:int},
     *     sizes:array<string, array{min:int,max:int}>
     * }
     * @throws \Exception Если не удалось получить набор товаров для одной из фасеток.
     * @since 1.0.0
     */
    private function getAvailableOptions(int $categoryId, int $itemId, array $filters): array
    {
        // Производители считаются без фильтра производителей, но с ценой, складами, размерами и характеристиками.
        $manufacturerProductIds = $this->getFilteredProductIds(
            $categoryId,
            $itemId,
            $this->withoutFacet($filters, 'manufacturers')
        );

        // Склады считаются без фильтра складов, чтобы выбранный склад не скрывал другие доступные склады.
        $warehouseProductIds = $this->getFilteredProductIds(
            $categoryId,
            $itemId,
            $this->withoutFacet($filters, 'warehouses')
        );

        return [
            'manufacturers' => $this->getManufacturerIds($manufacturerProductIds),
            'warehouses'    => $this->getWarehouseIds($warehouseProductIds),
            'ishop_fields'  => $this->getFieldOptions($categoryId, $itemId, $filters),
            // Диапазон цены должен показывать границы, возможные при остальных выбранных фильтрах.
            'price_range'   => $this->getMainRange(
                $this->getFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'price')),
                'price'
            ),
            'sizes'         => [
                // Каждый размер считается независимо от своего диапазона, но с учетом остальных размерных фильтров.
                'width'  => $this->getMainRange(
                    $this->getFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'width')),
                    'width'
                ),
                'height' => $this->getMainRange(
                    $this->getFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'height')),
                    'height'
                ),
                'depth'  => $this->getMainRange(
                    $this->getFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'depth')),
                    'depth'
                ),
                'weight' => $this->getMainRange(
                    $this->getFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'weight')),
                    'weight'
                ),
            ],
        ];
    }

    /**
     * Удаляет фильтр текущей фасетки, оставляя все остальные ограничения.
     *
     * Метод используется только для расчета доступности опций. Он не меняет
     * исходный массив фильтров, а возвращает копию без одной логической
     * группы: системной фасетки, диапазона цены/размера или конкретной
     * характеристики товара.
     *
     * @param   array     $filters  Нормализованные фильтры
     * @param   string    $facet    Имя фасетки: manufacturers, warehouses, price, width, height, depth, weight или ishop_field
     * @param   int|null  $fieldId  ID характеристики, если $facet равен ishop_field
     *
     * @return array Нормализованные фильтры без указанной фасетки.
     * @since 1.0.0
     */
    private function withoutFacet(array $filters, string $facet, ?int $fieldId = null): array
    {
        $result = $filters;

        if ($facet === 'price') {
            unset($result['min_price'], $result['max_price']);
        } elseif (in_array($facet, ['width', 'height', 'depth', 'weight'], true)) {
            // Размерные фасетки представлены двумя request keys: min_* и max_*.
            unset($result['min_' . $facet], $result['max_' . $facet]);
        } elseif ($facet === 'ishop_field' && $fieldId !== null) {
            // Для характеристик исключается только текущее поле, остальные характеристики продолжают ограничивать выбор.
            unset($result['ishop_fields'][$fieldId]);

            if (empty($result['ishop_fields'])) {
                unset($result['ishop_fields']);
            }
        } else {
            unset($result[$facet]);
        }

        return $result;
    }

    /**
     * Возвращает минимальное и максимальное значение основного числового поля.
     *
     * Используется для цены и физических размеров товара. Метод получает
     * уже подготовленный список товаров для конкретной фасетки и агрегирует
     * по нему границы диапазона.
     *
     * @param   array<int, int>  $productIds  ID товаров для агрегации
     * @param   string           $column      Колонка таблицы #__ishop_products: price, width, height, depth или weight
     *
     * @return array{min:int,max:int} Округленные границы диапазона.
     * @throws \Exception Если запрос к базе данных завершится ошибкой.
     * @since 1.0.0
     */
    private function getMainRange(array $productIds, string $column): array
    {
        if (empty($productIds)) {
            return ['min' => 0, 'max' => 0];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Имя колонки приходит только из внутренних вызовов контроллера, поэтому достаточно quoteName().
        $query = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('a.' . $column) . ') AS ' . $db->quoteName('min_value'))
            ->select('MAX(' . $db->quoteName('a.' . $column) . ') AS ' . $db->quoteName('max_value'))
            ->from($db->quoteName('#__ishop_products', 'a'))
            ->whereIn($db->quoteName('a.id'), $productIds);

        $row = $db->setQuery($query)->loadObject();

        return [
            'min' => isset($row->min_value) ? (int) round((float) $row->min_value) : 0,
            'max' => isset($row->max_value) ? (int) round((float) $row->max_value) : 0,
        ];
    }

    /**
     * Возвращает ID производителей, доступных для переданного набора товаров.
     *
     * Список сортируется так же, как производители в фильтре: сначала
     * `ordering`, затем `alias`. Это сохраняет стабильный порядок значений
     * в AJAX-ответе и не создает визуальных скачков в UI.
     *
     * @param   array<int, int>  $productIds  ID товаров для расчета доступных производителей
     *
     * @return array<int, int> ID доступных производителей.
     * @throws \Exception Если запрос к базе данных завершится ошибкой.
     * @since 1.0.0
     */
    private function getManufacturerIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('a.manufacturer_id', 'id'))
            ->from($db->quoteName('#__ishop_products', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__ishop_manufacturers', 'manufacturer'),
                $db->quoteName('manufacturer.id') . ' = ' . $db->quoteName('a.manufacturer_id')
            )
            ->whereIn($db->quoteName('a.id'), $productIds)
            ->order($db->quoteName('manufacturer.ordering') . ' ASC, ' . $db->quoteName('manufacturer.alias') . ' ASC');

        return ArrayHelper::toInteger($db->setQuery($query)->loadColumn());
    }

    /**
     * Возвращает ID складов, доступных для переданного набора товаров.
     *
     * Доступность склада определяется наличием записи в таблице складских
     * остатков для товара. Значение фактического остатка здесь не проверяется,
     * потому что ProductsModel использует тот же принцип через EXISTS по
     * #__ishop_warehouses_stock.
     *
     * @param   array<int, int>  $productIds  ID товаров для расчета доступных складов
     *
     * @return array<int, int> ID доступных складов.
     * @throws \Exception Если запрос к базе данных завершится ошибкой.
     * @since 1.0.0
     */
    private function getWarehouseIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('stock.warehouse_id', 'id'))
            ->from($db->quoteName('#__ishop_warehouses_stock', 'stock'))
            ->join(
                'INNER',
                $db->quoteName('#__ishop_warehouses', 'warehouse'),
                $db->quoteName('warehouse.id') . ' = ' . $db->quoteName('stock.warehouse_id')
            )
            ->whereIn($db->quoteName('stock.product_id'), $productIds)
            ->order($db->quoteName('warehouse.ordering') . ' ASC, ' . $db->quoteName('warehouse.alias') . ' ASC');

        return ArrayHelper::toInteger($db->setQuery($query)->loadColumn());
    }

    /**
     * Собирает доступные значения характеристик для текущего состояния фильтра.
     *
     * Каждая характеристика считается отдельно: при расчете конкретного
     * fieldId фильтр этого же fieldId исключается, а все остальные фильтры
     * остаются активными. Это нужно для корректного поведения list-фасеток:
     * выбранный цвет не должен скрывать другие цвета, но выбранная ширина
     * или бренд должны ограничивать список цветов.
     *
     * @param   int    $categoryId  ID категории, из параметров которой берется список filter_fields
     * @param   int    $itemId      ID пункта меню для корректного state ключа CategoryModel
     * @param   array  $filters     Нормализованные фильтры текущего запроса
     *
     * @return array<int, array> Доступные характеристики в формате availableOptions.ishop_fields.
     * @throws \Exception Если не удалось получить товары или значения характеристик.
     * @since 1.0.0
     */
    private function getFieldOptions(int $categoryId, int $itemId, array $filters): array
    {
        $fieldIds = $this->getCategoryFilterFieldIds($categoryId);

        if (empty($fieldIds)) {
            return [];
        }

        $result = [];

        foreach ($fieldIds as $fieldId) {
            // Исключаем только текущую характеристику: остальные выбранные характеристики остаются ограничителями.
            $productIds = $this->getFilteredProductIds(
                $categoryId,
                $itemId,
                $this->withoutFacet($filters, 'ishop_field', (int) $fieldId)
            );

            $option = $this->getSingleFieldOption((int) $fieldId, $productIds);

            if (!empty($option)) {
                $result[$fieldId] = $option;
            }
        }

        return $result;
    }

    /**
     * Возвращает доступные значения одной характеристики по списку товаров.
     *
     * Для числовой характеристики возвращает min/max, для списка — пары
     * valueId => title, для boolean — только тип. Формат результата совпадает
     * с тем, который ожидает JavaScript модуля фильтра.
     *
     * @param   int             $fieldId     ID характеристики из #__ishop_fields
     * @param   array<int,int>  $productIds  ID товаров, по которым нужно собрать значения
     *
     * @return array{
     *     type?:string,
     *     min?:int,
     *     max?:int,
     *     values?:array<int|string, string>
     * }
     * @throws \Exception Если запрос к базе данных завершится ошибкой.
     * @since 1.0.0
     */
    private function getSingleFieldOption(int $fieldId, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Один SQL агрегирует разные типы характеристик:
        // type=1 собирает список значений, type=0 — числовой диапазон, type=2 — только факт доступности.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('field.id'),
                $db->quoteName('field.type'),
                ' CASE WHEN ' . $db->quoteName('field.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->quoteName('value.value') .
                ' ORDER BY ' . $db->quoteName('value.ordering') . ', ' . $db->quoteName('value.alias') . ' SEPARATOR ' . $db->quote('||') . ')' .
                ' WHEN ' . $db->quoteName('field.type') .
                ' = 0 THEN CONCAT(MIN(' . $db->quoteName('map.value') . '), ' . $db->quote(', ') . ', MAX(' . $db->quoteName('map.value') . '))' .
                ' ELSE ' . $db->quote('') .
                ' END AS ' . $db->quoteName('values'),
                ' CASE WHEN ' . $db->quoteName('field.type') .
                ' = 1 THEN GROUP_CONCAT(DISTINCT ' . $db->quoteName('value.id') .
                ' ORDER BY ' . $db->quoteName('value.ordering') . ', ' . $db->quoteName('value.alias') . ' SEPARATOR ' . $db->quote('||') . ')' .
                ' ELSE ' . $db->quote('') .
                ' END AS ' . $db->quoteName('values_id'),
            ])
            ->from($db->quoteName('#__ishop_fields', 'field'))
            ->join(
                'INNER',
                $db->quoteName('#__ishop_fields_map', 'map'),
                $db->quoteName('map.field_id') . ' = ' . $db->quoteName('field.id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_values', 'value'),
                '(' . $db->quoteName('field.type') . ' = 1 AND ' .
                $db->quoteName('value.id') . ' = ' . $db->quoteName('map.value') . ')'
            )
            ->where($db->quoteName('field.state') . ' = 1')
            ->where($db->quoteName('field.id') . ' = :fieldId')
            ->whereIn($db->quoteName('map.product_id'), $productIds)
            ->group([
                $db->quoteName('field.id'),
                $db->quoteName('field.type'),
            ])
            ->bind(':fieldId', $fieldId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadObject();

        if (!$row) {
            return [];
        }

        $type = (int) $row->type;

        if ($type === 0) {
            // Числовые характеристики хранят значения в fields_map.value и отдаются как диапазон.
            [$min, $max] = array_pad(explode(',', (string) $row->values), 2, 0);

            return [
                'type' => 'range',
                'min'  => (int) round((float) $min),
                'max'  => (int) round((float) $max),
            ];
        }

        if ($type === 1) {
            // Для списков важно вернуть ID значений, потому UI включает/отключает checkbox по value id.
            $values = explode('||', (string) $row->values);
            $ids = explode('||', (string) $row->values_id);

            return [
                'type'   => 'list',
                'values' => count($ids) === count($values) ? array_combine($ids, $values) : [],
            ];
        }

        if ($type === 2) {
            // Boolean-фасетка доступна, если по текущему набору товаров есть хотя бы одна запись field map.
            return [
                'type' => 'boolean',
            ];
        }

        return [];
    }

    /**
     * Возвращает ID характеристик, включенных в фильтр текущей категории.
     *
     * Список хранится в params категории Joomla в ключе `filter_fields`.
     * Именно этот список определяет, какие характеристики должны попасть
     * в availableOptions.ishop_fields.
     *
     * @param   int  $categoryId  ID категории com_ishop
     *
     * @return array<int, int> ID характеристик фильтра категории.
     * @throws \Exception Если запрос к базе данных завершится ошибкой.
     * @since 1.0.0
     */
    private function getCategoryFilterFieldIds(int $categoryId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_ishop'))
            ->bind(':id', $categoryId, ParameterType::INTEGER);

        $params = new Registry((string) $db->setQuery($query)->loadResult());

        return ArrayHelper::toInteger((array) $params->get('filter_fields', []));
    }
}
