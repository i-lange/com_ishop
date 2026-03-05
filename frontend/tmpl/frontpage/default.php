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
use Ilange\Component\Ishop\Site\Helper\ImageHelper;
?>
<div class="container">
<?php if ($this->params->get('show_page_heading')) : ?>
    <h1><?php echo $this->params->get('page_heading'); ?></h1>
<?php endif; ?>

<?php if ($this->params->get('show_slider') && !empty($slides = $this->params->get('slides', []))) : ?>
<div class="swiffy-slider slider-item-ratio slider-item-ratio-<?php echo $this->params->get('slides_ratio', '16x9'); ?> slider-nav-autoplay slider-nav-autopause" data-slider-nav-autoplay-interval="4000">
    <ul class="slider-container">
    <?php foreach ($slides as $slide) : ?>
        <li class="radius">
            <?php echo ImageHelper::renderImage(
                $slide['slide_image'], $slide['slide_image_alt'],
                '(max-width: 439px) calc(100vw - 2rem), 100vw',
                'radius'
            ); ?>
            <?php if (!empty($slide['slide_url'])) : ?>
                <a class="cover" href="<?php echo $slide['slide_url']; ?>" title="<?php echo $slide['slide_title']; ?>">
                    <span class="position-out"><?php echo $slide['slide_title']; ?></span>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
    <button type="button"
            class="slider-nav"
            title="<?php echo Text::_('TPL_SLIDE_PREV'); ?>"><span class="position-out"><?php echo Text::_('TPL_SLIDE_PREV'); ?></span></button>
    <button type="button"
            class="slider-nav slider-nav-next"
            title="<?php echo Text::_('TPL_SLIDE_NEXT'); ?>"><span class="position-out"><?php echo Text::_('TPL_SLIDE_NEXT'); ?></span></button>
    <ul class="slider-indicators">
        <?php for ($i = 0; $i < count($slides); $i++) : ?>
        <li<?php echo ($i === 0) ? ' class="active"' : ''; ?>></li>
        <?php endfor; ?>
    </ul>
</div>
<?php unset($slides); ?>
<?php endif; ?>

<?php if ($this->params->get('show_services') && !empty($services = $this->params->get('services', []))) : ?>
<div class="module-services mt-3">
    <?php foreach ($services as $service) : ?>
        <div class="service">
            <?php if (!empty($service['service_title'])) : ?>
                <div class="service-title"><?php echo $service['service_title']; ?></div>
            <?php endif; ?>
            <?php if (!empty($service['service_description'])) : ?>
                <div class="service-description"><?php echo $service['service_description']; ?></div>
            <?php endif; ?>
            <?php echo ImageHelper::renderImage(
                $service['service_image'], $service['service_image_alt'],
                '(max-width: 439px) calc(100vw - 2rem), 100vw',
                ''
            ); ?>
            <?php if (!empty($service['service_url'])) : ?>
                <a class="cover" href="<?php echo $service['service_url']; ?>" title="<?php echo $service['service_title']; ?>">
                    <span class="position-out"><?php echo $service['service_title']; ?></span>
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php unset($services); ?>
<?php endif; ?>

<?php if ($this->params->get('show_categories') && !empty($this->categories)) : ?>
    <div class="box-shadow mt-3 mt-lg-5">
        <h2><?php echo Text::_('TPL_CATALOG_ANCHOR'); ?></h2>
        <div class="module-categories">
        <?php echo $this->loadTemplate('categories'); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($this->params->get('show_text') && !empty($this->text)) : ?>
    <div class="py-5">
        <?php echo $this->text->introtext . $this->text->fulltext; ?>
    </div>
<?php endif; ?>
</div>