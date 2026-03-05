<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\ImageHelper;
use Ilange\Component\Ishop\Site\Helper\PriceHelper;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="module-category container pb-5">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <h1><?php echo $this->params->get('page_heading'); ?></h1>
    <?php endif; ?>
    <?php if (!empty($this->stock)) : ?>
        <?php foreach ($this->stock as $category) : ?>
            <a class="btn btn-border mb-1" href="#category-link-<?php echo $category->alias; ?>"><?php echo $category->title; ?></a>
        <?php endforeach; ?>
        <?php foreach ($this->stock as $category) : ?>
            <?php if (!$category->count) continue; ?>
            <?php $this->items = $category->products; ?>
            <h2 id="category-link-<?php echo $category->alias; ?>"><?php echo $category->title; ?></h2>
            <div class="category-products mb-5">
                <?php echo $this->loadTemplate('items'); ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert"><?php echo Text::_('COM_ISHOP_MSG_WAREHOUSE_EMPTY'); ?></div>
    <?php endif; ?>
</div>