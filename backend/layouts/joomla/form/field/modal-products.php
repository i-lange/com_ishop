<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   string   $class
 * @var   boolean  $disabled
 * @var   string   $hint
 * @var   string   $id
 * @var   string   $name
 * @var   boolean  $readonly
 * @var   boolean  $required
 * @var   array    $urls
 * @var   array    $modalTitles
 * @var   array    $selectedProducts
 */

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

if (!$readonly && !$disabled) {
    $wa->useScript('joomla.dialog')
        ->useScript('com_ishop.admin-modal-products');
}

$baseName = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
$fieldId  = preg_replace('/[^A-Za-z0-9_-]/', '_', $id);
$classes  = trim('com-ishop-modal-products ' . $class);
$readonly = $readonly || $disabled;
$count    = count($selectedProducts);
$summary  = $count === 1 ? Text::_('COM_ISHOP_1_PRODUCT_SELECTED') : Text::sprintf('COM_ISHOP_N_PRODUCTS_SELECTED', $count);

Text::script('COM_ISHOP_SELECT_PRODUCTS');
Text::script('COM_ISHOP_1_PRODUCT_SELECTED');
Text::script('COM_ISHOP_N_PRODUCTS_SELECTED');
Text::script('COM_ISHOP_MODAL_PRODUCTS_SELECT_AT_LEAST_ONE');
?>
<div class="<?php echo $this->escape($classes); ?>"
     id="<?php echo $this->escape($fieldId); ?>_wrapper"
     data-modal-products-field
     data-field-id="<?php echo $this->escape($fieldId); ?>"
     data-input-name="<?php echo $this->escape($baseName); ?>[]"
     data-modal-url="<?php echo $this->escape($urls['select'] ?? ''); ?>"
     data-modal-title="<?php echo $this->escape($modalTitles['select'] ?? Text::_('COM_ISHOP_SELECT_PRODUCTS')); ?>">
    <div class="input-group">
        <input type="text"
               class="form-control"
               value="<?php echo $this->escape($summary); ?>"
               placeholder="<?php echo $this->escape($hint); ?>"
               readonly>
        <?php if (!$readonly) : ?>
            <button type="button"
                    class="btn btn-primary"
                    data-modal-products-select
                    aria-label="<?php echo $this->escape(Text::_('COM_ISHOP_SELECT_PRODUCTS')); ?>">
                <span class="icon-list" aria-hidden="true"></span>
                <?php echo Text::_('JSELECT'); ?>
            </button>
            <button type="button"
                    class="btn btn-secondary"
                    data-modal-products-clear
                    data-show-when-products
                    <?php echo empty($selectedProducts) ? ' hidden' : ''; ?>
                    aria-label="<?php echo $this->escape(Text::_('JGLOBAL_FIELD_REMOVE')); ?>">
                <span class="icon-times" aria-hidden="true"></span>
            </button>
        <?php endif; ?>
    </div>

    <ul class="list-group mt-2"
        data-modal-products-list
        aria-live="polite">
        <?php foreach ($selectedProducts as $product) : ?>
            <li class="list-group-item d-flex align-items-center gap-2"
                data-modal-product-item
                data-id="<?php echo (int) $product['id']; ?>"
                data-title="<?php echo $this->escape($product['title']); ?>">
                <span class="modal-product-title flex-grow-1"><?php echo $this->escape($product['title']); ?></span>
                <?php if (!$readonly) : ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-modal-product-move="up"
                            aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_UP')); ?>">
                        <span class="icon-arrow-up" aria-hidden="true"></span>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-modal-product-move="down"
                            aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_DOWN')); ?>">
                        <span class="icon-arrow-down" aria-hidden="true"></span>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-modal-product-remove
                            aria-label="<?php echo $this->escape(Text::_('JGLOBAL_FIELD_REMOVE')); ?>">
                        <span class="icon-times" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
                <input type="hidden"
                       name="<?php echo $this->escape($baseName); ?>[]"
                       value="<?php echo (int) $product['id']; ?>">
            </li>
        <?php endforeach; ?>
    </ul>

    <template data-modal-products-template>
        <li class="list-group-item d-flex align-items-center gap-2" data-modal-product-item>
            <span class="modal-product-title flex-grow-1"></span>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-modal-product-move="up"
                    aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_UP')); ?>">
                <span class="icon-arrow-up" aria-hidden="true"></span>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-modal-product-move="down"
                    aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_DOWN')); ?>">
                <span class="icon-arrow-down" aria-hidden="true"></span>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    data-modal-product-remove
                    aria-label="<?php echo $this->escape(Text::_('JGLOBAL_FIELD_REMOVE')); ?>">
                <span class="icon-times" aria-hidden="true"></span>
            </button>
            <input type="hidden" name="<?php echo $this->escape($baseName); ?>[]" value="">
        </li>
    </template>

    <?php if ($required) : ?>
        <input type="text"
               class="visually-hidden"
               tabindex="-1"
               aria-hidden="true"
               data-modal-products-required
               value="<?php echo empty($selectedProducts) ? '' : '1'; ?>"
               required>
    <?php endif; ?>
</div>
