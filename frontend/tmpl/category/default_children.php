<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Ilange\Component\Ishop\Site\Helper\ImageHelper;

$user = Factory::getApplication()->getIdentity();
$groups = $user->getAuthorisedViewLevels();
?>
<?php if ($this->children[$this->category->id] > 0) : ?>
<div class="module-categories">
<?php foreach ($this->children[$this->category->id] as $id => $child) : ?>
    <?php if (in_array($child->access, $groups)) : ?>
        <div class="position-relative">
            <?php if ($this->params->get('show_description_image') && $child->getParams()->get('image')) : ?>
                <?php echo ImageHelper::renderImage(
                    $child->getParams()->get('image'),
                    $child->getParams()->get('image_alt'),
                    '(max-width: 439px) calc(100vw - 2rem), 50vw',
                    'image'
                ); ?>
            <?php endif; ?>
            <div class="category-title"><?php echo $this->escape($child->title); ?></div>
            <?php if ($this->params->get('show_subcat_desc_cat') == 1) : ?>
                <?php if ($child->description) : ?>
                    <div class="category-desc">
                        <?php echo HTMLHelper::_('content.prepare', $child->description, '', 'com_ishop.categories'); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <a class="cover" href="<?php echo Route::_(RouteHelper::getCategoryRoute($child->id, $child->language)); ?>"></a>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
