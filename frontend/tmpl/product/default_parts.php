<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var Ilange\Component\Ishop\Site\View\Product\HtmlView $this */
?>
<div class="bg-blue py-5">
    <div class="module-part container">
        <h2>Оплата частями</h2>
        <?php foreach ($this->item->parts as $part) : ?>
            <?php $isMulti = count($part->rules) > 1; ?>
            <div class="payment-part" id="<?php echo $part->alias; ?>">
                <div class="payment-part-title">
                    <svg class="svg"><use href="/icons_v3.svg#<?php echo $part->icon; ?>"/></svg>
                    <div><span class="payment-part-header"><?php echo $part->title; ?></span><br>
                        <span class="payment-part-desc"><?php echo $part->introtext; ?></span></div>
                </div>
                <div class="payment-rules">
                    <?php if ($isMulti) : ?>
                    <div class="swiffy-slider slider-nav-mousedrag slider-item-show3-xl slider-item-show2-lg slider-item-reveal slider-item-snapstart">
                    <ul class="slider-container">
                    <?php endif; ?>

                    <?php foreach ($part->rules as $period => $rule) : ?>
                        <?php if ($isMulti) : ?><li><?php endif; ?>
                        <div class="payment-rule">
                            <div class="payment-rule-title"><span class="payment-rule-monthly blue-1"><?php echo $rule->monthly_payment; ?></span>
                                <span class="blue-1"><?php echo Text::_('COM_ISHOP_PAY_PER_MONTH'); ?></span><br><?php echo Text::_('COM_ISHOP_PART_ON'); ?> <?php echo $period . ' ' . Text::plural('COM_ISHOP_MONTH', $period); ?></div>
                            <div><?php echo Text::_('COM_ISHOP_PART_TOTAL'), ' ', $rule->total_payment, ' ', Text::_('COM_ISHOP_PAY_CURRENCY'); ?></div>
                            <?php if ($part->first_part > 0) : ?>
                                <div><?php echo Text::_('COM_ISHOP_PART_FIRST_PAY'), ' ', $part->first_part; ?>%</div>
                            <?php else: ?>
                                <div><?php echo Text::_('COM_ISHOP_PART_FIRST_PAY_NONE'); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($isMulti) : ?></li><?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($isMulti) : ?>
                    </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
