<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Ilange\Component\Ishop\Site\Helper\ImageHelper;

/** @var Ilange\Component\Ishop\Site\View\Categories\HtmlView $this */

if ($this->maxLevelcat != 0 && count($this->items[$this->parent->id]) > 0) : ?>
<?php foreach ($this->items[$this->parent->id] as $id => $item) : ?>
    <?php if (count($item->getChildren()) > 0 && $this->maxLevelcat > 1) : ?>
        <?php
            $this->items[$item->id] = $item->getChildren();
            $this->parent = $item;
            $this->maxLevelcat--;
            echo $this->loadTemplate('items');
            $this->parent = $item->getParent();
            $this->maxLevelcat++;
        ?>
    <?php else: ?>
    <div class="position-relative">
        <?php if ($this->params->get('show_description_image') && $item->getParams()->get('image')) : ?>
            <?php echo ImageHelper::renderImage(
                $item->getParams()->get('image'),
                $item->getParams()->get('image_alt'),
                '(max-width: 439px) calc(100vw - 2rem), 50vw',
                'image'
            ); ?>
        <?php endif; ?>
        <div class="category-title"><?php echo $this->escape($item->title); ?></div>
        <?php if ($this->params->get('show_subcat_desc_cat') == 1) : ?>
            <?php if ($item->description) : ?>
                <div class="category-desc">
                    <?php echo HTMLHelper::_('content.prepare', $item->description, '', 'com_ishop.categories'); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <a class="cover" href="<?php echo Route::_(RouteHelper::getCategoryRoute($item->id, $item->language)); ?>"></a>
    </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>