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
$important = $this->params->get('important_fields', []);
$find = [];

foreach($this->item->fields as $groups) {
    foreach($groups->fields as $field_id => $field) {
        if (in_array($field_id, $important)) {
            $unit = ($field->field_unit !== '') ? ' ' . $field->field_unit : '';
            $find[] = $field->value . $unit;
        }
    }
}
?>
<div>
    <h2 class="card_title"><?php echo $this->item->fullname; ?></h2>
    <?php if (!empty($find)) : ?>
    <p><?php echo implode(', ', $find); ?></p>
    <?php endif; ?>
    <?php if ($this->item->introtext !== '') : ?>
    <p class="d-n d-md-block"><?php echo $this->item->introtext; ?></p>
    <?php endif; ?>
    <?php foreach ($this->item->parts as $part) : ?>
        <div class="mb-1 prod_part_type_<?php echo $part->prod_label; ?>">
            <?php if (!empty($part->icon)) : ?>
                <svg class="svg small"><use href="/icons_v3.svg#<?php echo $part->icon; ?>"/></svg>
            <?php endif; ?>
            <a href="#<?php echo $part->alias; ?>" title="<?php echo Text::_('COM_ISHOP_FIELD_PROD_LABEL_' . $part->prod_label); ?>"><?php echo Text::_('COM_ISHOP_FIELD_PROD_LABEL_' . $part->prod_label); ?></a>
            <?php if ($part->prod_label_param > 0) : ?>
                <?php switch ($part->prod_label_param) {
                    case 1:
                        echo $part->min_payment, ' ', Text::_('COM_ISHOP_PAY_PER_MONTH');
                        break;
                    case 2:
                        echo $part->min_rate, '%';
                        break;
                    case 3:
                        echo $part->max_period, ' ', Text::_('COM_ISHOP_PAY_CURRENCY');
                        break;
                }
                ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
