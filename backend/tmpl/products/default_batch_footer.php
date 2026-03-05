<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->document->getWebAssetManager();
$wa->useScript('com_ishop.admin-product-batch');

?>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
    <?php echo Text::_('JCANCEL'); ?>
</button>
<button type="submit" id='batch-submit-button-id' class="btn btn-success" data-submit-task='product.batch'>
    <?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?>
</button>
