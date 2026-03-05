<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Класс helper
 * @since 1.0.0
 */
class ProductHelper
{
    /**
     * @var        array    Массив уже загруженных данных
     * @since 1.0.0
     */
    protected static array $loaded = [];

    /**
     * Возвращает массив (select.option) товаров каталога
     * для элемента select
     *
     * @param   string|null  $language  Язык товара
     *
     * @return  array
     * @since 1.0.0
     */
    public static function productOptions(string $language = null)
    {
        if (!empty(static::$loaded[__METHOD__][$language])) {
            return static::$loaded[__METHOD__][$language];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a.id', 'value'),
                'CONCAT('
                . $db->quoteName('p.title') . ', ' . $db->q(' ') . ', '
                . $db->quoteName('m.title') . ', ' . $db->q(' ') . ', '
                . $db->quoteName('a.title') .') AS ' . $db->qn('text'),
                $db->quoteName('a.language'),
            ])
            ->from($db->quoteName('#__ishop_products', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_prefixes', 'p'),
                $db->quoteName('p.id') . ' = ' . $db->quoteName('a.prefix_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__ishop_manufacturers', 'm'),
                $db->quoteName('m.id') . ' = ' . $db->quoteName('a.manufacturer_id')
            )
            ->where($db->quoteName('a.state') . ' = 1')
            ->order($db->quoteName('a.id'));

        if ($language) {
            $query->where($db->quoteName('a.language') . ' = ' . $db->q($language));
        }

        //Log::add('Запрос на выборку товаров: ' . $query->__toString(), Log::ERROR, 'ishop');

        $db->setQuery($query);
        $products = $db->loadObjectList();

        // Если включена многоязычность, но язык не указан
        if (Associations::isEnabled() && $products && !$language) {
            foreach ($products as $product) {
                $product->text = $product->text . ' (' . $product->language . ')';
            }
        }

        static::$loaded[__METHOD__][$language] = $products;

        return $products;
    }

    /**
     * Возвращает массив (select.option) производителей
     * для элемента select
     *
     * @param   string|null  $language  Язык
     *
     * @return  array
     * @since 1.0.0
     */
    public static function manufacturerOptions(string $language = null)
    {
        if (!empty(static::$loaded[__METHOD__][$language])) {
            return static::$loaded[__METHOD__][$language];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id', 'value'),
                $db->quoteName('title', 'text'),
                $db->quoteName('language'),
            ])
            ->from($db->quoteName('#__ishop_manufacturers'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('title'));

        if ($language) {
            $query->where('language = ' . $db->q($language));
        }

        $db->setQuery($query);
        $manufacturers = $db->loadObjectList();

        // Если включена многоязычность, но язык не указан
        if (Associations::isEnabled() && $manufacturers && !$language) {
            foreach ($manufacturers as $manufacturer) {
                $manufacturer->text = $manufacturer->text . ' (' . $manufacturer->language . ')';
            }
        }

        static::$loaded[__METHOD__][$language] = $manufacturers;

        return $manufacturers;
    }

    /**
     * Возвращает массив (select.option) поставщиков
     * для элемента select
     *
     * @param   string|null  $language  Язык
     *
     * @return  array
     * @since 1.0.0
     */
    public static function supplierOptions(string $language = null)
    {
        if (!empty(static::$loaded[__METHOD__][$language])) {
            return static::$loaded[__METHOD__][$language];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id', 'value'),
                $db->quoteName('title', 'text'),
                $db->quoteName('language'),
            ])
            ->from($db->quoteName('#__ishop_suppliers'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('title'));

        if ($language) {
            $query->where('language = ' . $db->q($language));
        }

        $db->setQuery($query);
        $suppliers = $db->loadObjectList();

        // Если включена многоязычность, но язык не указан
        if (Associations::isEnabled() && $suppliers && !$language) {
            foreach ($suppliers as $supplier) {
                $supplier->text = $supplier->text . ' (' . $supplier->language . ')';
            }
        }

        static::$loaded[__METHOD__][$language] = $suppliers;

        return $suppliers;
    }

    /**
     * Возвращает массив (select.option) сервисных центров
     * для элемента select
     *
     * @param   string|null  $language  Язык
     *
     * @return  array
     * @since 1.0.0
     */
    public static function serviceOptions(string $language = null)
    {
        if (!empty(static::$loaded[__METHOD__][$language])) {
            return static::$loaded[__METHOD__][$language];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id', 'value'),
                $db->quoteName('title', 'text'),
                $db->quoteName('language'),
            ])
            ->from($db->quoteName('#__ishop_services'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('title'));

        if ($language) {
            $query->where('language = ' . $db->q($language));
        }

        $db->setQuery($query);
        $services = $db->loadObjectList();

        // Если включена многоязычность, но язык не указан
        if (Associations::isEnabled() && $services && !$language) {
            foreach ($services as $supplier) {
                $supplier->text = $supplier->text . ' (' . $supplier->language . ')';
            }
        }

        static::$loaded[__METHOD__][$language] = $services;

        return $services;
    }

    /**
     * Возвращает массив (select.option) префиксов
     * для элемента select
     *
     * @param   string|null  $language  Язык
     *
     * @return  array
     * @since 1.0.0
     */
    public static function prefixesOptions(string $language = null)
    {
        if (!empty(static::$loaded[__METHOD__][$language])) {
            return static::$loaded[__METHOD__][$language];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id', 'value'),
                $db->quoteName('title', 'text'),
                $db->quoteName('language'),
            ])
            ->from($db->quoteName('#__ishop_prefixes'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('title'));

        if ($language) {
            $query->where('language = ' . $db->q($language));
        }

        $db->setQuery($query);
        $prefixes = $db->loadObjectList();

        // Если включена многоязычность, но язык не указан
        if (Associations::isEnabled() && $prefixes && !$language) {
            foreach ($prefixes as $prefix) {
                $prefix->text = $prefix->text . ' (' . $prefix->language . ')';
            }
        }

        static::$loaded[__METHOD__][$language] = $prefixes;

        return $prefixes;
    }

    /**
     * Возвращает массив (select.option) складов и магазинов
     * для элемента select
     *
     * @param   string|null  $language  Язык
     *
     * @return  array
     * @since 1.0.0
     */
    public static function warehouseOptions(string $language = null)
    {
        if (!empty(static::$loaded[__METHOD__][$language])) {
            return static::$loaded[__METHOD__][$language];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id', 'value'),
                $db->quoteName('title', 'text'),
                $db->quoteName('language'),
            ])
            ->from($db->quoteName('#__ishop_warehouses'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('title'));

        if ($language) {
            $query->where('language = ' . $db->q($language));
        }

        $db->setQuery($query);
        $warehouses = $db->loadObjectList();

        // Если включена многоязычность, но язык не указан
        if (Associations::isEnabled() && $warehouses && !$language) {
            foreach ($warehouses as $warehouse) {
                $warehouse->text = $warehouse->text . ' (' . $warehouse->language . ')';
            }
        }

        static::$loaded[__METHOD__][$language] = $warehouses;

        return $warehouses;
    }

    /**
     * Привязывает фото товара к карточке,
     * если они найдены в папке с псевдонимом товара
     *
     * @param   int  $pk  Идентификатор товара
     *
     * @return  bool
     * @throws \Exception
     * @since 1.0.0
     */
    public static function setImages(int $pk)
    {
        if (!$pk) {
            return false;
        }

        // Получим путь к изображениям товаров
        // и расширение файлов изображений
        // из настроек компонента
        $params = ComponentHelper::getParams('com_ishop');
        $extension = image_type_to_extension($params->get('products_images_type', IMAGETYPE_WEBP));

        $image_path = $params->get('product_images_dir');
        if (empty($image_path)) {
            return false;
        }
        $image_path = trim($image_path, '/' );

        // Получи префиксы имен изображений
        $prefix_main    = $params->get('product_image_prefix_main', '');
        $prefix_small   = $params->get('product_image_prefix_small', '');
        $prefix_export  = $params->get('product_image_prefix_export', '');
        $prefix_nobg    = $params->get('product_image_prefix_nobg', '');
        $prefix_info    = $params->get('product_image_prefix_info', '');
        $prefix_more    = $params->get('product_image_prefix_more', '');

        // Используем таблицу товара
        $product = Factory::getApplication()
            ->bootComponent('com_ishop')
            ->getMVCFactory()
            ->createTable('Product', 'Administrator');

        // Загрузим данные товара
        if (!$product->load($pk)) {
            return false;
        }

        $site_path = $image_path . '/' . $product->alias . '/';
        $dir_path = JPATH_SITE . '/' . $image_path . '/' . $product->alias;

        // Если папка с изображениями не существует, выходим
        if (!is_dir($dir_path)) {
            return false;
        }

        $mainFileName = $product->alias . $extension;
        $pattern = $dir_path . '/' . '*' . $extension;
        $allFiles = glob($pattern);

        $images = (new Registry($product->images))->toArray();

        $index = 0;
        foreach ($allFiles as $file) {
            if (!is_file($file)) {
                continue;
            }

            // Получаем размеры изображения
            $name = basename($file);
            $image_dimensions = getimagesize($file);
            if (!$image_dimensions) {
                continue;
            }
            $width = $image_dimensions[0];
            $height = $image_dimensions[1];
            unset($image_dimensions);
            $src = $site_path . $name . '#joomlaImage://local-' . $site_path . $name . '?width=' . $width . '&height=' . $height;

            // Флаг устанавливаем в true, если это не дополнительное фото для галереи
            $noMore = false;

            // Ищем главное фото
            if ((!empty($prefix_main) && str_starts_with($name, $prefix_main)) || (empty($images['image_main']) && $name === $mainFileName)) {
                $images['image_main'] = $src;
                $images['image_main_alt'] = $product->title;
                $noMore = true;
            }

            // Ищем фото предварительного просмотра
            if ((!empty($prefix_small) && str_starts_with($name, $prefix_small)) || (empty($images['image_small']) && $name === $mainFileName)) {
                $images['image_small'] = $src;
                $images['image_small_alt'] = $product->title;
                $noMore = true;
            }

            // Ищем фото для экспорта
            if ((!empty($prefix_export) && str_starts_with($name, $prefix_export)) || (empty($images['image_export']) && $name === $mainFileName)) {
                $images['image_export'] = $src;
                $images['image_export_alt'] = $product->title;
                $noMore = true;
            }

            // Ищем фото без фона
            if ((!empty($prefix_nobg) && str_starts_with($name, $prefix_nobg))) {
                $images['image_nobg'] = $src;
                $images['image_nobg_alt'] = $product->title;
                $noMore = true;
            }

            // Ищем фото с инфографикой
            if ((!empty($prefix_info) && str_starts_with($name, $prefix_info))) {
                $images['image_info'] = $src;
                $images['image_info_alt'] = $product->title;
                $noMore = true;
            }

            // Ищем дополнительное фото
            if ((!$noMore && str_starts_with($name, $prefix_more)) || (!$noMore && $name !== $mainFileName)) {
                if (!isset($images['image_more'])) {
                    $images['image_more'] = [];
                }

                $images['image_more']['image_more' . $index] = [
                    'image_item' => $src,
                    'image_item_alt' => $product->title,
                ];

                $index++;
            }
        }

        // Сохраняем изменения в базе данных
        $product->images = (string) new Registry($images);

        return $product->store();
    }
}
