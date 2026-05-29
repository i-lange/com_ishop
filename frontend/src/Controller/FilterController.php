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
 * Контроллер SEO-фильтра категории com_ishop.
 * @since 1.0.0
 */
class FilterController extends BaseController
{
    /**
     * Возвращает данные предпросмотра фильтра и готовый ЧПУ URL.
     *
     * @return void
     * @throws \Exception
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

            $this->primeCategoryInput($categoryId, $itemId, $filters);

            $model = $this->getModel('Category', 'Site');
            $productIds = $model->getFilteredItemsId();

            echo new JsonResponse([
                'productCount'     => count($productIds),
                'availableOptions' => $this->getAvailableOptions($categoryId, $productIds),
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
     * @return void
     * @throws \Exception
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
     * @return int
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
     * @return array
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
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return void
     * @since 1.0.0
     */
    private function primeCategoryInput(int $categoryId, int $itemId, array $filters): void
    {
        $this->input->set('id', $categoryId);
        $this->input->set('category_id', $categoryId);
        $this->input->set('filter_route', 1);

        if ($itemId > 0) {
            $this->input->set('Itemid', $itemId);
        }

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
     * Собирает доступные значения для текущего набора товаров.
     *
     * @param   int    $categoryId  ID категории
     * @param   array  $productIds  ID товаров
     *
     * @return array
     * @throws \Exception
     * @since 1.0.0
     */
    private function getAvailableOptions(int $categoryId, array $productIds): array
    {
        $productIds = array_values(array_filter(ArrayHelper::toInteger($productIds)));

        return [
            'manufacturers' => $this->getManufacturerIds($productIds),
            'warehouses'    => $this->getWarehouseIds($productIds),
            'ishop_fields'  => $this->getFieldOptions($categoryId, $productIds),
            'price_range'   => $this->getMainRange($productIds, 'price'),
            'sizes'         => [
                'width'  => $this->getMainRange($productIds, 'width'),
                'height' => $this->getMainRange($productIds, 'height'),
                'depth'  => $this->getMainRange($productIds, 'depth'),
                'weight' => $this->getMainRange($productIds, 'weight'),
            ],
        ];
    }

    /**
     * @param array $productIds
     * @return array{min:int,max:int}
     */
    private function getMainRange(array $productIds, string $column): array
    {
        if (empty($productIds)) {
            return ['min' => 0, 'max' => 0];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
     * @param array $productIds
     * @return array<int, int>
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
     * @param array $productIds
     * @return array<int, int>
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
     * @param int $categoryId
     * @param array $productIds
     * @return array
     */
    private function getFieldOptions(int $categoryId, array $productIds): array
    {
        $fieldIds = $this->getCategoryFilterFieldIds($categoryId);

        if (empty($fieldIds) || empty($productIds)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
            ->whereIn($db->quoteName('field.id'), $fieldIds)
            ->whereIn($db->quoteName('map.product_id'), $productIds)
            ->group([
                $db->quoteName('field.id'),
                $db->quoteName('field.type'),
            ]);

        $rows = $db->setQuery($query)->loadObjectList('id');
        $result = [];

        foreach ($rows as $fieldId => $row) {
            $type = (int) $row->type;

            if ($type === 0) {
                [$min, $max] = array_pad(explode(',', (string) $row->values), 2, 0);
                $result[$fieldId] = [
                    'type' => 'range',
                    'min'  => (int) round((float) $min),
                    'max'  => (int) round((float) $max),
                ];
            } elseif ($type === 1) {
                $values = explode('||', (string) $row->values);
                $ids = explode('||', (string) $row->values_id);
                $result[$fieldId] = [
                    'type'   => 'list',
                    'values' => count($ids) === count($values) ? array_combine($ids, $values) : [],
                ];
            } elseif ($type === 2) {
                $result[$fieldId] = [
                    'type' => 'boolean',
                ];
            }
        }

        return $result;
    }

    /**
     * @param int $categoryId
     * @return array<int, int>
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
