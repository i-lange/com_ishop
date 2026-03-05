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
use Joomla\CMS\Language\Text;

/** @var Ilange\Component\Ishop\Site\View\Product\HtmlView $this */

?>
<div class="container mt-5 pb-5">
    <h2><?php echo Text::_('COM_ISHOP_PRODUCT_FIELDS'); ?></h2>
    <div class="card_extra_fields">
        <?php foreach($this->item->fields as $group) : ?>
            <?php if (empty($group->fields)) continue; ?>
            <div class="card_group">
                <h3><?php echo $group->title; ?></h3>
                <?php foreach($group->fields as $field) : ?>
                    <div class="card_props">
                        <span><?php echo $field->field_title; ?>:</span>
                        <span>
                            <?php if ($field->field_type === 2) : ?>
                                <?php echo ($field->field_value === 'y') ? Text::_('COM_ISHOP_YES') : Text::_('COM_ISHOP_NO'); ?>
                            <?php else : ?>
                                <?php if ($field->field_type === 0) $field->field_value = FormatHelper::renderFloat($field->field_value); ?>
                                <?php echo $field->field_value, ' ', ($field->field_unit !== '') ? ' ' . $field->field_unit : ''; ?>
                            <?php endif; ?>
                            <?php echo ($field->field_value_hint !== '') ? ' (' . $field->field_value_hint . ')' : ''; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>