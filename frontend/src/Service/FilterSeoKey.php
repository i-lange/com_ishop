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

use Joomla\Utilities\ArrayHelper;

/**
 * Сервис нормализации параметров SEO-страниц фильтра.
 *
 * На клиентской части этот класс используется для построения того же
 * `filter_key`, который был рассчитан при сохранении записи в админке.
 * Это нужно, чтобы текущий state категории можно было надежно сопоставить
 * с записью в `#__ishop_filters`.
 *
 * Фильтр может быть сформирован из ЧПУ URL, query string, AJAX-перехода,
 * session state или формы модуля фильтра. Поэтому методам класса нельзя
 * доверять порядку и типам входных значений: они приводят данные к единому
 * каноническому виду перед построением ключа.
 *
 * Важно: frontend и backend версии класса должны оставаться логически
 * одинаковыми. Иначе сохраненная в админке SEO-запись может не совпасть
 * с фактической страницей фильтра на сайте.
 *
 * @since 1.0.0
 */
final class FilterSeoKey
{
    /**
     * Нормализует полный набор параметров фильтра.
     *
     * В результат попадают только параметры, которые реально участвуют
     * в SEO-совпадении:
     *
     * - `category_id` как положительный integer;
     * - список производителей `manufacturers`;
     * - список складов `warehouses`;
     * - выбранные характеристики `ishop_fields`;
     * - флаг наличия скидки `good_price`;
     * - диапазоны цены, габаритов и веса: `price`, `width`, `height`,
     *   `depth`, `weight`.
     *
     * Пустые значения (`0`, `null`, пустые строки, пустые массивы)
     * отбрасываются. Порядок ID и ключей приводится к стабильному виду,
     * чтобы одинаковые фильтры давали одинаковый ключ независимо от того,
     * как именно пользователь выбрал значения.
     *
     * @param   array  $data  Сырые данные фильтра из state модели или request.
     *
     * @return array Канонический массив значимых параметров фильтра.
     * @since 1.0.0
     */
    public static function normalize(array $data): array
    {
        $result = [];

        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId > 0) {
            $result['category_id'] = $categoryId;
        }

        $manufacturers = self::normalizeIds($data['manufacturers'] ?? []);
        if (!empty($manufacturers)) {
            $result['manufacturers'] = $manufacturers;
        }

        $warehouses = self::normalizeIds($data['warehouses'] ?? []);
        if (!empty($warehouses)) {
            $result['warehouses'] = $warehouses;
        }

        $fields = self::normalizeFields($data['ishop_fields'] ?? []);
        if (!empty($fields)) {
            $result['ishop_fields'] = $fields;
        }

        if ((int) ($data['good_price'] ?? 0) > 0) {
            $result['good_price'] = 1;
        }

        foreach (['price', 'width', 'height', 'depth', 'weight'] as $dimension) {
            $range = self::normalizeRange($data['min_' . $dimension] ?? 0, $data['max_' . $dimension] ?? 0);

            if ($range !== null) {
                $result['min_' . $dimension] = $range['min'];
                $result['max_' . $dimension] = $range['max'];
            }
        }

        return $result;
    }

    /**
     * Строит SHA-256 ключ для текущей комбинации фильтра.
     *
     * Метод использует `normalize()`, затем исключает `category_id`.
     * Категория проверяется отдельной колонкой таблицы `#__ishop_filters`,
     * поэтому в сам hash входят только выбранные параметры фильтра.
     *
     * JSON сериализуется без экранирования Unicode и слешей, чтобы ключ
     * был стабильным и читаемым при отладке исходной структуры.
     *
     * @param   array  $data  Сырые или уже нормализованные данные фильтра.
     *
     * @return string SHA-256 ключ комбинации фильтра.
     * @since 1.0.0
     */
    public static function build(array $data): string
    {
        $normalized = self::normalize($data);
        unset($normalized['category_id']);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Нормализует список ID.
     *
     * Метод принимает массив, строку с ID через запятую или одиночное
     * значение. JSON-массивы из сохраненных полей таблицы также поддерживаются.
     * Все элементы приводятся к integer, пустые значения удаляются, дубли
     * убираются, итоговый список сортируется по возрастанию.
     *
     * Это нужно, чтобы одинаковые наборы производителей или значений
     * характеристик не создавали разные ключи из-за порядка выбора.
     *
     * @param   mixed  $value  Сырые ID или список ID.
     *
     * @return array<int, int> Отсортированный список уникальных положительных ID.
     * @since 1.0.0
     */
    public static function normalizeIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                $value = [];
            } elseif ($value[0] === '[') {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : explode(',', $value);
            } else {
                $value = explode(',', $value);
            }
        } elseif (!is_array($value)) {
            $value = [$value];
        }

        $ids = array_values(array_filter(ArrayHelper::toInteger($value)));
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * Нормализует параметры фильтра по характеристикам.
     *
     * Поддерживаются три типа значений:
     *
     * - диапазон для числовых характеристик: `['min' => 10, 'max' => 20]`;
     * - массив ID значений для списочных характеристик;
     * - `1` для boolean-характеристик.
     *
     * Метод также умеет принимать JSON-строку, потому что сохраненные
     * записи из `#__ishop_filters` хранят характеристики в JSON.
     *
     * Пустые характеристики отбрасываются, ключи сортируются по ID,
     * списочные значения нормализуются через `normalizeIds()`.
     *
     * @param   mixed  $fields  Сырые данные `ishop_fields` или JSON-строка.
     *
     * @return array<string, mixed> Канонический массив характеристик.
     * @since 1.0.0
     */
    public static function normalizeFields(mixed $fields): array
    {
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            $fields = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($fields)) {
            return [];
        }

        $result = [];

        foreach ($fields as $fieldId => $value) {
            $fieldId = (int) $fieldId;

            if ($fieldId <= 0) {
                continue;
            }

            if (is_array($value) && (array_key_exists('min', $value) || array_key_exists('max', $value))) {
                $range = self::normalizeRange($value['min'] ?? 0, $value['max'] ?? 0);

                if ($range !== null) {
                    $result[(string) $fieldId] = $range;
                }

                continue;
            }

            if (is_array($value)) {
                $ids = self::normalizeIds($value);

                if (!empty($ids)) {
                    $result[(string) $fieldId] = $ids;
                }

                continue;
            }

            if ((int) $value > 0) {
                $result[(string) $fieldId] = 1;
            }
        }

        ksort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * Нормализует диапазон с минимальной и максимальной границей.
     *
     * Границы приводятся к положительным целым числам. Если обе границы
     * пустые, диапазон считается неактивным и возвращается `null`.
     *
     * Если заданы обе границы, но минимальная больше максимальной,
     * значения меняются местами. Так один и тот же диапазон не создает
     * разные ключи из-за порядка ввода.
     *
     * @param   mixed  $min  Сырая минимальная граница.
     * @param   mixed  $max  Сырая максимальная граница.
     *
     * @return array{min:int,max:int}|null Нормализованный диапазон или `null`.
     * @since 1.0.0
     */
    public static function normalizeRange(mixed $min, mixed $max): ?array
    {
        $min = self::normalizePositiveInteger($min);
        $max = self::normalizePositiveInteger($max);

        if ($min === 0 && $max === 0) {
            return null;
        }

        if ($min > 0 && $max > 0 && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Приводит значение к положительному integer.
     *
     * Объекты и массивы не считаются валидными числовыми значениями
     * и превращаются в `0`. Скалярные значения приводятся к float,
     * округляются и ограничиваются снизу нулем.
     *
     * @param   mixed  $value  Сырое значение фильтра.
     *
     * @return int Положительное целое число или `0`.
     * @since 1.0.0
     */
    private static function normalizePositiveInteger(mixed $value): int
    {
        if (is_array($value) || is_object($value)) {
            return 0;
        }

        return max(0, (int) round((float) $value));
    }
}
