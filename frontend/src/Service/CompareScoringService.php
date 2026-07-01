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

/**
 * Рассчитывает лучшие значения и рейтинг товаров в списке сравнения.
 *
 * @since 1.0.24
 */
class CompareScoringService
{
    /**
     * Системные поля габаритов и веса, которые не участвуют в рейтинге.
     *
     * @var string[]
     * @since 1.0.24
     */
    private array $excludedFields = [
        'width',
        'height',
        'depth',
        'weight',
        'width_pkg',
        'height_pkg',
        'depth_pkg',
        'weight_pkg',
    ];

    /**
     * Рассчитывает победы, лучшие ячейки и итоговый рейтинг товаров.
     *
     * @param object $category Данные активной категории сравнения
     *
     * @return void
     *
     * @since 1.0.24
     */
    public function score(object $category): void
    {
        if (empty($category->products) || !is_array($category->products)) {
            return;
        }

        foreach ($category->products as $product) {
            $product->compare_wins = 0;
            $product->compare_rating = 0;
        }

        if (count($category->products) < 2 || empty($category->groups) || !is_array($category->groups)) {
            return;
        }

        $productIds = array_map('intval', array_keys($category->products));

        foreach ($category->groups as $group) {
            if (empty($group->fields) || !is_array($group->fields)) {
                continue;
            }

            foreach ($group->fields as $fieldKey => $field) {
                if (!$this->isComparableField($fieldKey, $field)) {
                    continue;
                }

                $this->scoreField($field, $category->products, $productIds);
            }
        }

        $this->assignRatings($category->products);
    }

    /**
     * Проверяет, должна ли характеристика участвовать в сравнении.
     *
     * @param int|string $fieldKey Ключ характеристики в матрице сравнения
     * @param object     $field    Данные характеристики
     *
     * @return bool
     *
     * @since 1.0.24
     */
    private function isComparableField(int|string $fieldKey, object $field): bool
    {
        if (empty($field->products) || !is_array($field->products)) {
            return false;
        }

        if (in_array((string)$fieldKey, $this->excludedFields, true)) {
            return false;
        }

        return in_array((int)($field->compare ?? 0), [-1, 1], true);
    }

    /**
     * Отмечает лучшие значения одной характеристики и добавляет победы товарам.
     *
     * @param object $field      Данные характеристики
     * @param array  $products   Товары активной категории
     * @param array  $productIds Идентификаторы товаров в порядке сравнения
     *
     * @return void
     *
     * @since 1.0.24
     */
    private function scoreField(object $field, array $products, array $productIds): void
    {
        $scores = [];

        foreach ($productIds as $productId) {
            if (isset($field->products[$productId])) {
                $field->products[$productId]->is_best = false;
            }

            $score = $this->normaliseValue($field, $field->products[$productId] ?? null);

            if ($score !== null) {
                $scores[$productId] = $score;
            }
        }

        if (empty($scores)) {
            return;
        }

        $best = (int)$field->compare === -1 ? min($scores) : max($scores);

        foreach ($scores as $productId => $score) {
            if ((string)$score !== (string)$best) {
                continue;
            }

            if (isset($field->products[$productId])) {
                $field->products[$productId]->is_best = true;
            }

            if (isset($products[$productId])) {
                $products[$productId]->compare_wins++;
            }
        }
    }

    /**
     * Приводит значение ячейки к числу для сравнения или возвращает null для пустых значений.
     *
     * @param object      $field Данные характеристики
     * @param object|null $value Значение характеристики товара
     *
     * @return float|int|null
     *
     * @since 1.0.24
     */
    private function normaliseValue(object $field, ?object $value): float|int|null
    {
        if ($value === null) {
            return null;
        }

        if (!empty($field->is_system)) {
            return $this->normaliseSystemValue((string)($field->system_key ?? ''), $value);
        }

        return match ((int)($field->type ?? -1)) {
            0 => $this->normaliseNumber($value),
            1 => isset($value->raw_value) && (float)$value->raw_value > 0 ? (int)($value->weight ?? 0) : null,
            2 => isset($value->raw_value) ? ((float)$value->raw_value > 0 ? 1 : 0) : null,
            default => null,
        };
    }

    /**
     * Нормализует системные значения сравнения.
     *
     * @param string $key   Ключ системного поля
     * @param object $value Значение товара
     *
     * @return float|int|null
     *
     * @since 1.0.24
     */
    private function normaliseSystemValue(string $key, object $value): float|int|null
    {
        if (!isset($value->raw_value)) {
            return null;
        }

        $rawValue = (float)$value->raw_value;

        return match ($key) {
            'price' => $rawValue > 0 ? $rawValue : null,
            'discount' => $rawValue,
            'available' => $rawValue > 0 ? 1 : 0,
            default => null,
        };
    }

    /**
     * Нормализует числовые характеристики.
     *
     * @param object $value Значение товара
     *
     * @return float|null
     *
     * @since 1.0.24
     */
    private function normaliseNumber(object $value): ?float
    {
        if (!isset($value->raw_value) || $value->raw_value === '') {
            return null;
        }

        return (float)$value->raw_value;
    }

    /**
     * Назначает итоговый рейтинг по числу побед.
     * Если есть уникальный лидер, второе место получает рейтинг 1.
     * Если лидеров несколько, рейтинг 1 получают только товары с максимумом побед.
     *
     * @param array $products Товары активной категории
     *
     * @return void
     *
     * @since 1.0.24
     */
    private function assignRatings(array $products): void
    {
        $wins = array_map(
            static fn ($product): int => (int)($product->compare_wins ?? 0),
            $products
        );

        $winLevels = array_values(array_unique(array_filter($wins, static fn (int $winCount): bool => $winCount > 0)));
        rsort($winLevels, SORT_NUMERIC);

        $maxWins = $winLevels[0] ?? 0;

        if ($maxWins <= 0) {
            return;
        }

        $secondWins = $winLevels[1] ?? null;
        $leaderCount = count(array_filter($wins, static fn (int $winCount): bool => $winCount === $maxWins));

        foreach ($products as $product) {
            $productWins = (int)($product->compare_wins ?? 0);

            if ($productWins <= 0) {
                $product->compare_rating = 0;
                continue;
            }

            if ($leaderCount > 1) {
                $product->compare_rating = $productWins === $maxWins ? 1 : 0;
                continue;
            }

            if ($productWins === $maxWins) {
                $product->compare_rating = 2;
                continue;
            }

            $product->compare_rating = ($secondWins !== null && $productWins === $secondWins) ? 1 : 0;
        }
    }
}
