<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Ilange\Component\Ishop\Site\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$tpl = $app->getTemplate(true);

/** @var Ilange\Component\Ishop\Site\View\Product\HtmlView $this */
// Сделаем ссылку на товар
$product = $this->item;
$product_price = ($product->sale_price > 0) ? $product->sale_price : $product->price;
?>
<div class="card_price<?php echo ($product->discount_size > 0) ? ' sale' : ''; ?>">
    <?php if ($product->discount_size > 0) : ?>
        <svg class="svg"><use href="/icons_v3.svg#sales"/></svg>
    <?php endif; ?>
    <?php echo round($product_price, $this->params->get('roundPrice', 0)); ?><span class="currency"><?php echo $this->params->get('defaultCurrency', 'BYN'); ?></span>
</div>
<?php if ($product->old_price > 0) : ?>
    <div class="card_old_price">
        <del><?php echo round($product->old_price, $this->params->get('roundPrice', 0)); ?><span class="currency position-out"><?php echo $this->params->get('defaultCurrency', 'BYN'); ?></span></del>
    </div>
<?php endif; ?>

<?php if ($product->available) : ?>
    <?php echo ProductHelper::renderCartButton($product, $this->params); ?>
    <form action="<?php echo Route::_(RouteHelper::getCheckoutRoute()); ?>"
          method="post"
          name="product-buy-now">
        <input type="hidden" name="products[]" value="<?php echo $product->id; ?>">
        <button class="btn btn-border w-100"
                title="<?php echo Text::_('COM_ISHOP_PRODUCT_BUY_NOW'); ?>"
                type="submit"><?php echo Text::_('COM_ISHOP_PRODUCT_BUY_NOW'); ?></button>
    </form>
<?php else : ?>
    <div class="card_not_available"><?php echo Text::_('COM_ISHOP_PRODUCT_NOT_AVAILABLE'); ?></div>
<?php endif; ?>

<?php if (!empty($product->active_zone)) : ?>
<div class="card_delivery_zone"
     data-offcanvas="mainmenu"
     data-offcanvas-panel="mainmenu-location">
    <?php echo Text::_('COM_ISHOP_PRODUCT_DELIVERY_ZONE'); ?>
    <span class="active"><?php echo $product->active_zone->title; ?></span>
</div>
<?php endif; ?>

<?php if ($tpl->params->get('siteContacts', 1)) : ?>
    <?php if ($tpl->params->get('sitePhone')) : ?>
        <div class="card_call_us"><?php echo Text::_('COM_ISHOP_MSG_CALL_US'); ?>:</div>
        <a class="btn btn-border w-100"
           title="<?php echo Text::_('COM_ISHOP_MSG_CALL_TO'), ' ', $tpl->params->get('sitePhone'); ?>"
           href="tel:+<?php echo str_replace(['+',' ','-', '(',')'], '', $tpl->params->get('sitePhone')); ?>"
           target="_blank"
           rel="noopener noreferrer">
            <svg class="svg">
                <use href="/icons_v3.svg#phone"/>
            </svg><span><?php echo $tpl->params->get('sitePhone'); ?></span>
        </a>
    <?php endif; ?>
<?php endif; ?>

