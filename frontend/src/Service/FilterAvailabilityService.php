<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Собирает доступные опции фильтра категории.
 *
 * @since 1.0.0
 */
class FilterAvailabilityService
{
    /**
     * @var array<string, array<int, int>>
     * @since 1.0.0
     */
    private array $filteredProductIdsCache = [];

    /**
     * Получает ID товаров с учетом переданного состояния фильтра.
     *
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return array<int, int>
     * @throws \Exception
     * @since 1.0.0
     */
    public function getFilteredProductIds(int $categoryId, int $itemId, array $filters): array
    {
        return $this->withRestoredInput(function () use ($categoryId, $itemId, $filters): array {
            return $this->loadFilteredProductIds($categoryId, $itemId, $filters);
        });
    }

    /**
     * Собирает доступные значения для фасеток с учетом текущего состояния фильтра.
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
     * @throws \Exception
     * @since 1.0.0
     */
    public function getAvailableOptions(int $categoryId, int $itemId, array $filters): array
    {
        return $this->withRestoredInput(function () use ($categoryId, $itemId, $filters): array {
            $manufacturerProductIds = $this->loadFilteredProductIds(
                $categoryId,
                $itemId,
                $this->withoutFacet($filters, 'manufacturers')
            );

            $warehouseProductIds = $this->loadFilteredProductIds(
                $categoryId,
                $itemId,
                $this->withoutFacet($filters, 'warehouses')
            );

            return [
                'manufacturers' => $this->getManufacturerIds($manufacturerProductIds),
                'warehouses'    => $this->getWarehouseIds($warehouseProductIds),
                'ishop_fields'  => $this->getFieldOptions($categoryId, $itemId, $filters),
                'price_range'   => $this->getMainRange(
                    $this->loadFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'price')),
                    'price'
                ),
                'sizes'         => [
                    'width'  => $this->getMainRange(
                        $this->loadFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'width')),
                        'width'
                    ),
                    'height' => $this->getMainRange(
                        $this->loadFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'height')),
                        'height'
                    ),
                    'depth'  => $this->getMainRange(
                        $this->loadFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'depth')),
                        'depth'
                    ),
                    'weight' => $this->getMainRange(
                        $this->loadFilteredProductIds($categoryId, $itemId, $this->withoutFacet($filters, 'weight')),
                        'weight'
                    ),
                ],
            ];
        });
    }

    /**
     * @param   callable  $callback  Callback, который временно меняет request input
     *
     * @return mixed
     * @since 1.0.0
     */
    private function withRestoredInput(callable $callback): mixed
    {
        $input = Factory::getApplication()->getInput();
        $keys = [
            'id', 'category_id', 'filter_route', 'Itemid',
            'min_price', 'max_price', 'good_price',
            'min_width', 'max_width',
            'min_height', 'max_height',
            'min_depth', 'max_depth',
            'min_weight', 'max_weight',
            'manufacturers', 'warehouses', 'ishop_fields',
        ];

        $snapshot = [];

        foreach ($keys as $key) {
            $snapshot[$key] = $input->get($key, null, 'raw');
        }

        try {
            return $callback();
        } finally {
            foreach ($snapshot as $key => $value) {
                $input->set($key, $value);
            }
        }
    }

    /**
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return array<int, int>
     * @throws \Exception
     * @since 1.0.0
     */
    private function loadFilteredProductIds(int $categoryId, int $itemId, array $filters): array
    {
        $cacheKey = $categoryId . ':' . $itemId . ':' . serialize($filters);

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
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return void
     * @since 1.0.0
     */
    private function primeCategoryInput(int $categoryId, int $itemId, array $filters): void
    {
        $input = Factory::getApplication()->getInput();

        $input->set('id', $categoryId);
        $input->set('category_id', $categoryId);
        $input->set('filter_route', 1);

        if ($itemId > 0) {
            $input->set('Itemid', $itemId);
        }

        foreach ([
            'min_price', 'max_price', 'good_price',
            'min_width', 'max_width',
            'min_height', 'max_height',
            'min_depth', 'max_depth',
            'min_weight', 'max_weight',
        ] as $key) {
            $input->set($key, $filters[$key] ?? 0);
        }

        $input->set('manufacturers', $filters['manufacturers'] ?? []);
        $input->set('warehouses', $filters['warehouses'] ?? []);
        $input->set('ishop_fields', $filters['ishop_fields'] ?? []);
    }

    /**
     * @param   array     $filters  Нормализованные фильтры
     * @param   string    $facet    Имя фасетки
     * @param   int|null  $fieldId  ID характеристики
     *
     * @return array
     * @since 1.0.0
     */
    private function withoutFacet(array $filters, string $facet, ?int $fieldId = null): array
    {
        $result = $filters;

        if ($facet === 'price') {
            unset($result['min_price'], $result['max_price']);
        } elseif (in_array($facet, ['width', 'height', 'depth', 'weight'], true)) {
            unset($result['min_' . $facet], $result['max_' . $facet]);
        } elseif ($facet === 'ishop_field' && $fieldId !== null) {
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
     * @param   array<int, int>  $productIds  ID товаров
     * @param   string           $column      Колонка таблицы #__ishop_products
     *
     * @return array{min:int,max:int}
     * @throws \Exception
     * @since 1.0.0
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
     * @param   array<int, int>  $productIds  ID товаров
     *
     * @return array<int, int>
     * @throws \Exception
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
     * @param   array<int, int>  $productIds  ID товаров
     *
     * @return array<int, int>
     * @throws \Exception
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
     * @param   int    $categoryId  ID категории
     * @param   int    $itemId      ID пункта меню
     * @param   array  $filters     Нормализованные фильтры
     *
     * @return array<int, array>
     * @throws \Exception
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
            $productIds = $this->loadFilteredProductIds(
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
     * @param   int             $fieldId     ID характеристики
     * @param   array<int,int>  $productIds  ID товаров
     *
     * @return array{type?:string,min?:int,max?:int,values?:array<int|string, string>}
     * @throws \Exception
     * @since 1.0.0
     */
    private function getSingleFieldOption(int $fieldId, array $productIds): array
    {
        if (empty($productIds)) {
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
            [$min, $max] = array_pad(explode(',', (string) $row->values), 2, 0);

            return [
                'type' => 'range',
                'min'  => (int) round((float) $min),
                'max'  => (int) round((float) $max),
            ];
        }

        if ($type === 1) {
            $values = explode('||', (string) $row->values);
            $ids = explode('||', (string) $row->values_id);

            return [
                'type'   => 'list',
                'values' => count($ids) === count($values) ? array_combine($ids, $values) : [],
            ];
        }

        if ($type === 2) {
            return [
                'type' => 'boolean',
            ];
        }

        return [];
    }

    /**
     * @param   int  $categoryId  ID категории
     *
     * @return array<int, int>
     * @throws \Exception
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
