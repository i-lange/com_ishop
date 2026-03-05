<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\View\Categories;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\CategoriesView;

/**
 * Представление категорий
 * @since 1.0.0
 */
class HtmlView extends CategoriesView
{
    /**
     * Языковая константа для заголовка страницы по умолчанию
     * @var string
     * @since 1.0.0
     */
    protected $pageHeading = 'COM_ISHOP_PRODUCTS';

    /**
     * @var string Имя расширения для категории
     * @since 1.0.0
     */
    protected $extension = 'com_ishop';
}