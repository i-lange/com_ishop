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
    <?php if ($product->incart) : ?>
    <button class="btn w-100 btn-control"
            title="<?php echo Text::_('COM_ISHOP_ADD_TO_CART'); ?>"
            data-tocart="<?php echo $product->id; ?>"
            data-original-html="<svg class=&quot;svg&quot;><use href=&quot;/icons_v3.svg#cart&quot;/></svg><span><?php echo $product->delivery; ?></span>">
        <span class="btn_decrease">-</span>
        <span class="btn_quantity"><?php echo $product->incart_count; ?></span>
        <span class="btn_increase">+</span>
    </button>
    <?php else : ?>
    <button class="btn w-100"
            title="<?php echo Text::_('COM_ISHOP_ADD_TO_CART'); ?>"
            data-tocart="<?php echo $product->id; ?>"
            data-tocart-text="<?php echo $product->delivery; ?>"><svg class="svg"><use href="/icons_v3.svg#cart"/></svg><span><?php echo $product->delivery; ?></span></button>
    <?php endif; ?>
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


