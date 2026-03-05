<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Класс helper
 * @since 1.0.0
 */
class FieldHelper
{
    /**
     * Метод последовательно пересоздает таблицы
     * для фильтрации товаров по каждой категории,
     *
     * @return  bool удачно или нет
     * @since 1.0.0
     */
    public static function updateFilterForCategories()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Получаем список всех активных категорий,
        // а также параметры этих категорий
        $query
            ->select([
                $db->quoteName('id'),
                $db->quoteName('params'),
            ])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('published') . ' = ' . 1)
            ->where($db->quoteName('extension') . ' = ' . $db->q('com_ishop'));
        $db->setQuery($query);
        $categories = $db->loadObjectList();

        if (!$categories) {
            return false;
        }

        // Для каждой категории отдельная таблица фильтрации
        foreach ($categories as $category) {
            // Параметры категории, преобразуем в массив
            $category->params = (new Registry($category->params))->toArray();

            // Проверяем, заполнен ли список характеристик для фильтра
            if (empty($category->params['filter_fields'])) {
                // Если список пуст - пропускаем категорию
                continue;
            }

            // Создаем таблицу для фильтра
            $tableName = self::creatFilterTable($category);
            // Если не удалось пропускаем категорию
            if (!$tableName) {
                continue;
            }
            // Имя рабочей таблицы
            $realTableName = '#__ishop_filter_cat_' . $category->id;

            // Получаем данные характеристик,
            // указанных в параметрах категории,
            // ключ массива - id товара
            $products_fields = self::getCategoryFields($category);
            // Если не удалось пропускаем категорию,
            // но таблицу оставим для совместимости
            if (!$products_fields) {
                // Удаляем старую версию рабочей таблицы
                $db->dropTable($realTableName);
                // Меняем имя временной таблицы
                $db->renameTable($tableName, $realTableName);
                continue;
            }

            // Собираем запрос для заполнения таблицы фильтра
            $all_fields_id = $category->params['filter_fields'];
            $columns = ['product_id'];
            foreach ($all_fields_id as $id) {
                $columns[] = 'field_' . $id;
            }
            $query
                ->clear()
                ->insert($db->quoteName($tableName))
                ->columns($db->quoteName($columns));

            // Проходим по каждому товару и составляем запись
            foreach ($products_fields as $id => $row) {
                // массив значений для данного товара
                $values = [$id]; // всегда устанавливаем product_id
                foreach ($all_fields_id as $field_id) {
                    // если для данного поля нет данных,
                    // пишем NULL в качестве значения
                    if (!isset($row[$field_id])) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $row[$field_id];
                    }
                }

                $query->values(implode(',', $values));
            }

            $db->setQuery($query);

            // Если не удалось вставить значения
            // удаляем временную таблицу и выходим
            if (!$db->execute()) {
                $db->dropTable($tableName);
                return false;
            }

            // Удаляем старую версию рабочей таблицы
            $db->dropTable($realTableName);
            // Меняем имя временной таблицы
            $db->renameTable($tableName, $realTableName);
        }

        return true;
    }


    /**
     * Метод возвращает данные о характеристиках,
     * их типах и значения.
     *
     * @param object $category Параметры категории
     *
     * @return array Массив характеристик для фильтрации
     * @since 1.0.0
     */
    public static function getCategoryFields(object $category): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select([
                $db->quoteName('a.product_id'),
                $db->quoteName('a.field_id'),
                $db->quoteName('a.value'),
            ])
            ->from($db->quoteName('#__ishop_fields_map', 'a'))
            ->join('INNER',
                $db->quoteName('#__ishop_products', 'products'),
                $db->quoteName('products.id') . ' = ' . $db->quoteName('a.product_id'))
            ->where($db->quoteName('products.catid') . ' = ' . $category->id);
        $db->setQuery($query);
        $fields = $db->loadObjectlist();

        if (!$fields) {
            return [];
        }

        $result = [];
        foreach ($fields as $field) {
            $result[$field->product_id][$field->field_id] = $field->value;
        }

        return $result;
    }


    /**
     * Метод пересоздает таблицу
     * для фильтрации товаров в категории,
     *
     * @param   object  $category  Параметры категории
     *
     * @return string Имя таблицы для фильтрации
     * @since 1.0.0
     */
    public static function creatFilterTable(object $category)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $tableName = '#__ishop_filter_cat_' . $category->id . '_temp';
        $db->dropTable($tableName);
        $query = "CREATE TABLE " . $db->quoteName($tableName) .
            "(" .
            // PRIMARY KEY идентификатор товара
            $db->quoteName('product_id') . " int UNSIGNED NOT NULL, ";

        // Добавляем определения столбцов характеристик,
        // которые указаны в параметрах категории
        foreach ($category->params['filter_fields'] as $field) {
            $query .= $db->quoteName('field_' . $field) . ' float(10, 2), ';
        }

        // Указываем ключ, тип и кодировку
        $query .= "PRIMARY KEY (" . $db->quoteName('product_id') . ") " .
            ") " .
            "ENGINE = InnoDB " .
            "DEFAULT CHARSET = utf8mb4 " .
            "COLLATE = utf8mb4_unicode_ci;";
        $db->setQuery($query);
        if (!$db->execute()) {
            Log::add('Не удалось создать таблицу ' . $tableName, Log::ERROR, 'ishop');
            return false;
        }

        return $tableName;
    }
}
