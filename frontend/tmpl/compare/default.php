<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\FormatHelper;
use Ilange\Component\Ishop\Site\Helper\ImageHelper;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$currency = strtoupper($this->params->get('defaultCurrency', 'BYN'));
$round = (int) $this->params->get('defaultCurrency', 0);
$catId = $this->category_id;
?>
<div class="container pb-5">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <h1><?php echo $this->params->get('page_heading'); ?></h1>
    <?php endif; ?>
    <?php if (!empty($this->compare)) : ?>
        <form id="compare-submit"
              action="<?php echo Route::_(RouteHelper::getCompareRoute()); ?>"
              method="post"
              name="compare-submit">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input id="compare-category-id" type="hidden" name="category_id" value="<?php echo $catId; ?>">
            <div class="dropdown changeable">
                <button class="dropdown-toggle"
                        type="button"
                        title="<?php echo Text::_('COM_ISHOP_MSG_SELECT_CATEGORY'); ?>"><span class="dropdown-text"><?php echo $this->compare[$catId]->title; ?></span><small class="ml-1"><?php echo $this->compare[$catId]->count; ?></small><svg class="svg ml-2"><use href="/icons_v3.svg#caret-down"/></svg></button>
                <ul class="dropdown-menu">
                    <?php foreach ($this->compare as $category) : ?>
                        <?php
                        $attribs = ($catId === $category->id)
                                ? ' class="active"'
                                : ' onclick="document.getElementById(\'compare-category-id\').value=\'' . $category->id .
                                '\';document.getElementById(\'compare-submit\').submit();"';
                        ?>
                        <li<?php echo $attribs; ?>>
                            <span><?php echo $category->title; ?></span>
                            <small class="ml-1"><?php echo $category->count; ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </form>
        <div class="module-compare-scroll" data-drag-scroller>
        <div class="module-compare-scroll-inner">
            <div class="module-compare-products module-compare mt-3">
            <?php foreach ($this->compare[$catId]->products as $product) : ?>
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
            <?php endforeach; ?>
            </div>
            <?php $products_list = array_keys($this->compare[$catId]->products); ?>
            <?php foreach ($this->compare[$catId]->groups as $group) : ?>
                <?php if (!empty($group->fields)) : ?>
                    <h3 class="compare-group-title"><?php echo $group->title; ?></h3><br>
                    <?php foreach ($group->fields as $field) : ?>
                        <?php if (!empty($field->products)) : ?>
                            <div class="compare-field-title"><?php echo $field->title; ?></div>
                            <div class="module-compare compare-value-list<?php echo ($field->ismixed) ? ' mixed' : ''; ?>">
                                <?php foreach ($products_list as $id) : ?>
                                    <div>
                                        <?php if (isset($field->products[$id])) : ?>
                                            <?php $value = $field->products[$id]; ?>
                                            <?php if ($field->type === 2) : ?>
                                                <?php echo ($value->value === 'y') ? Text::_('COM_ISHOP_YES') : Text::_('COM_ISHOP_NO'); ?>
                                            <?php else: ?>
                                                <?php if ($field->type === 0) $value->value = FormatHelper::renderFloat($value->value); ?>
                                                <?php echo $value->value, ' ', ($field->unit !== '') ? ' ' . $field->unit : ''; ?>
                                            <?php endif; ?>
                                            <?php echo ($value->hint !== '') ? ' (' . $value->hint . ')' : ''; ?>
                                        <?php else : ?>-<?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        </div>
    <?php else : ?>
        <div class="module-cart-empty">
            <svg class="svg mega blue-2"><use href="/icons_v3.svg#compare"/></svg>
            <p><?php echo Text::_('COM_ISHOP_COMPARE_NULL'); ?></p>
        </div>
    <?php endif; ?>
</div>