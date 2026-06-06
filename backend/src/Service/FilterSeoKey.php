<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;

/**
 * Сервис нормализации параметров SEO-страниц фильтра.
 *
 * Один и тот же фильтр может прийти из разных мест в разном виде:
 * из формы админки, из AJAX-запроса, из ЧПУ URL или из state модели категории.
 * Например, порядок производителей и значений характеристик может отличаться,
 * пустые значения могут быть переданы как `0`, `null`, пустая строка или пустой массив.
 *
 * Этот класс приводит такие данные к единому каноническому виду и строит
 * стабильный `filter_key`, который используется для поиска записи в
 * `#__ishop_filters` и для защиты от дублей в админке.
 *
 * Важно: backend и frontend версии класса должны оставаться логически
 * одинаковыми. Иначе запись, сохраненная в админке, может не совпасть
 * с фильтром на клиентской части.
 *
 * @since 1.0.0
 */
final class FilterSeoKey
{
    /**
     * Нормализует полный набор параметров фильтра.
     *
     * Метод принимает "сырые" данные фильтра и возвращает массив,
     * пригодный для сериализации и построения ключа. В результат попадают
     * только значимые параметры:
     *
     * - `category_id` приводится к положительному integer;
     * - `manufacturers` превращается в отсортированный массив уникальных ID;
     * - `ishop_fields` нормализуется по типам значений;
     * - диапазоны `width`, `height`, `depth`, `weight` приводятся к парам
     *   `min/max`;
     * - полностью пустые значения удаляются.
     *
     * `category_id` сохраняется в нормализованном результате для валидации
     * и запросов к БД, но не входит в hash `filter_key`: категория хранится
     * отдельной колонкой и участвует в уникальности вместе с ключом.
     *
     * @param   array  $data  Сырые данные фильтра из формы, request или state.
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

        $fields = self::normalizeFields($data['ishop_fields'] ?? []);
        if (!empty($fields)) {
            $result['ishop_fields'] = $fields;
        }

        foreach (['width', 'height', 'depth', 'weight'] as $dimension) {
            $range = self::normalizeRange($data['min_' . $dimension] ?? 0, $data['max_' . $dimension] ?? 0);

            if ($range !== null) {
                $result['min_' . $dimension] = $range['min'];
                $result['max_' . $dimension] = $range['max'];
            }
        }

        return $result;
    }

    /**
     * Строит стабильный hash для комбинации фильтра.
     *
     * Метод сначала вызывает `normalize()`, затем удаляет `category_id`
     * и сериализует оставшиеся параметры в JSON без экранирования Unicode
     * и слешей. От полученной строки считается SHA-256.
     *
     * Такой ключ не зависит от порядка выбранных производителей,
     * характеристик или значений, но меняется при любом смысловом изменении
     * комбинации фильтра.
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
     * Нормализует список идентификаторов.
     *
     * Поддерживаются значения из разных источников:
     *
     * - массив ID из формы;
     * - строка с ID через запятую;
     * - одиночное скалярное значение.
     *
     * Все элементы приводятся к integer, нулевые и отрицательные значения
     * удаляются, дубли убираются, итоговый массив сортируется по возрастанию.
     * Это гарантирует, что `[2, 1]`, `[1, 2]` и `"2,1"` дадут один результат.
     *
     * @param   mixed  $value  Сырые ID или список ID.
     *
     * @return array<int, int> Отсортированный список уникальных положительных ID.
     * @since 1.0.0
     */
    public static function normalizeIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        } elseif (!is_array($value)) {
            $value = [$value];
        }

        $ids = array_values(array_filter(ArrayHelper::toInteger($value)));
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * Нормализует фильтр по характеристикам товаров.
     *
     * Характеристики поддерживают три формата:
     *
     * - числовые характеристики: `field_id => ['min' => 10, 'max' => 20]`;
     * - списочные характеристики: `field_id => [value_id_1, value_id_2]`;
     * - boolean-характеристики: `field_id => 1`.
     *
     * Метод также принимает JSON-строку, потому что в таблице
     * `#__ishop_filters` характеристики хранятся сериализованными.
     *
     * Пустые значения удаляются. Ключи характеристик сортируются по ID,
     * а значения списочных характеристик проходят через `normalizeIds()`.
     * Благодаря этому порядок выбора значений в форме не влияет на
     * итоговый `filter_key`.
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
     * Нормализует диапазон `min/max`.
     *
     * Значения приводятся к положительным целым числам. Если обе границы
     * равны нулю, диапазон считается невыбранным и метод возвращает `null`.
     *
     * Если обе границы заданы, но пользователь передал их в обратном порядке,
     * метод меняет значения местами. Это защищает от создания разных ключей
     * для одной и той же смысловой комбинации.
     *
     * @param   mixed  $min  Сырая минимальная граница диапазона.
     * @param   mixed  $max  Сырая максимальная граница диапазона.
     *
     * @return array{min:int,max:int}|null Нормализованный диапазон или `null`, если он пустой.
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
     * Приводит произвольное значение к положительному целому числу.
     *
     * Массивы и объекты не являются допустимыми числовыми значениями,
     * поэтому для них возвращается `0`. Скалярные значения приводятся
     * к float, округляются и ограничиваются снизу нулем.
     *
     * @param   mixed  $value  Сырое значение из формы, request или JSON.
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
