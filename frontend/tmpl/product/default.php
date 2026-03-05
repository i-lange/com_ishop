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
use Joomla\CMS\Language\Text;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
// Сделаем ссылку на товар
$product = $this->item;
// Регистрируем просмотр карточки товара
$this->getModel()->hit($product->id);
// Блоки с подробной информацией о товаре
$tabs = ['fields', 'fulltext', 'parts', 'payments', 'deliveries', 'reviews', ];

$currency = strtoupper($this->params->get('defaultCurrency', 'BYN'));
$dataLayerItems = [];
$dataLayerPrice = ($product->discount_size > 0) ? $product->sale_price : $product->price;
$dataLayerItems[] = [
    'item_id'       => $product->id,
    'item_name'     => $this->escape($product->fullname),
    'discount'      => $product->discount_size,
    'index'         => 1,
    'item_brand'    => $product->manufacturer_title,
    'item_category' => $product->category_title,
    'price'         => $dataLayerPrice,
    'quantity'      => 1,
];
$jsonLayerItems = json_encode($dataLayerItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$dataLayer = 'const dataLayerItems = ' . $jsonLayerItems . ';';
$wa->addInlineScript($dataLayer);
$dataLayer = 'gtag("event","view_item",{currency:"' . $currency . '",value:"' . $dataLayerPrice . '",items:dataLayerItems});';
$wa->addInlineScript($dataLayer);
?>
<div class="module-card">
    <div class="container">
        <div class="product_cart_labels">
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
        <div class="card_first_block">
            <div class="card_images">
                <?php echo $this->loadTemplate('images'); ?>
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
            <div class="card_description">
                <?php echo $this->loadTemplate('description'); ?>
            </div>
            <div class="card_buttons">
                <div><?php echo $this->loadTemplate('buttons'); ?></div>
            </div>
        </div>
        <div class="card_fixed_block"><?php echo $this->loadTemplate('fixed'); ?></div>
    </div>
    <?php foreach($tabs as $tab) : ?>
        <?php if (!empty($product->$tab)) : ?>
            <?php echo $this->loadTemplate($tab); ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="container">
        <hr class="mt-5">
        <div class="small mb-5">
            <p><?php echo Text::_('COM_ISHOP_INFO_WARNING_01'); ?></p>
            <p><?php echo Text::_('COM_ISHOP_INFO_WARNING_02'); ?></p>
        </div>
    </div>
</div>
