<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\PriceHelper;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$currency = strtoupper($this->params->get('defaultCurrency', 'BYN'));

if (!empty($this->checkout->products)) {
    $forMail = [];
    $dataLayerItems = [];
    foreach ($this->checkout->products as $i => $product) {
        $forMail[] = [
            'product_id' => $product->id,
            'product_name' => $product->fullname,
            'real_ean' => $product->gtin,
            'quantity' => $product->count . ' шт.',
            'price' => ($product->sale_price > 0) ? $product->sale_price : $product->price . ' руб.'
        ];

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
    $dataLayer = 'gtag("event","begin_checkout",{currency:"' . $currency . '",value:"' . $this->checkout->summary . '",items:dataLayerItems});';
    $wa->addInlineScript($dataLayer);
}
?>
<div class="container pb-5">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <h1 class="mb-1"><?php echo $this->params->get('page_heading'); ?></h1>
    <?php endif; ?>
    <?php if (!empty($this->active_zone)) : ?>
        <div class="card_delivery_zone"
             data-offcanvas="mainmenu"
             data-offcanvas-panel="mainmenu-location">
            <?php echo Text::_('COM_ISHOP_PRODUCT_DELIVERY_ZONE'); ?>
            <span class="active"><?php echo $this->active_zone->title; ?></span>
        </div>
    <?php endif; ?>
    <?php if (empty($this->checkout->products)) : ?>
        <div class="module-cart-empty">
            <svg class="svg mega blue-2"><use href="/icons_v3.svg#cart"/></svg>
            <p><?php echo Text::_('COM_ISHOP_CART_NULL'); ?></p>
        </div>
    <?php else: ?>
    <form id="checkout-submit"
          action="<?php echo Route::_(RouteHelper::getCheckoutRoute()); ?>"
          method="post"
          name="checkout-submit">
        <div class="checkout-grid">
            <div>
                <h3><?php echo Text::_('COM_ISHOP_CHECKOUT_CLIENT'); ?></h3>
                <div class="mb-2">
                    <label class="form-label" for="name-checkout"><?php echo Text::_('COM_ISHOP_CHECKOUT_NAME_LABEL'); ?></label>
                    <input class="form-control"
                           id="name-checkout"
                           type="text"
                           name="name"
                           placeholder="<?php echo Text::_('COM_ISHOP_CHECKOUT_NAME_HINT'); ?>">
                </div>
                <div>
                    <label class="form-label" for="phone-checkout"><?php echo Text::_('COM_ISHOP_CHECKOUT_PHONE_LABEL'); ?><span class="fw-bold">*</span></label>
                    <input class="form-control required-phone"
                           id="phone-checkout"
                           type="text"
                           name="phone"
                           placeholder="<?php echo Text::_('COM_ISHOP_CHECKOUT_PHONE_HINT'); ?>"
                           required>
                </div>

                <h3 class="mt-3"><?php echo Text::_('COM_ISHOP_CHECKOUT_PAYMENT'); ?></h3>
                <?php $i= 0; ?>
                <?php foreach ($this->payments as $payment) : ?>
                    <div class="form-check">
                        <input class="form-check-input" required <?php if (!$i) echo 'checked="checked"'; ?> id="payment-<?php echo $payment->alias; ?>" type="radio" name="payment" value="<?php echo $payment->title; ?>">
                        <label class="form-check-label" for="payment-<?php echo $payment->alias; ?>"><svg class="svg mr-1"><use href="/icons_v3.svg#<?php echo $payment->icon; ?>"/></svg><?php echo $payment->title; ?></label>
                    </div>
                    <?php $i++; ?>
                <?php endforeach; ?>
            </div>
            <div>
                <h3><?php echo Text::_('COM_ISHOP_CHECKOUT_DELIVERY'); ?></h3>
                <?php $i= 0; ?>
                <?php foreach ($this->deliveries as $delivery) : ?>
                    <div class="form-check">
                        <input class="form-check-input" required <?php if (!$i) echo 'checked="checked"'; ?>
                               id="shipping-<?php echo $delivery->alias; ?>" type="radio" name="shipping" value="<?php echo $delivery->title; ?>">
                        <label class="form-check-label" for="shipping-<?php echo $delivery->alias; ?>"><?php echo $delivery->title; ?></label>
                    </div>
                    <?php $i++; ?>
                    <?php if ($delivery->point === 1) : ?>
                    <div class="checkout-shipping-params my-2 pl-3" id="toogle-shipping-<?php echo $delivery->alias; ?>">
                        <?php foreach ($this->warehouses as $pvz) : ?>
                            <div class="form-check">
                                <input class="form-check-input" id="point-<?php echo $pvz->alias; ?>" type="radio" name="point" value="<?php echo $pvz->address; ?>">
                                <label class="form-check-label" for="point-<?php echo $pvz->alias; ?>"><?php echo $pvz->title; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                        <div class="checkout-shipping-params my-2 pl-3 active" id="toogle-shipping-<?php echo $delivery->alias; ?>">
                            <label class="form-label" for="address-checkout"><?php echo Text::_('COM_ISHOP_CHECKOUT_ADDRESS_LABEL'); ?><span class="fw-bold">*</span></label>
                            <textarea class="form-control" id="address-checkout" name="address"
                                      placeholder="<?php echo Text::_('COM_ISHOP_CHECKOUT_ADDRESS_HINT'); ?>"></textarea>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="checkout-summary mt-3">
            <h3><?php echo Text::_('COM_ISHOP_CHECKOUT_ORDER'); ?></h3>
            <ol>
            <?php foreach ($this->checkout->products as $product) : ?>
                <li><?php echo $product->fullname; ?> <span class="fw-bold">x<?php echo $product->count, ' ', Text::_('COM_ISHOP_CHECKOUT_UNIT'); ?></span></li>
            <?php endforeach; ?>
            </ol>
            <p class="fw-bold text-uppercase"><?php echo Text::_('COM_ISHOP_CART_SUM'); ?>:
            <?php echo PriceHelper::renderPrice($this->checkout->summary, false); ?> <?php echo $currency; ?></p>
        </div>

        <p><?php echo Text::sprintf(
                'COM_ISHOP_CHECKOUT_MESSAGE',
                HTMLHelper::_('date', $this->checkout->delivery_date->format('Y-m-d'), Text::_('DATE_FORMAT_FUTURE'))); ?></p>
        <p><?php echo Text::_('COM_ISHOP_CHECKOUT_INFO'); ?></p>
        <div class="form-check">
            <input class="form-check-input"
                   id="checkout-confirm"
                   type="checkbox"
                   name="confirm"
                   checked="checked"
                   required>
            <label class="form-check-label" for="checkout-confirm"><?php echo Text::_('TPL_FEEDBACK_I_CONFIRM_PRE'); ?> <a class="blue tt-u td-u" href="/politica"><?php echo Text::_('TPL_FEEDBACK_I_CONFIRM_RULES'); ?></a> <?php echo Text::_('TPL_FEEDBACK_I_CONFIRM_POST'); ?></label>
        </div>
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="products" value='<?php echo json_encode($forMail); ?>'>
        <input type="hidden" name="total" value="<?php echo $this->checkout->summary; ?>&nbsp;руб.">
        <input type="hidden" name="title" value="DOMIS.BY - Заказ из корзины">
        <input type="hidden" name="module_id" value="119">
        <input type="hidden" name="metrika_client_id" value="">
        <input type="hidden" name="google_client_id" value="">
        <div class="text-center mt-5"><button class="btn btn-large"
                                              title="<?php echo Text::_('COM_ISHOP_CHECKOUT_SUBMIT'); ?>"
                                              type="submit"><?php echo Text::_('COM_ISHOP_CHECKOUT_SUBMIT'); ?></button></div>
    </form>
    <aside class="checkout_thank_you text-center">
        <svg class="svg blue-2 mega"><use href="/icons_v3.svg#smile-5"/></svg>
        <h2 class="">Спасибо за покупку!</h2>
        <p class="">Ваш заказ на сумму <?php echo PriceHelper::renderPrice($this->checkout->summary, false); ?> руб. обрабатывается. <br>Наш менеджер свяжется с вами для подтверждения и консультации в самое короткое время</p>
    </aside>
    <?php endif; ?>
</div>