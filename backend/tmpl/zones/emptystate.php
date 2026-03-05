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

/** @var Ilange\Component\Ishop\Administrator\View\Zones\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_ISHOP',
    'formURL'    => 'index.php?option=com_ishop&view=zones',
    'helpURL'    => '',
    'icon'       => 'icon-copy',
];

$user = $this->getCurrentUser();

if ($user->authorise('core.create', 'com_ishop') || count($user->getAuthorisedCategories('com_ishop', 'core.create')) > 0) {
    $displayData['createURL'] = 'index.php?option=com_ishop&task=zone.add';
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
