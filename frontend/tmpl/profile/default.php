<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\ImageHelper;
use Ilange\Component\Ishop\Site\Helper\PriceHelper;
use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="container pb-5">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <h1><?php echo $this->params->get('page_heading'); ?></h1>
    <?php endif; ?>
    <pre>
        <?php print_r($this->profile); ?>
        <?php print_r($this->orders); ?>
        <?php print_r($this->wishlist); ?>
        <?php print_r($this->compare); ?>
        <?php print_r($this->viewed); ?>
    </pre>
</div>