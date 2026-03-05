<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var Ilange\Component\Ishop\Administrator\View\Products\HtmlView $this */
?>
<div class="p-3">
    <div class="row">
        <?php if (Multilanguage::isEnabled()) : ?>
            <div class="form-group col-md-6">
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.html.batch.language', []); ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="form-group col-md-6">
            <div class="controls">
                <?php echo LayoutHelper::render('joomla.html.batch.access', []); ?>
            </div>
        </div>
    </div>
    <div class="row">
        <?php if ((int) $this->state->get('filter.published') >= 0) : ?>
        <div class="form-group col-md-6">
            <div class="controls">
                <?php echo LayoutHelper::render('joomla.html.batch.item', ['extension' => 'com_ishop']); ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="form-group col-md-6">
            <div class="controls">
                <?php echo LayoutHelper::render('joomla.html.batch.tag', []); ?>
            </div>
        </div>
    </div>
</div>
