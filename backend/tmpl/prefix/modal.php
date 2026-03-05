<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

/** @var Ilange\Component\Ishop\Administrator\View\Prefix\HtmlView $this */
?>
<div class="subhead noshadow mb-3">
    <?php echo $this->getDocument()->getToolbar()->render(); ?>
</div>
<div class="container-popup">
    <?php $this->setLayout('edit'); ?>
    <?php
    try {
        echo $this->loadTemplate();
    } catch (Exception $e) {

    } ?>
</div>
