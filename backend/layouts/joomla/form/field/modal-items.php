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
 * @var   array    $selectedItems
 * @var   string   $fieldClass
 * @var   string   $selectTitleKey
 * @var   string   $selectedOneKey
 * @var   string   $selectedManyKey
 * @var   string   $emptySelectionKey
 */

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

if (!$readonly && !$disabled) {
    $wa->useScript('joomla.dialog')
        ->useScript('com_ishop.admin-modal-items');
}

$baseName = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
$fieldId  = preg_replace('/[^A-Za-z0-9_-]/', '_', $id);
$classes  = trim('com-ishop-modal-items ' . ($fieldClass ?? '') . ' ' . $class);
$readonly = $readonly || $disabled;
$count    = count($selectedItems);
$summary  = $count === 1 ? Text::_($selectedOneKey) : Text::sprintf($selectedManyKey, $count);

Text::script($selectTitleKey);
Text::script($selectedOneKey);
Text::script($selectedManyKey);
Text::script($emptySelectionKey);
?>
<div class="<?php echo $this->escape($classes); ?>"
     id="<?php echo $this->escape($fieldId); ?>_wrapper"
     data-modal-items-field
     data-field-id="<?php echo $this->escape($fieldId); ?>"
     data-input-name="<?php echo $this->escape($baseName); ?>[]"
     data-modal-url="<?php echo $this->escape($urls['select'] ?? ''); ?>"
     data-modal-title="<?php echo $this->escape($modalTitles['select'] ?? Text::_($selectTitleKey)); ?>"
     data-selected-one-key="<?php echo $this->escape($selectedOneKey); ?>"
     data-selected-many-key="<?php echo $this->escape($selectedManyKey); ?>"
     data-empty-selection-key="<?php echo $this->escape($emptySelectionKey); ?>">
    <div class="input-group">
        <input type="text"
               class="form-control"
               value="<?php echo $this->escape($summary); ?>"
               placeholder="<?php echo $this->escape($hint); ?>"
               readonly>
        <?php if (!$readonly) : ?>
            <button type="button"
                    class="btn btn-primary"
                    data-modal-items-select
                    aria-label="<?php echo $this->escape(Text::_($selectTitleKey)); ?>">
                <span class="icon-list" aria-hidden="true"></span>
                <?php echo Text::_('JSELECT'); ?>
            </button>
            <button type="button"
                    class="btn btn-secondary"
                    data-modal-items-clear
                    <?php echo empty($selectedItems) ? ' hidden' : ''; ?>
                    aria-label="<?php echo $this->escape(Text::_('JGLOBAL_FIELD_REMOVE')); ?>">
                <span class="icon-times" aria-hidden="true"></span>
            </button>
        <?php endif; ?>
    </div>

    <ul class="list-group mt-2"
        data-modal-items-list
        aria-live="polite">
        <?php foreach ($selectedItems as $item) : ?>
            <li class="list-group-item d-flex align-items-center gap-2"
                data-modal-item
                data-id="<?php echo (int) $item['id']; ?>"
                data-title="<?php echo $this->escape($item['title']); ?>">
                <span class="modal-item-title flex-grow-1"><?php echo $this->escape($item['title']); ?></span>
                <?php if (!$readonly) : ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-modal-item-move="up"
                            aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_UP')); ?>">
                        <span class="icon-arrow-up" aria-hidden="true"></span>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-modal-item-move="down"
                            aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_DOWN')); ?>">
                        <span class="icon-arrow-down" aria-hidden="true"></span>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-modal-item-remove
                            aria-label="<?php echo $this->escape(Text::_('JGLOBAL_FIELD_REMOVE')); ?>">
                        <span class="icon-times" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>
                <input type="hidden"
                       name="<?php echo $this->escape($baseName); ?>[]"
                       value="<?php echo (int) $item['id']; ?>">
            </li>
        <?php endforeach; ?>
    </ul>

    <template data-modal-items-template>
        <li class="list-group-item d-flex align-items-center gap-2" data-modal-item>
            <span class="modal-item-title flex-grow-1"></span>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-modal-item-move="up"
                    aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_UP')); ?>">
                <span class="icon-arrow-up" aria-hidden="true"></span>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary"
                    data-modal-item-move="down"
                    aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_MOVE_DOWN')); ?>">
                <span class="icon-arrow-down" aria-hidden="true"></span>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    data-modal-item-remove
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
               data-modal-items-required
               value="<?php echo empty($selectedItems) ? '' : '1'; ?>"
               required>
    <?php endif; ?>
</div>
