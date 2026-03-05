<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

/** @var Ilange\Component\Ishop\Administrator\View\Payment\HtmlView $this */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

$wa = $this->getDocument()->getWebAssetManager();

$wa->useScript('keepalive')
    ->useScript('form.validate');

$this->useCoreUI = true;

$input = Factory::getApplication()->getInput();
$assoc = Associations::isEnabled();

if (!$assoc) {
    $this->ignore_fieldsets[] = 'frontendassociations';
}

// In case of modal
$isModal = $input->get('layout') === 'modal';
$layout  = $isModal ? 'modal' : 'edit';
$tmpl    = $input->get('tmpl');
$tmpl    = $tmpl ? '&tmpl=' . $tmpl : '';
?>
<form action="<?php echo Route::_('index.php?option=com_ishop&layout=' . $layout . $tmpl . '&id=' . (int) $this->item->id); ?>"
      method="post"
      name="adminForm"
      id="item-form"
      aria-label="<?php echo Text::_('COM_ISHOP_' . ((int) $this->item->id === 0 ? 'ADD' : 'EDIT') . '_PAYMENT', true); ?>"
      class="form-validate">
    <div class="row title-alias form-vertical mb-3">
        <div class="col-12 col-md-6">
            <?php echo $this->form->renderField('title'); ?>
        </div>
        <div class="col-12 col-md-6">
            <?php echo $this->form->renderField('alias'); ?>
        </div>
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
                <fieldset id="fieldset-options" class="options-form">
                    <legend><?php echo Text::_('COM_ISHOP_FIELD_OTHER'); ?></legend>
                    <div>
                        <?php echo $this->form->renderField('icon'); ?>
                        <?php echo $this->form->renderField('emoji'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'options', Text::_('COM_ISHOP_FORM_TAB_OPTIONS')); ?>
        <div class="row">
            <div class="col-lg-3">
                <fieldset id="fieldset-image-small" class="options-form">
                    <legend><?php echo Text::_($this->form->getFieldsets()['image-small']->label); ?></legend>
                    <div>
                        <?php echo $this->form->renderFieldset('image-small'); ?>
                    </div>
                </fieldset>
                <fieldset id="fieldset-image-main" class="options-form">
                    <legend><?php echo Text::_($this->form->getFieldsets()['image-main']->label); ?></legend>
                    <div>
                        <?php echo $this->form->renderFieldset('image-main'); ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-9">
                <fieldset id="fieldset-image-more" class="options-form">
                    <legend><?php echo Text::_($this->form->getFieldsets()['image-more']->label); ?></legend>
                    <div><?php echo $this->form->renderFieldset('image-more'); ?></div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'publishing', Text::_('COM_ISHOP_FORM_TAB_PUBLISHING')); ?>
        <fieldset id="fieldset-publishingdata" class="options-form">
            <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
            <div>
                <?php echo LayoutHelper::render('joomla.edit.publishingdata', $this); ?>
            </div>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php if (!$isModal && $assoc) : ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'associations', Text::_('JGLOBAL_FIELDSET_ASSOCIATIONS')); ?>
            <fieldset id="fieldset-associations" class="options-form">
                <legend><?php echo Text::_('JGLOBAL_FIELDSET_ASSOCIATIONS'); ?></legend>
                <div>
                    <?php echo LayoutHelper::render('joomla.edit.associations', $this); ?>
                </div>
            </fieldset>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php elseif ($isModal && $assoc) : ?>
            <div class="hidden"><?php echo LayoutHelper::render('joomla.edit.associations', $this); ?></div>
        <?php endif; ?>
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="return" value="<?php echo $input->getBase64('return'); ?>">
        <input type="hidden" name="forcedLanguage" value="<?php echo $input->get('forcedLanguage', '', 'cmd'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
