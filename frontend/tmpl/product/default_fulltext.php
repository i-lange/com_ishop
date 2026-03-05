<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var Ilange\Component\Ishop\Site\View\Product\HtmlView $this */
?>
<div class="bg-grey py-5">
    <div class="container">
        <h2><?php echo Text::_('COM_ISHOP_PRODUCT_DESCRIPTIONS'); ?></h2>
        <?php echo $this->item->fulltext; ?>
    </div>
</div>
