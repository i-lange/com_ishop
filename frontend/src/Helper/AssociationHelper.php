<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Associations;
use Joomla\CMS\Factory;
use Joomla\Component\Categories\Administrator\Helper\CategoryAssociationHelper;

/**
 * Класс helper компонента
 * методы для работы с языковыми связями
 * @since 1.0.0
 */
abstract class AssociationHelper extends CategoryAssociationHelper
{
    /**
     * Метод получения ассоциаций для данного элемента
     *
     * @param   int          $id    Идентификатор элемента
     * @param   string|null  $view  Имя представления
     *
     * @return  array   Array of associations for the item.
     * @throws \Exception
     * @since 1.0.0
     */
    public static function getAssociations(int $id = 0, string $view = null)
    {
        $jinput = Factory::getApplication()->input;
        $view   = $view === null ? $jinput->get('view') : $view;
        $id     = empty($id) ? $jinput->getInt('id') : $id;

        // Категория или список категорий
        if ($view === 'category' or $view === 'categories') {
            return self::getCategoryAssociations($id, 'com_ishop');
        }

        // Товар или избранные товары
        if ($view === 'product' or $view === 'featured') {
            if ($id) {
                $associations = Associations::getAssociations(
                    'com_ishop',
                    '#__ishop_products',
                    'com_ishop.product',
                    $id
                );

                $return = [];

                foreach ($associations as $tag => $item) {
                    $return[$tag] = RouteHelper::getProductRoute($item->id, (int)$item->catid, $item->language);
                }

                return $return;
            }
        }

        // Характеристика
        if ($view === 'field') {
            if ($id) {
                $associations = Associations::getAssociations(
                    'com_ishop',
                    '#__ishop_fields',
                    'com_ishop.field',
                    $id,
                    'id',
                    'alias',
                    ''
                );

                $return = [];

                foreach ($associations as $tag => $item) {
                    $return[$tag] = RouteHelper::getFieldRoute($item->id, $item->language);
                }

                return $return;
            }
        }

        // Производитель
        if ($view === 'manufacturer') {
            if ($id) {
                $associations = Associations::getAssociations(
                    'com_ishop',
                    '#__ishop_manufacturers',
                    'com_ishop.manufacturer',
                    $id,
                    'id',
                    'alias',
                    null
                );

                $return = [];

                foreach ($associations as $tag => $item) {
                    $return[$tag] = RouteHelper::getManufacturerRoute($item->id, $item->language);
                }

                return $return;
            }
        }

        // Поставщик
        if ($view === 'supplier') {
            if ($id) {
                $associations = Associations::getAssociations(
                    'com_ishop',
                    '#__ishop_suppliers',
                    'com_ishop.supplier',
                    $id,
                    'id',
                    'alias',
                    null
                );

                $return = [];

                foreach ($associations as $tag => $item) {
                    $return[$tag] = RouteHelper::getSupplierRoute($item->id, $item->language);
                }

                return $return;
            }
        }

        return [];
    }
}
