<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Контроллер списка SEO-страниц фильтра.
 *
 * Использует стандартные административные операции Joomla для списка записей:
 * публикацию, снятие с публикации, архивирование, удаление, check-in и batch.
 *
 * @since 1.0.0
 */
class FiltersController extends AdminController
{
    /**
     * Возвращает модель записи для операций списка.
     *
     * По умолчанию список работает с моделью `Filter`, чтобы batch и массовые
     * действия применялись к отдельным записям `#__ishop_filters`.
     *
     * @param   string  $name    Имя модели.
     * @param   string  $prefix  Префикс пространства имен модели.
     * @param   array   $config  Конфигурация создания модели.
     *
     * @return  object
     *
     * @since 1.0.0
     */
    public function getModel($name = 'Filter', $prefix = 'Administrator', $config = ['ignore_request' => true]): object
    {
        return parent::getModel($name, $prefix, $config);
    }
}
