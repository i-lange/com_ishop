<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var Ilange\Component\Ishop\Site\View\Category\HtmlView $this */

$ordering = $this->escape($this->state->get('list.ordering', 'a.price'));
$direction = $this->escape($this->state->get('list.direction', 'DESC'));
$fullOrdering = $ordering . ' ' . $direction;
$text = ltrim($ordering, 'a.') . '_' . $direction;
$orderingList = $this->params->get('category_ordering', []);
$showFilter = (!empty(ModuleHelper::getModules('filter')));
?>
<div class="module-category">
<div class="container">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>

    <?php if ($this->params->get('show_category_title')) : ?>
        <h2><?php echo $this->category->title; ?> <span class="category-products-count">(<?php echo $this->pagination->total; ?>)</span></h2>
    <?php endif; ?>

    <?php if ($this->maxLevel != 0 && $this->get('children')) : ?>
        <div class="category-children">
            <?php echo $this->loadTemplate('children'); ?>
        </div>
    <?php endif; ?>

    <div class="category-toolbar">
        <div class="dropdown changeable">
            <button class="dropdown-toggle"
                    title="<?php echo Text::_('COM_ISHOP_MSG_OPEN_SORTING'); ?>"><svg class="svg"><use href="/icons_v3.svg#sorting"/></svg><span class="dropdown-text"><?php echo Text::_('COM_ISHOP_ORDER_' . $text); ?></span></button>
            <ul class="dropdown-menu">
                <?php foreach ($orderingList as $item) : ?>
                    <?php
                    $full = $item->field . ' ' . $item->dir;
                    $attribs = ($full == $fullOrdering)
                        ? ' class="active"'
                        : ' onclick="document.getElementById(\'filter_ordering\').value=\'' . $item->field .
                        '\';document.getElementById(\'filter_direction\').value=\'' . $item->dir .
                        '\';document.getElementById(\'category-ordering\').submit();"';
                    ?>
                    <li<?php echo $attribs; ?>>
                        <span>
                            <?php echo Text::_('COM_ISHOP_ORDER_' . ltrim($item->field, 'a.')  . '_' . $item->dir); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form id="category-ordering"
              class="d-none"
              action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>"
              method="post"
              name="category-ordering">
            <input type="hidden" name="filter_order" id="filter_ordering" value="<?php echo $ordering; ?>">
            <input type="hidden" name="filter_order_Dir" id="filter_direction" value="<?php echo $direction; ?>">
        </form>
        <?php if ($showFilter) : ?>
            <button class="filter-toggle"
                    title="<?php echo Text::_('COM_ISHOP_MSG_OPEN_FILTER'); ?>"
                    aria-label="<?php echo Text::_('TPL_FILTER_LABEL'); ?>"
                    data-offcanvas-open="smartfilter">
                <svg class="svg"><use href="/icons_v3.svg#filter"/></svg>
                <span><?php echo Text::_('TPL_FILTER_ANCHOR'); ?></span>
                <?php if ($this->filter_object->active_count > 0) : ?>
                <small><?php echo $this->filter_object->active_count; ?></small>
                <?php endif; ?>
            </button>
        <?php endif; ?>
    </div>

    <div class="category-products mb-5">
    <?php echo $this->loadTemplate('items'); ?>
    </div>

    <?php if (!empty($this->items) && $this->pagination->pagesTotal > 1) : ?>
        <?php echo $this->pagination->getPagesLinks(); ?>
        <div class="pagination_text mb-5"><?php echo $this->pagination->getPagesCounter(); ?></div>
    <?php endif; ?>
</div>

<?php if ($this->params->get('show_description', 1) && $this->category->description) : ?>
<div class="bg-grey py-5">
    <div class="container">
        <?php echo HTMLHelper::_('content.prepare', $this->category->description, '', 'com_ishop.category'); ?>
    </div>
</div>
<?php endif; ?>
</div>
