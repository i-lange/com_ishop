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
use Joomla\CMS\Language\Text;

/** @var Ilange\Component\Ishop\Site\View\Product\HtmlView $this */
// Сделаем ссылку на изображения товара
$images = $this->item->images;
// Дополнительные фото товара
$more = isset($images->image_more) ? (array) $images->image_more : [];
?>
<?php if (!empty($more) && count($more)) : ?>
    <div class="swiffy-slider slider-nav-dark">
        <ul class="slider-container">
            <?php if (!empty($images->image_main)) : ?>
                <li>
                    <?php $alt = $images->image_main_alt ?: $this->item->fullname; ?>
                    <?php echo ImageHelper::renderImage(
                        $images->image_main, $alt,
                        '(max-width: 439px) calc(100vw - 2rem), 40vw'
                    ); ?>
                </li>
            <?php endif; ?>

            <?php foreach ($more as $image) : ?>
                <?php if (!empty($image->image_item)) : ?>
                    <?php $alt = $image->image_item_alt ?: $this->item->fullname; ?>
                    <li>
                        <?php echo ImageHelper::renderImage(
                            $image->image_item, $alt,
                            '(max-width: 439px) calc(100vw - 2rem), 40vw'
                        ); ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <button type="button"
                title="<?php echo Text::_('TPL_SLIDE_PREV'); ?>"
                class="slider-nav"><span class="position-out"><?php echo Text::_('TPL_SLIDE_PREV'); ?></span></button>
        <button type="button"
                title="<?php echo Text::_('TPL_SLIDE_NEXT'); ?>"
                class="slider-nav slider-nav-next"><span class="position-out"><?php echo Text::_('TPL_SLIDE_NEXT'); ?></span></button>
    </div>
<?php elseif (!empty($images->image_main)) : ?>
    <div class="card_image_single">
        <?php $alt = $images->image_main_alt ?: $this->item->fullname; ?>
        <?php echo ImageHelper::renderImage(
            $images->image_main, $alt,
            '(max-width: 439px) calc(100vw - 2rem), 40vw'
        ); ?>
    </div>
<?php else : ?>
    <div class="card_image_single">
        <svg class="svg"><use href="/icons_v3.svg#image"/></svg>
    </div>
<?php endif; ?>
