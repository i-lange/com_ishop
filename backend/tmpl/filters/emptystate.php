<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$displayData = [
    'textPrefix' => 'COM_ISHOP',
    'formURL'    => 'index.php?option=com_ishop&view=filters',
    'helpURL'    => '',
    'icon'       => 'icon-filter',
    'createURL'  => Route::_('index.php?option=com_ishop&task=filter.add'),
];

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
