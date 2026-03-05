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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$currency = strtoupper($this->params->get('defaultCurrency', 'BYN'));

if ($this->cart->total > 0) {
    $dataLayerItems = [];
    foreach ($this->cart->products as $i => $product) {
        if (empty($product->count)) {
            continue;
        }

        $dataLayerItems[] = [
            'item_id'       => $product->id,
            'item_name'     => $this->escape($product->fullname),
            'discount'      => $product->discount_size,
            'index'         => $i,
            'item_brand'    => $product->manufacturer_title,
            'item_category' => $product->category_title,
            'price'         => ($product->discount_size > 0) ? $product->sale_price : $product->price,
            'quantity'      => $product->count,
        ];
    }
    $jsonLayerItems = json_encode($dataLayerItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $dataLayer = 'const dataLayerItems = ' . $jsonLayerItems . ';';
    $wa->addInlineScript($dataLayer);
    $dataLayer = 'gtag("event","view_cart",{currency:"' . $currency . '",value:"' . $this->cart->summary . '",items:dataLayerItems});';
    $wa->addInlineScript($dataLayer);
}
?>
<div class="container pb-5">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <h1 class="mb-1"><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>
    <?php if (!empty($this->active_zone)) : ?>
        <div class="card_delivery_zone"
             data-offcanvas="mainmenu"
             data-offcanvas-panel="mainmenu-location">
            <?php echo Text::_('COM_ISHOP_PRODUCT_DELIVERY_ZONE'); ?>
            <span class="active"><?php echo $this->active_zone->title; ?></span>
        </div>
    <?php endif; ?>
    <?php if (count($this->cart->products) > 0) : ?>
    <form class="module-cart-grid"
          id="cart-submit"
          action="<?php echo Route::_(RouteHelper::getCheckoutRoute()); ?>"
          method="post"
          name="cart-submit">
        <div>
            <?php foreach ($this->cart->products as $key_id => $product) : ?>
                <div class="module-cart-item<?php echo ($product->available) ? '' : ' not_available'; ?>" data-product-incart-id="<?php echo $product->id; ?>">
                    <div class="cart-item_top">
                        <div class="cart-item_image">
                            <?php if (!empty($product->images->image_small)) : ?>
                                <?php $alt = $product->images->image_small_alt ?: $product->fullname; ?>
                                <?php echo ImageHelper::renderImage(
                                    $product->images->image_small, $alt,
                                    '(max-width: 299px) 5rem, (max-width: 1199px) 8rem, 10rem',
                                    'image'
                                ); ?>
                            <?php else : ?>
                                <div class="x1x1 w-100">
                                    <svg class="svg large"><use href="/icons_v3.svg#image"/></svg>
                                </div>
                            <?php endif; ?>
                            <?php if ($product->available) : ?>
                            <label class="position-out" for="check_input_<?php echo $key_id; ?>"></label>
                            <input class="form-check-input"
                                   id="check_input_<?php echo $key_id; ?>"
                                   type="checkbox"
                                   name="products[]"
                                   checked="checked"
                                   value="<?php echo $product->id; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="cart-item_title">
                            <div class="title-container">
                                <h3><?php echo htmlspecialchars($product->fullname); ?></h3>
                                <span>
                                <?php if ($product->gtin) : ?>
                                   <?php echo Text::_('COM_ISHOP_PRODUCT_GTIN'); ?>:<?php echo $product->gtin; ?>,
                                <?php endif; ?>
                                <?php if ($product->available) : ?>
                                    <?php echo Text::_('COM_ISHOP_TAB_DELIVERIES'), ' ', $product->delivery; ?>
                                <?php else: ?>
                                    <?php echo Text::_('COM_ISHOP_PRODUCT_NOT_AVAILABLE'); ?>
                                <?php endif; ?>
                                </span>
                                <a class="cover" href="<?php echo Route::_(RouteHelper::getProductRoute((int)$product->id, (int)$product->catid)); ?>"></a>
                            </div>
                            <div class="prices-container">
                                <?php if ($product->available) : ?>
                                    <span class="cart-item_price<?php echo ($product->discount_size > 0) ? ' sale' : '';?>">
                                    <?php if ($product->discount_size > 0) : ?>
                                        <svg class="svg"><use href="/icons_v3.svg#sales"/></svg>
                                    <?php endif; ?>
                                    <span data-price><?php echo PriceHelper::renderPrice($product->incart_total, false); ?></span><span class="currency"><?php echo $currency; ?></span>
                                </span>
                                    <?php if ($product->incart_old_total > 0) : ?>
                                        <span class="cart-item_price_old_price">
                                        <del data-old-price><?php echo PriceHelper::renderPrice($product->incart_old_total, false); ?><span class="currency position-out"><?php echo $currency; ?></span></del>
                                    </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cart-item_controls">
                        <div class="cart-item_quantity">
                            <?php if ($product->available) : ?>
                            <label class="position-out" for="quantity_input_<?php echo $key_id; ?>"></label>
                            <button class="btn quantity_minus"
                                    title="<?php echo Text::_('COM_ISHOP_CART_MINUS'); ?>"
                                    type="button"
                                    data-cart-button="minus"><span class="position-out"><?php echo Text::_('COM_ISHOP_CART_MINUS'); ?></span><svg class="svg"><use href="/icons_v3.svg#minus"/></svg></button>
                            <input class="quantity_input"
                                   id="quantity_input_<?php echo $key_id; ?>"
                                   type="number"
                                   name="quantity[<?php echo $product->id; ?>]"
                                   value="<?php echo $product->incart_count; ?>"
                                   min="0"
                                   readonly
                                   data-quantity>
                            <button class="btn quantity_plus"
                                    title="<?php echo Text::_('COM_ISHOP_CART_PLUS'); ?>"
                                    type="button"
                                    data-cart-button="plus"><span class="position-out"><?php echo Text::_('COM_ISHOP_CART_PLUS'); ?></span><svg class="svg"><use href="/icons_v3.svg#plus"/></svg></button>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item_remove">
                            <button class="btn btn-delete"
                                    title="<?php echo Text::_('COM_ISHOP_CART_REMOVE'); ?>"
                                    type="button"
                                    data-cart-button="delete">
                                <span class="position-out"><?php echo Text::_('COM_ISHOP_CART_REMOVE'); ?></span>
                                <svg class="svg"><use href="/icons_v3.svg#trash"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div>
            <div class="module-cart-checkout">
                <div class="module-cart-info">
                    <h3><?php echo Text::_('COM_ISHOP_CART_YOUR_ORDER'); ?></h3>
                    <div class="info-line">
                        <span><?php echo Text::_('COM_ISHOP_CART_SELECTED_TOTAL'); ?>:</span>
                        <span class="cart-price"><span data-cart-total><?php echo PriceHelper::renderPrice($this->cart->total, false); ?></span><span class="currency"><?php echo $currency; ?></span></span>
                    </div>
                    <div class="info-line">
                        <span><?php echo Text::_('COM_ISHOP_CART_SELECTED_SALE'); ?>:</span>
                        <span class="cart-price">-<span data-cart-total-discount><?php echo PriceHelper::renderPrice($this->cart->total_discount, false); ?></span><span class="currency"><?php echo $currency; ?></span></span>
                    </div>
                    <hr>
                    <div class="info-line h3">
                        <span><?php echo Text::_('COM_ISHOP_CART_SUM'); ?>:</span>
                        <span class="cart-price"><span data-cart-summary><?php echo PriceHelper::renderPrice($this->cart->summary, false); ?></span><span class="currency"><?php echo $currency; ?></span></span>
                    </div>
                </div>
                <?php if ($this->cart->total > 0) : ?>
                <div><button class="btn btn-large"
                             title="<?php echo Text::_('COM_ISHOP_CART_CHECKOUT'); ?>"
                             type="submit"><?php echo Text::_('COM_ISHOP_CART_CHECKOUT'); ?></button></div>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <?php else : ?>
        <div class="module-cart-empty">
            <svg class="svg mega blue-2"><use href="/icons_v3.svg#cart"/></svg>
            <p><?php echo Text::_('COM_ISHOP_CART_NULL'); ?></p>
        </div>
    <?php endif; ?>
</div>