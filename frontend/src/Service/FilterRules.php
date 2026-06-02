<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Специальные правила обработки URL-адресов в компоненте com_ishop
 * @since 1.0.0
 */
class FilterRules implements RulesInterface
{
    private const RESERVED_SEGMENTS = [
        'brand',
        'sale',
        'price',
        'warehouse',
        'width',
        'height',
        'depth',
        'weight',
    ];

    private const RANGE_FILTERS = [
        'price'  => ['min_price', 'max_price'],
        'width'  => ['min_width', 'max_width'],
        'height' => ['min_height', 'max_height'],
        'depth'  => ['min_depth', 'max_depth'],
        'weight' => ['min_weight', 'max_weight'],
    ];

    /**
     * Роутер, к которому привязано это правило
     * @var RouterView
     * @since 1.0.0
     */
    protected ?RouterView $router;

    /** @var array<int, string|null> */
    private array $manufacturerAliases = [];

    /** @var array<string, int|null> */
    private array $manufacturerIds = [];

    /** @var array<int, string|null> */
    private array $warehouseAliases = [];

    /** @var array<string, int|null> */
    private array $warehouseIds = [];

    /** @var array<int, object|null> */
    private array $fieldsById = [];

    /** @var array<string, object|null> */
    private array $fieldsByAlias = [];

    /** @var array<string, string|null> */
    private array $valueAliases = [];

    /** @var array<string, int|null> */
    private array $valueIds = [];

    /** @var array<int, array<int, int>> */
    private array $categoryFieldIds = [];

    /**
     * Конструктор
     * @param RouterView $router Роутер
     * @since 1.0.0
     */
    public function __construct(?RouterView $router = null)
    {
        $this->router = $router;
    }

    /**
     * Заглушка метода для выполнения требований интерфейса
     * @param array &$query Массив запроса
     * @return void
     * @since 1.0.0
     */
    public function preprocess(&$query)
    {
    }

    /**
     * Разбор URL-адреса
     * @param array &$segments Сегменты URL для разбора
     * @param array &$vars Параметры, получаемые в результате разбора
     * @return void
     * @since 1.0.0
     */
    public function parse(&$segments, &$vars)
    {
        if (empty($segments)) {
            return;
        }

        $parsed = false;

        while (!empty($segments)) {
            $segment = array_shift($segments);
            $parts = explode(':', $segment);
            $name = array_shift($parts);

            if ($name === '' || empty($parts)) {
                array_unshift($segments, $segment);
                break;
            }

            $valid = match ($name) {
                'brand' => $this->parseAliasList($parts, 'getManufacturerId', $vars, 'manufacturers'),
                'warehouse' => $this->parseAliasList($parts, 'getWarehouseId', $vars, 'warehouses'),
                'sale' => $this->parseSale($parts, $vars),
                'price', 'width', 'height', 'depth', 'weight' => $this->parseRange($name, $parts, $vars),
                default => $this->parseField($name, $parts, $vars),
            };

            if (!$valid) {
                array_unshift($segments, $segment);
                break;
            }

            $parsed = true;
        }

        if ($parsed) {
            $vars['filter_route'] = 1;
        }
    }

    /**
     * Составляем ЧПУ URL только из необходимых сегментов
     * @param array &$query Параметры, которые нужно обработать
     * @param array &$segments Сегменты URL для создания ЧПУ адреса
     * @return void
     * @since 1.0.0
     */
    public function build(&$query, &$segments)
    {
        $filterSegments = self::buildFilterSegments($query);

        foreach ($filterSegments as $segment) {
            $segments[] = $segment;
        }
    }

    /**
     * Собирает route-ссылку категории с SEO-секциями фильтра.
     *
     * @param   int    $categoryId  ID категории
     * @param   array  $filters     Параметры фильтра
     * @param   int    $itemId      ID пункта меню
     *
     * @return string
     * @since 1.0.0
     */
    public static function getFilterRoute(int $categoryId, array $filters = [], int $itemId = 0): string
    {
        $query = array_merge([
            'option' => 'com_ishop',
            'view'   => 'category',
            'id'     => $categoryId,
        ], $filters);

        if ($itemId > 0) {
            $query['Itemid'] = $itemId;
        }

        $link = 'index.php?' . http_build_query($query, '', '&');

        return Route::_($link, false);
    }

    /**
     * Собирает базовую route-ссылку категории без SEO-секций фильтра.
     *
     * @param   int  $categoryId  ID категории
     * @param   int  $itemId      ID пункта меню
     *
     * @return string
     * @since 1.0.0
     */
    public static function getBaseCategoryRoute(int $categoryId, int $itemId = 0): string
    {
        $query = [
            'option' => 'com_ishop',
            'view'   => 'category',
            'id'     => $categoryId,
        ];

        if ($itemId > 0) {
            $query['Itemid'] = $itemId;
        }

        return Route::_('index.php?' . http_build_query($query, '', '&'), false);
    }

    /**
     * Собирает SEO-сегменты фильтра и удаляет обработанные параметры из query.
     *
     * @param   array  &$query  Query параметры
     *
     * @return array<int, string>
     * @since 1.0.0
     */
    public static function buildFilterSegments(array &$query): array
    {
        $builder = new self();

        return $builder->buildSegments($query);
    }

    /**
     * Нормализует входные параметры фильтра для route builder и модели.
     *
     * @param   array  $filters  Данные фильтра
     *
     * @return array
     * @since 1.0.0
     */
    public static function normalizeFilterInput(array $filters): array
    {
        $result = [];

        foreach (self::RANGE_FILTERS as [$minKey, $maxKey]) {
            $min = self::normalizePositiveInteger($filters[$minKey] ?? 0);
            $max = self::normalizePositiveInteger($filters[$maxKey] ?? 0);

            if ($min > 0) {
                $result[$minKey] = $min;
            }

            if ($max > 0) {
                $result[$maxKey] = $max;
            }
        }

        $goodPrice = (int) ($filters['good_price'] ?? 0);
        if ($goodPrice > 0) {
            $result['good_price'] = 1;
        }

        foreach (['manufacturers', 'warehouses'] as $key) {
            $ids = $filters[$key] ?? [];
            if (!is_array($ids)) {
                $ids = [$ids];
            }

            $ids = array_values(array_filter(ArrayHelper::toInteger($ids)));
            $ids = array_values(array_unique($ids));

            if (!empty($ids)) {
                $result[$key] = $ids;
            }
        }

        if (!empty($filters['ishop_fields']) && is_array($filters['ishop_fields'])) {
            $fields = [];

            foreach ($filters['ishop_fields'] as $fieldId => $value) {
                $fieldId = (int) $fieldId;

                if ($fieldId <= 0) {
                    continue;
                }

                if (is_array($value)) {
                    if (isset($value['min']) || isset($value['max'])) {
                        $min = self::normalizePositiveInteger($value['min'] ?? 0);
                        $max = self::normalizePositiveInteger($value['max'] ?? 0);

                        if ($min > 0 || $max > 0) {
                            $fields[$fieldId] = ['min' => $min, 'max' => $max];
                        }
                    } else {
                        $ids = array_values(array_filter(ArrayHelper::toInteger($value)));
                        $ids = array_values(array_unique($ids));

                        if (!empty($ids)) {
                            $fields[$fieldId] = $ids;
                        }
                    }
                } elseif ((int) $value > 0) {
                    $fields[$fieldId] = 1;
                }
            }

            if (!empty($fields)) {
                $result['ishop_fields'] = $fields;
            }
        }

        return $result;
    }

    /**
     * Сбрасывает user-state фильтров категории.
     *
     * @param   int  $categoryId  ID категории
     * @param   int  $itemId      ID пункта меню
     *
     * @return void
     * @since 1.0.0
     */
    public static function clearCategoryFilterState(int $categoryId, int $itemId = 0): void
    {
        $app = Factory::getApplication();
        $prefix = 'com_ishop.category.filter.' . $categoryId . ':' . $itemId . '.';
        $keys = [
            'tag',
            'min_price',
            'max_price',
            'good_price',
            'min_width',
            'max_width',
            'min_height',
            'max_height',
            'min_depth',
            'max_depth',
            'min_weight',
            'max_weight',
            'ishop_fields',
            'manufacturers',
            'warehouses',
            'manufacturer_id',
            'warehouse_id',
        ];

        foreach ($keys as $key) {
            $app->setUserState($prefix . $key, null);
        }
    }

    /**
     * @param array &$query
     * @return array<int, string>
     */
    private function buildSegments(array &$query): array
    {
        $segments = [];

        $manufacturerIds = [];
        if (isset($query['manufacturers']) && is_array($query['manufacturers'])) {
            $manufacturerIds = $query['manufacturers'];
        } elseif (isset($query['manufacturer_id']) && (int) $query['manufacturer_id'] > 0) {
            $manufacturerIds = [(int) $query['manufacturer_id']];
        }

        $manufacturerAliases = $this->getOrderedAliasesByIds('#__ishop_manufacturers', $manufacturerIds);
        if (!empty($manufacturerAliases)) {
            $segments[] = 'brand:' . implode(':', $manufacturerAliases);
        }
        unset($query['manufacturers'], $query['manufacturer_id']);

        if (!empty($query['good_price'])) {
            $segments[] = 'sale:yes';
        }
        unset($query['good_price']);

        foreach (self::RANGE_FILTERS as $segmentName => [$minKey, $maxKey]) {
            $range = $this->normalizeRangeValues($query[$minKey] ?? 0, $query[$maxKey] ?? 0);

            if ($range !== null) {
                $segments[] = $segmentName . ':' . $range[0] . ':' . $range[1];
            }

            unset($query[$minKey], $query[$maxKey]);
        }

        $warehouseAliases = $this->getOrderedAliasesByIds('#__ishop_warehouses', $query['warehouses'] ?? []);
        if (!empty($warehouseAliases)) {
            $segments[] = 'warehouse:' . implode(':', $warehouseAliases);
        }
        unset($query['warehouses'], $query['warehouse_id']);

        if (!empty($query['ishop_fields']) && is_array($query['ishop_fields'])) {
            $segments = array_merge($segments, $this->buildFieldSegments($query['ishop_fields']));
        }
        unset($query['ishop_fields']);

        unset($query['filter_route']);

        return $segments;
    }

    /**
     * @param array $fields
     * @return array<int, string>
     */
    private function buildFieldSegments(array $fields): array
    {
        $items = [];

        foreach ($fields as $fieldId => $value) {
            $field = $this->getFieldById((int) $fieldId);

            if (!$field || !$this->isAllowedFieldAlias($field->alias)) {
                continue;
            }

            if ((int) $field->type === 0 && is_array($value)) {
                $range = $this->normalizeRangeValues($value['min'] ?? 0, $value['max'] ?? 0);

                if ($range !== null) {
                    $items[] = [
                        'ordering' => (int) $field->ordering,
                        'alias'    => (string) $field->alias,
                        'segment'  => $field->alias . ':' . $range[0] . ':' . $range[1],
                    ];
                }
            } elseif ((int) $field->type === 1 && is_array($value)) {
                $aliases = $this->getOrderedValueAliases((int) $field->id, $value);

                if (!empty($aliases)) {
                    $items[] = [
                        'ordering' => (int) $field->ordering,
                        'alias'    => (string) $field->alias,
                        'segment'  => $field->alias . ':' . implode(':', $aliases),
                    ];
                }
            } elseif ((int) $field->type === 2 && (int) $value > 0) {
                $items[] = [
                    'ordering' => (int) $field->ordering,
                    'alias'    => (string) $field->alias,
                    'segment'  => $field->alias . ':yes',
                ];
            }
        }

        usort(
            $items,
            static fn($a, $b) => [$a['ordering'], $a['alias']] <=> [$b['ordering'], $b['alias']]
        );

        return array_column($items, 'segment');
    }

    /**
     * @param array $aliases
     * @param string $method
     * @param array $vars
     * @param string $key
     * @return bool
     */
    private function parseAliasList(array $aliases, string $method, array &$vars, string $key): bool
    {
        $ids = [];

        foreach ($aliases as $alias) {
            if ($alias === '') {
                return false;
            }

            $id = $this->{$method}($alias);
            if (!$id) {
                return false;
            }

            $ids[] = $id;
        }

        $ids = array_values(array_unique(ArrayHelper::toInteger($ids)));

        if (empty($ids)) {
            return false;
        }

        sort($ids, SORT_NUMERIC);
        $vars[$key] = $ids;

        return true;
    }

    /**
     * @param array $parts
     * @param array $vars
     * @return bool
     */
    private function parseSale(array $parts, array &$vars): bool
    {
        if (count($parts) !== 1 || $parts[0] !== 'yes') {
            return false;
        }

        $vars['good_price'] = 1;

        return true;
    }

    /**
     * @param string $name
     * @param array $parts
     * @param array $vars
     * @return bool
     */
    private function parseRange(string $name, array $parts, array &$vars): bool
    {
        if (count($parts) !== 2 || !isset(self::RANGE_FILTERS[$name])) {
            return false;
        }

        $range = $this->parseRangeValues($parts[0], $parts[1]);
        if ($range === null) {
            return false;
        }

        [$minKey, $maxKey] = self::RANGE_FILTERS[$name];
        $vars[$minKey] = $range[0];
        $vars[$maxKey] = $range[1];

        return true;
    }

    /**
     * @param string $alias
     * @param array $parts
     * @param array $vars
     * @return bool
     */
    private function parseField(string $alias, array $parts, array &$vars): bool
    {
        if (!$this->isAllowedFieldAlias($alias)) {
            return false;
        }

        $field = $this->getFieldByAlias($alias);
        if (!$field) {
            return false;
        }

        $fieldId = (int) $field->id;
        $categoryId = isset($vars['id']) ? (int) $vars['id'] : 0;

        if ($categoryId > 0 && !$this->isFieldEnabledForCategory($fieldId, $categoryId)) {
            return false;
        }

        $vars['ishop_fields'] ??= [];

        if ((int) $field->type === 0) {
            if (count($parts) !== 2) {
                return false;
            }

            $range = $this->parseRangeValues($parts[0], $parts[1]);
            if ($range === null) {
                return false;
            }

            $vars['ishop_fields'][$fieldId] = ['min' => $range[0], 'max' => $range[1]];

            return true;
        }

        if ((int) $field->type === 1) {
            $ids = [];
            foreach ($parts as $valueAlias) {
                if ($valueAlias === '') {
                    return false;
                }

                $id = $this->getValueId($fieldId, $valueAlias);
                if (!$id) {
                    return false;
                }

                $ids[] = $id;
            }

            $ids = array_values(array_unique(ArrayHelper::toInteger($ids)));
            sort($ids, SORT_NUMERIC);
            $vars['ishop_fields'][$fieldId] = $ids;

            return !empty($ids);
        }

        if ((int) $field->type === 2) {
            if (count($parts) !== 1 || $parts[0] !== 'yes') {
                return false;
            }

            $vars['ishop_fields'][$fieldId] = 1;

            return true;
        }

        return false;
    }

    /**
     * @param mixed $min
     * @param mixed $max
     * @return array<int, int>|null
     */
    private function parseRangeValues(mixed $min, mixed $max): ?array
    {
        if (!is_scalar($min) || !is_scalar($max)) {
            return null;
        }

        $min = (string) $min;
        $max = (string) $max;

        if (!preg_match('/^\d+$/', $min) || !preg_match('/^\d+$/', $max)) {
            return null;
        }

        $min = (int) $min;
        $max = (int) $max;

        if ($min === 0 && $max === 0) {
            return null;
        }

        if ($min > 0 && $max > 0 && $min > $max) {
            return null;
        }

        return [$min, $max];
    }

    /**
     * @param mixed $min
     * @param mixed $max
     * @return array<int, int>|null
     */
    private function normalizeRangeValues(mixed $min, mixed $max): ?array
    {
        $min = self::normalizePositiveInteger($min);
        $max = self::normalizePositiveInteger($max);

        if ($min === 0 && $max === 0) {
            return null;
        }

        if ($min > 0 && $max > 0 && $min > $max) {
            return null;
        }

        return [$min, $max];
    }

    /**
     * @param mixed $value
     * @return int
     */
    private static function normalizePositiveInteger(mixed $value): int
    {
        if (is_array($value) || is_object($value)) {
            return 0;
        }

        return max(0, (int) round((float) $value));
    }

    /**
     * Возвращает алиасы записей в каноническом порядке ordering, alias.
     *
     * @param   string  $table  Таблица сущности
     * @param   array   $ids    ID записей
     *
     * @return array<int, string>
     */
    private function getOrderedAliasesByIds(string $table, array $ids): array
    {
        $ids = array_values(array_filter(ArrayHelper::toInteger($ids)));
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('alias'))
            ->from($db->quoteName($table))
            ->where($db->quoteName('alias') . ' <> ' . $db->quote(''))
            ->whereIn($db->quoteName('id'), $ids)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('alias') . ' ASC');

        return array_values(array_filter((array) $db->setQuery($query)->loadColumn()));
    }

    /**
     * Возвращает алиасы значений характеристики в каноническом порядке ordering, alias.
     *
     * @param   int    $fieldId  ID характеристики
     * @param   array  $ids      ID значений
     *
     * @return array<int, string>
     */
    private function getOrderedValueAliases(int $fieldId, array $ids): array
    {
        $ids = array_values(array_filter(ArrayHelper::toInteger($ids)));
        $ids = array_values(array_unique($ids));

        if ($fieldId <= 0 || empty($ids)) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('alias'))
            ->from($db->quoteName('#__ishop_values'))
            ->where($db->quoteName('alias') . ' <> ' . $db->quote(''))
            ->where($db->quoteName('field_id') . ' = :field_id')
            ->whereIn($db->quoteName('id'), $ids)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('alias') . ' ASC')
            ->bind(':field_id', $fieldId, ParameterType::INTEGER);

        return array_values(array_filter((array) $db->setQuery($query)->loadColumn()));
    }

    /**
     * @param string $alias
     * @return bool
     */
    private function isAllowedFieldAlias(string $alias): bool
    {
        return $alias !== '' && !in_array($alias, self::RESERVED_SEGMENTS, true);
    }


    /**
     * Проверяет, включена ли характеристика в фильтр текущей категории.
     *
     * @param   int  $fieldId     ID характеристики
     * @param   int  $categoryId  ID категории
     *
     * @return bool
     */
    private function isFieldEnabledForCategory(int $fieldId, int $categoryId): bool
    {
        if ($fieldId <= 0 || $categoryId <= 0) {
            return false;
        }

        if (!array_key_exists($categoryId, $this->categoryFieldIds)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_ishop'))
                ->bind(':id', $categoryId, ParameterType::INTEGER);

            $params = new Registry((string) $db->setQuery($query)->loadResult());
            $this->categoryFieldIds[$categoryId] = ArrayHelper::toInteger((array) $params->get('filter_fields', []));
        }

        return in_array($fieldId, $this->categoryFieldIds[$categoryId], true);
    }

    /**
     * Получение алиаса производителя по ID
     *
     * @param   int  $id  Идентификатор производителя
     *
     * @return string|null Алиас производителя или null
     * @since 1.0.0
     */
    private function getManufacturerAlias(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        if (!array_key_exists($id, $this->manufacturerAliases)) {
            $this->manufacturerAliases[$id] = $this->getAliasById('#__ishop_manufacturers', $id);
        }

        return $this->manufacturerAliases[$id];
    }

    /**
     * Получение ID производителя по алиасу
     *
     * @param   string  $alias  Алиас производителя
     *
     * @return int|null Идентификатор производителя или null
     * @since 1.0.0
     */
    private function getManufacturerId(string $alias): ?int
    {
        if ($alias === '') {
            return null;
        }

        if (!array_key_exists($alias, $this->manufacturerIds)) {
            $this->manufacturerIds[$alias] = $this->getIdByAlias('#__ishop_manufacturers', $alias);
        }

        return $this->manufacturerIds[$alias];
    }

    /**
     * @param int $id
     * @return string|null
     */
    private function getWarehouseAlias(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        if (!array_key_exists($id, $this->warehouseAliases)) {
            $this->warehouseAliases[$id] = $this->getAliasById('#__ishop_warehouses', $id);
        }

        return $this->warehouseAliases[$id];
    }

    /**
     * @param string $alias
     * @return int|null
     */
    private function getWarehouseId(string $alias): ?int
    {
        if ($alias === '') {
            return null;
        }

        if (!array_key_exists($alias, $this->warehouseIds)) {
            $this->warehouseIds[$alias] = $this->getIdByAlias('#__ishop_warehouses', $alias);
        }

        return $this->warehouseIds[$alias];
    }

    /**
     * @param int $id
     * @return object|null
     */
    private function getFieldById(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        if (!array_key_exists($id, $this->fieldsById)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('alias'),
                    $db->quoteName('type'),
                    $db->quoteName('ordering'),
                ])
                ->from($db->quoteName('#__ishop_fields'))
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('state') . ' = 1')
                ->bind(':id', $id, ParameterType::INTEGER);

            $this->fieldsById[$id] = $db->setQuery($query)->loadObject() ?: null;
        }

        return $this->fieldsById[$id];
    }

    /**
     * @param string $alias
     * @return object|null
     */
    private function getFieldByAlias(string $alias): ?object
    {
        if ($alias === '') {
            return null;
        }

        if (!array_key_exists($alias, $this->fieldsByAlias)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('alias'),
                    $db->quoteName('type'),
                    $db->quoteName('ordering'),
                ])
                ->from($db->quoteName('#__ishop_fields'))
                ->where($db->quoteName('alias') . ' = :alias')
                ->where($db->quoteName('state') . ' = 1')
                ->bind(':alias', $alias);

            $this->fieldsByAlias[$alias] = $db->setQuery($query)->loadObject() ?: null;
        }

        return $this->fieldsByAlias[$alias];
    }

    /**
     * @param int $fieldId
     * @param int $valueId
     * @return string|null
     */
    private function getValueAlias(int $fieldId, int $valueId): ?string
    {
        if ($fieldId <= 0 || $valueId <= 0) {
            return null;
        }

        $key = $fieldId . ':' . $valueId;
        if (!array_key_exists($key, $this->valueAliases)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('alias'))
                ->from($db->quoteName('#__ishop_values'))
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('field_id') . ' = :field_id')
                ->bind(':id', $valueId, ParameterType::INTEGER)
                ->bind(':field_id', $fieldId, ParameterType::INTEGER);

            $alias = $db->setQuery($query)->loadResult();
            $this->valueAliases[$key] = $alias ? (string) $alias : null;
        }

        return $this->valueAliases[$key];
    }

    /**
     * @param int $fieldId
     * @param string $alias
     * @return int|null
     */
    private function getValueId(int $fieldId, string $alias): ?int
    {
        if ($fieldId <= 0 || $alias === '') {
            return null;
        }

        $key = $fieldId . ':' . $alias;
        if (!array_key_exists($key, $this->valueIds)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__ishop_values'))
                ->where($db->quoteName('field_id') . ' = :field_id')
                ->where($db->quoteName('alias') . ' = :alias')
                ->bind(':field_id', $fieldId, ParameterType::INTEGER)
                ->bind(':alias', $alias);

            $id = (int) $db->setQuery($query)->loadResult();
            $this->valueIds[$key] = $id > 0 ? $id : null;
        }

        return $this->valueIds[$key];
    }

    /**
     * @param string $table
     * @param int $id
     * @return string|null
     */
    private function getAliasById(string $table, int $id): ?string
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('alias'))
                ->from($db->quoteName($table))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);

            $alias = $db->setQuery($query)->loadResult();

            return $alias ? (string) $alias : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param string $table
     * @param string $alias
     * @return int|null
     */
    private function getIdByAlias(string $table, string $alias): ?int
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName($table))
                ->where($db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias);

            $id = (int) $db->setQuery($query)->loadResult();

            return $id > 0 ? $id : null;
        } catch (\Exception) {
            return null;
        }
    }
}
