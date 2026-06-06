<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

/** @var Ilange\Component\Ishop\Administrator\View\Filter\HtmlView $this */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$wa = $this->getDocument()->getWebAssetManager();
$wa
    ->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('com_ishop.admin-filter');

$this->useCoreUI = true;

$input = Factory::getApplication()->getInput();
$tmpl = $input->get('tmpl');
$tmpl = $tmpl ? '&tmpl=' . $tmpl : '';

$this->getDocument()->addScriptOptions('com_ishop.adminFilter', [
    'endpoint' => Route::_('index.php?option=com_ishop&task=filter.categoryFields&format=json&' . Session::getFormToken() . '=1', false),
    'messages' => [
        'selectCategory' => Text::_('COM_ISHOP_FILTER_SELECT_CATEGORY_FIRST'),
        'noFields' => Text::_('COM_ISHOP_FILTER_NO_FIELDS'),
        'min' => Text::_('COM_ISHOP_FILTER_MIN_VALUE'),
        'max' => Text::_('COM_ISHOP_FILTER_MAX_VALUE'),
        'yes' => Text::_('JYES'),
    ],
]);
?>
<form action="<?php echo Route::_('index.php?option=com_ishop&layout=edit' . $tmpl . '&id=' . (int) $this->item->id); ?>"
      method="post"
      name="adminForm"
      id="item-form"
      aria-label="<?php echo Text::_('COM_ISHOP_' . ((int) $this->item->id === 0 ? 'ADD' : 'EDIT') . '_FILTER', true); ?>"
      class="form-validate">
    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]); ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'general', Text::_('COM_ISHOP_FORM_TAB_GENERAL')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-description" class="options-form">
                    <legend><?php echo $this->form->getLabel('description'); ?></legend>
                    <div><?php echo $this->form->getInput('description'); ?></div>
                </fieldset>
                <fieldset id="fieldset-metadata" class="options-form">
                    <legend><?php echo Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'); ?></legend>
                    <div>
                        <?php echo $this->form->renderField('heading'); ?>
                        <?php echo $this->form->renderField('metatitle'); ?>
                        <?php echo $this->form->renderField('metadesc'); ?>
                        <?php echo $this->form->renderField('metakey'); ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
                <fieldset id="fieldset-filter" class="options-form">
                    <legend><?php echo Text::_('COM_ISHOP_FILTER_CONDITIONS'); ?></legend>
                    <div>
                        <?php echo $this->form->renderField('category_id'); ?>
                        <?php echo $this->form->renderField('manufacturers'); ?>
                        <?php echo $this->form->renderField('ishop_fields'); ?>
                        <div class="row">
                            <div class="col-6"><?php echo $this->form->renderField('min_width'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('max_width'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('min_height'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('max_height'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('min_depth'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('max_depth'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('min_weight'); ?></div>
                            <div class="col-6"><?php echo $this->form->renderField('max_weight'); ?></div>
                        </div>
                    </div>
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
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="return" value="<?php echo $input->getBase64('return'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
