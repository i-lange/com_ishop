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
use Ilange\Component\Ishop\Site\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;

$app = Factory::getApplication();
$tpl = $app->getTemplate(true);
$doc = $app->getDocument();
$wa = $doc->getWebAssetManager();
$rend = $doc->loadRenderer('module');
$banner = (isset(ModuleHelper::getModules('banner')[0])) ? ModuleHelper::getModules('banner')[0] : 0;
$product_index = 0;

$currency = strtoupper($this->params->get('defaultCurrency', 'BYN'));
$round = (int) $this->params->get('defaultCurrency', 0);

$dataLayerItems = [];
foreach ($this->items as $i => $product) {
    $dataLayerItems[] = [
        'item_id'       => $product->id,
        'item_name'     => $this->escape($product->fullname),
        'discount'      => $product->discount_size,
        'index'         => $i,
        'item_brand'    => $product->manufacturer_title,
        'item_category' => $this->category->title,
        'price'         => ($product->discount_size > 0) ? $product->sale_price : $product->price,
        'quantity'      => 1,
    ];
}
$jsonLayerItems = json_encode($dataLayerItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$dataLayer = 'const dataLayerItems = ' . $jsonLayerItems . ';';
$wa->addInlineScript($dataLayer);
$dataLayer = 'gtag("event","view_item_list",{currency:"' . $currency . '",item_list_id:"' . $this->category->id . '",item_list_name:"' . $this->category->title . '",items:dataLayerItems});';
$wa->addInlineScript($dataLayer);
?>
<?php foreach ($this->items as $product) : ?>
    <?php $product_index++; ?>
    <?php $product_price = ($product->sale_price > 0) ? $product->sale_price : $product->price; ?>
    <article class="module-product" data-product-id="<?php echo $product->id; ?>">
        <div class="position-relative">
            <div class="product_image <?php echo ($product->discount_size > 0) ? 'product_image_sale' : '' ;?>">
                <?php if (!empty($product->images->image_small)) : ?>
                    <?php $alt = $product->images->image_small_alt ?: $product->fullname; ?>
                    <?php echo ImageHelper::renderImage(
                        $product->images->image_small, $alt,
                        '(max-width: 439px) calc(100vw - 2rem), (max-width: 759px) calc(50vw - 4rem), (max-width: 959px) calc(33vw - 5rem), (max-width: 1199px) calc(25vw - 7rem), 20vw',
                        'image'
                    ); ?>
                <?php else : ?>
                    <svg class="svg blue-2 mega"><use href="/icons_v3.svg#image"/></svg>
                <?php endif; ?>
                <?php if (!empty($product->attribs)) : ?>
                <div class="product_badges">
                    <?php foreach ($product->attribs as $text => $value) : ?>
                        <?php if ($value) : ?>
                            <div class="product_badge badge_<?php echo $text; ?>">
                                <?php echo Text::_('COM_ISHOP_' . $text); ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="product_labels">
                    <?php if ($product->discount_size > 0) : ?>
                        <span class="product_label product_discount_size">-<?php echo $product->discount_size; ?>%</span>
                        <span class="product_label product_discount_price"><?php echo Text::_('COM_ISHOP_PRODUCT_GOOD_PRICE'); ?></span>
                    <?php endif; ?>
                    <?php foreach ($product->parts as $part) : ?>
                        <span class="product_label product_part_type_<?php echo $part->cats_label; ?>">
                        <?php if (!empty($part->icon)) : ?>
                            <svg class="svg"><use href="/icons_v3.svg#<?php echo $part->icon; ?>"/></svg>
                        <?php endif; ?>
                            <?php if ($part->cats_label_param > 0) : ?>
                                <?php switch ($part->cats_label_param) {
                                    case 1:
                                        echo Text::sprintf(
                                            'COM_ISHOP_FIELD_CATS_LABEL_' . $part->cats_label,
                                            $part->min_payment);
                                        break;
                                    case 2:
                                        echo Text::sprintf(
                                            'COM_ISHOP_FIELD_CATS_LABEL_' . $part->cats_label,
                                            $part->min_rate . '%');
                                        break;
                                    case 3:
                                        echo Text::sprintf(
                                            'COM_ISHOP_FIELD_CATS_LABEL_' . $part->cats_label,
                                            $part->max_period);
                                        break;
                                }
                                ?>
                            <?php else: ?>
                                <?php echo Text::_('COM_ISHOP_FIELD_CATS_LABEL_0'); ?>
                            <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($product->price) : ?>
            <div class="product_all_prices">
                    <div class="product_price <?php echo ($product->discount_size > 0) ? 'sale' : '';?>">
                        <?php if ($product->discount_size > 0) : ?>
                            <svg class="svg"><use href="/icons_v3.svg#sales"/></svg>
                        <?php endif; ?>
                        <?php echo round($product_price, $round); ?><span class="currency"><?php echo $currency; ?></span>
                    </div>
                    <?php if ($product->old_price > 0) : ?>
                        <div class="product_old_price">
                            <del><?php echo round($product->old_price, $round); ?><span class="currency position-out"><?php echo $currency; ?></span></del>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <h3 class="product_title">
                <span class="brand"><?php echo $this->escape($product->manufacturer_title); ?></span><span class="model"> / <?php echo $this->escape($product->title); ?></span>
            </h3>
            <div class="product_prefix"><?php echo $this->escape($product->prefix); ?></div>
            <?php if (!$product->available) : ?>
            <div class="product_not_available"><?php echo Text::_('COM_ISHOP_PRODUCT_NOT_AVAILABLE'); ?></div>
            <?php endif; ?>
            <a class="cover" href="<?php echo Route::_(RouteHelper::getProductRoute((int)$product->id, (int)$product->catid)); ?>">
                <span class="position-out"><?php echo $this->escape($product->fullname); ?></span>
            </a>
            <div class="product_tools">
                <?php if ($this->params->get('use_wishlist', false)) : ?>
                    <button class="btn<?php echo ($product->inwishlist) ? ' active' : ''; ?>"
                            title="<?php echo Text::_('COM_ISHOP_WISHLIST_ADD'); ?>"
                            data-towishlist="<?php echo $product->id; ?>">
                        <svg class="svg"><use href="/icons_v3.svg#heart-fill"/></svg>
                        <span class="position-out"><?php echo Text::_('COM_ISHOP_WISHLIST_ADD'); ?></span>
                    </button>
                <?php endif; ?>
                <?php if ($this->params->get('use_compare', false)) : ?>
                    <button class="btn<?php echo ($product->incompare) ? ' active' : ''; ?>"
                            title="<?php echo Text::_('COM_ISHOP_COMPARE_ADD'); ?>"
                            data-tocompare="<?php echo $product->id; ?>">
                        <svg class="svg"><use href="/icons_v3.svg#compare"/></svg>
                        <span class="position-out"><?php echo Text::_('COM_ISHOP_COMPARE_ADD'); ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
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
                    data-tocart="<?php echo $product->id; ?>"><svg class="svg"><use href="/icons_v3.svg#cart"/></svg><span><?php echo $product->delivery; ?></span></button>
            <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php if ($banner && ($product_index === 10 )) : ?>
        <div class="grid-banner-item">
            <?php echo $rend->render($banner, ['style' => 'none']); ?>
        </div>
    <?php endif; ?>
    <?php if ($banner && ($product_index === 20 )) : ?>
        <div class="grid-banner-item-2">
            <?php echo $rend->render($banner, ['style' => 'none']); ?>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
