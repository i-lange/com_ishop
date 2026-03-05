<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

/** @var Ilange\Component\Ishop\Administrator\View\Product\HtmlView $this */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$this->useCoreUI = true;

$input = Factory::getApplication()->getInput();
$assoc = Associations::isEnabled();

if (!$assoc) {
    $this->ignore_fieldsets[] = 'frontendassociations';
}

// Для отображения в модальном окне
$isModal = $input->get('layout') === 'modal';
$layout  = $isModal ? 'modal' : 'edit';
$tmpl    = $input->get('tmpl');
$tmpl    = $tmpl ? '&tmpl=' . $tmpl : '';
?>
<form action="<?php echo Route::_('index.php?option=com_ishop&layout=' . $layout . $tmpl . '&id=' . (int) $this->item->id); ?>"
      method="post"
      name="adminForm"
      id="item-form"
      aria-label="<?php echo Text::_('COM_ISHOP_' . ((int) $this->item->id === 0 ? 'ADD' : 'EDIT') . '_PRODUCT', true); ?>"
      class="form-validate">
    <div class="row title-alias form-vertical mb-3">
        <div class="col-md-4"><?php echo $this->form->renderField('prefix_id'); ?></div>
        <div class="col-md-4"><?php echo $this->form->renderField('title'); ?></div>
        <div class="col-md-4"><?php echo $this->form->renderField('alias'); ?></div>
    </div>
    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]); ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'general', Text::_('COM_ISHOP_FORM_TAB_GENERAL')); ?>
        <div class="row">
            <div class="col-lg-9">
                <div>
                    <fieldset id="fieldset-introtext" class="options-form">
                        <legend><?php echo $this->form->getLabel('introtext'); ?></legend>
                        <div><?php echo $this->form->getInput('introtext'); ?></div>
                    </fieldset>
                    <fieldset id="fieldset-fulltext" class="options-form">
                        <legend><?php echo $this->form->getLabel('fulltext'); ?></legend>
                        <div><?php echo $this->form->getInput('fulltext'); ?></div>
                    </fieldset>
                </div>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
                <?php echo $this->form->renderField('rating'); ?>
                <?php echo $this->form->renderField('reviews_count'); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php if (!empty($this->item->ishop_fields)) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'ishopfields', Text::_('COM_ISHOP_FORM_TAB_FIELDS')); ?>
            <?php foreach ($this->item->ishop_fields as $group) : ?>
            <div>
                <fieldset id="fieldset-ishop-fields-<?php echo $group['alias']; ?>" class="options-form">
                    <legend><?php echo $group['title']; ?></legend>
                    <div>
                        <?php
                            $i = 0;
                            $count = count($group['fields']);
                        ?>
                        <?php foreach ($group['fields'] as $field) : ?>
                            <?php $i++; ?>
                            <div class="control-group">
                                <div class="controls"><?php echo $this->form->renderField('ishop_field_' . $field['id']); ?></div>
                                <div class="controls ms-sm-3 mt-0"><?php echo $this->form->renderField('ishop_field_hint_' . $field['id']); ?></div>
                            </div>
                            <?php if ($i < $count) : ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            <?php endforeach; ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'options', Text::_('COM_ISHOP_FORM_TAB_OPTIONS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <div class="row">
                    <div class="col-md-4">
                        <fieldset id="fieldset-image-small" class="options-form">
                            <legend><?php echo Text::_($this->form->getFieldsets()['image-small']->label); ?></legend>
                            <div><?php echo $this->form->renderFieldset('image-small'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-md-4">
                        <fieldset id="fieldset-image-main" class="options-form">
                            <legend><?php echo Text::_($this->form->getFieldsets()['image-main']->label); ?></legend>
                            <div><?php echo $this->form->renderFieldset('image-main'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-md-4">
                        <fieldset id="fieldset-image-export" class="options-form">
                            <legend><?php echo Text::_($this->form->getFieldsets()['image-export']->label); ?></legend>
                            <div><?php echo $this->form->renderFieldset('image-export'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-md-4">
                        <fieldset id="fieldset-image-nobg" class="options-form">
                            <legend><?php echo Text::_($this->form->getFieldsets()['image-nobg']->label); ?></legend>
                            <div><?php echo $this->form->renderFieldset('image-nobg'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-md-4">
                        <fieldset id="fieldset-image-info" class="options-form">
                            <legend><?php echo Text::_($this->form->getFieldsets()['image-info']->label); ?></legend>
                            <div><?php echo $this->form->renderFieldset('image-info'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-12">
                        <fieldset id="fieldset-image-more" class="options-form">
                            <legend><?php echo Text::_($this->form->getFieldsets()['image-more']->label); ?></legend>
                            <div><?php echo $this->form->renderFieldset('image-more'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-12">
                        <fieldset id="reach_icons" class="options-form">
                            <legend><?php echo $this->form->getLabel('reach_icons'); ?></legend>
                            <div><?php echo $this->form->getInput('reach_icons'); ?></div>
                        </fieldset>
                    </div>
                    <div class="col-12">
                        <fieldset id="reach_features" class="options-form">
                            <legend><?php echo $this->form->getLabel('reach_features'); ?></legend>
                            <div><?php echo $this->form->getInput('reach_features'); ?></div>
                        </fieldset>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <?php echo $this->form->renderField('manufacturer_id'); ?>
                <?php echo $this->form->renderField('supplier_id'); ?>
                <?php echo $this->form->renderField('country'); ?>
                <?php echo $this->form->renderField('warranty'); ?>
                <?php echo $this->form->renderField('type'); ?>
                <?php echo $this->form->renderField('gtin'); ?>
                <?php echo $this->form->renderField('price'); ?>
                <?php echo $this->form->renderField('old_price'); ?>
                <?php echo $this->form->renderField('sale_price'); ?>
                <?php echo $this->form->renderField('cost_price'); ?>
                <?php echo $this->form->renderField('stock'); ?>
                <?php echo $this->form->renderField('width'); ?>
                <?php echo $this->form->renderField('height'); ?>
                <?php echo $this->form->renderField('depth'); ?>
                <?php echo $this->form->renderField('weight'); ?>
                <?php echo $this->form->renderField('width_pkg'); ?>
                <?php echo $this->form->renderField('height_pkg'); ?>
                <?php echo $this->form->renderField('depth_pkg'); ?>
                <?php echo $this->form->renderField('weight_pkg'); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'related', Text::_('COM_ISHOP_FORM_TAB_RELATED')); ?>
        <div>
            <fieldset>
                <?php echo $this->form->renderField('services'); ?>
                <?php echo $this->form->renderField('importers'); ?>
                <?php echo $this->form->renderField('offers'); ?>
                <?php echo $this->form->renderField('related'); ?>
                <?php echo $this->form->renderField('similar'); ?>
            </fieldset>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'documents', Text::_('COM_ISHOP_FORM_TAB_DOCUMENTS')); ?>
        <div>
            <fieldset>
                <?php echo $this->form->renderField('equipment'); ?>
                <?php echo $this->form->renderField('documents'); ?>
                <?php echo $this->form->renderField('videos'); ?>
            </fieldset>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'integrations', Text::_('COM_ISHOP_FORM_TAB_INTEGRATION')); ?>
        <div>
            <fieldset id="fieldset-attribs" class="options-form">
                <legend><?php echo Text::_('COM_ISHOP_FIELD_PARAMS_OPTIONS'); ?></legend>
                <div><?php echo $this->form->getInput('attribs'); ?></div>
            </fieldset>
            <fieldset id="fieldset-search" class="options-form">
                <legend><?php echo Text::_('COM_ISHOP_FIELD_SEARCH'); ?></legend>
                <div><?php echo $this->form->renderField('search_keys'); ?></div>
            </fieldset>
            <fieldset id="fieldset-parse" class="options-form">
                <legend><?php echo Text::_('COM_ISHOP_FIELD_PARSE'); ?></legend>
                <div>
                    <?php echo $this->form->renderField('parse_url'); ?>
                    <?php echo $this->form->renderField('onliner_url'); ?>
                </div>
            </fieldset>
            <fieldset id="fieldset-links" class="options-form">
                <legend><?php echo Text::_('COM_ISHOP_FIELD_LINKS'); ?></legend>
                <div>
                    <?php echo $this->form->renderField('bitrix24_id'); ?>
                    <?php echo $this->form->renderField('system1c_guid'); ?>
                    <?php echo $this->form->renderField('system1c_name'); ?>
                    <?php echo $this->form->renderField('zoomos_id'); ?>
                    <?php echo $this->form->renderField('shopmanager_id'); ?>
                    <?php echo $this->form->renderField('onliner_sku'); ?>
                </div>
            </fieldset>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'publishing', Text::_('COM_ISHOP_FORM_TAB_PUBLISHING')); ?>
        <div class="row">
            <div class="col-12 col-lg-6">
                <fieldset id="fieldset-publishingdata" class="options-form">
                    <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                    <div><?php echo LayoutHelper::render('joomla.edit.publishingdata', $this); ?></div>
                </fieldset>
            </div>
            <div class="col-12 col-lg-6">
                <fieldset id="fieldset-metadata" class="options-form">
                    <legend><?php echo Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'); ?></legend>
                    <div>
                        <?php echo $this->form->renderField('metatitle'); ?>
                        <?php echo LayoutHelper::render('joomla.edit.metadata', $this); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php if (!$isModal && $assoc) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'associations', Text::_('JGLOBAL_FIELDSET_ASSOCIATIONS')); ?>
            <fieldset id="fieldset-associations" class="options-form">
                <legend><?php echo Text::_('JGLOBAL_FIELDSET_ASSOCIATIONS'); ?></legend>
                <div><?php echo LayoutHelper::render('joomla.edit.associations', $this); ?></div>
            </fieldset>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php elseif ($isModal && $assoc) : ?>
            <div class="hidden"><?php echo LayoutHelper::render('joomla.edit.associations', $this); ?></div>
        <?php endif; ?>
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <?php echo $this->form->renderControlFields(); ?>
    </div>
</form>
