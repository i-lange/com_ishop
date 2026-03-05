<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

/** @var Ilange\Component\Ishop\Administrator\View\Order\HtmlView $this */

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
      aria-label="<?php echo Text::_('COM_ISHOP_' . ((int) $this->item->id === 0 ? 'ADD' : 'EDIT') . '_ORDER', true); ?>"
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
        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'options', Text::_('COM_ISHOP_FORM_TAB_OPTIONS')); ?>
        <div class="row">
            <div class="col-lg-6">
                <fieldset id="fieldset-options" class="options-form">
                    <legend><?php echo Text::_('COM_ISHOP_FIELD_OTHER'); ?></legend>
                    <div><?php echo $this->form->renderField('ishop_user_id'); ?></div>
                    <div><?php echo $this->form->renderField('products'); ?></div>
                    <div><?php echo $this->form->renderField('discounts'); ?></div>
                    <div><?php echo $this->form->renderField('payment'); ?></div>
                    <div><?php echo $this->form->renderField('delivery'); ?></div>
                </fieldset>
            </div>
            <div class="col-lg-6">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
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
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="return" value="<?php echo $input->getBase64('return'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
