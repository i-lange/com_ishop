<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Service;

use Joomla\CMS\Categories\Categories;

defined('_JEXEC') or die;

/**
 * Дерево категорий товаров
 * @since 1.0.0
 */
class Category extends Categories
{
    /**
     * Конструктор
     * @param array $options Массив параметров
     * @since 1.0.0
     */
    public function __construct($options = [])
    {
        $options['table'] = '#__ishop_products';
        $options['extension'] = 'com_ishop';

        parent::__construct($options);
    }
}
