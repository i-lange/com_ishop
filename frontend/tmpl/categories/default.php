<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
?>
<div class="container">
    <?php if ($this->params->get('show_page_heading')) : ?>
    <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>
    <?php if ($this->params->get('show_base_description')) : ?>
        <?php // Если в параметрах меню есть описание, используем его ?>
        <?php if ($this->params->get('categories_description')) : ?>
            <div class="category-description">
                <?php echo HTMLHelper::_('content.prepare', $this->params->get('categories_description'), '', $this->get('extension') . '.categories'); ?>
            </div>
        <?php else : ?>
            <?php // Иначе получаем описание из базы данных, если оно существует ?>
            <?php if ($this->parent->description) : ?>
                <div class="category-description">
                    <?php echo HTMLHelper::_('content.prepare', $this->parent->description, '', $this->parent->extension . '.categories'); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <div class="module-categories">
    <?php echo $this->loadTemplate('items'); ?>
    </div>
</div>