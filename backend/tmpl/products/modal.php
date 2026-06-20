<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2026 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var Ilange\Component\Ishop\Administrator\View\Products\HtmlView $this */

$app = Factory::getApplication();

if ($app->isClient('site')) {
    Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
}

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core')
    ->useScript('modal-content-select');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$multilang = Multilanguage::isEnabled();
$multi     = (bool) $app->getInput()->getInt('multi', 0);
$selected  = array_filter(array_map('intval', explode(',', (string) $app->getInput()->getString('selected', ''))));
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_ishop&view=products&layout=modal&tmpl=component' . ($multi ? '&multi=1&selected=' . implode(',', $selected) : '') . '&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">
        <?php if ($multi) : ?>
            <?php $wa->useScript('com_ishop.admin-modal-items'); ?>
            <?php Text::script('COM_ISHOP_MODAL_ITEMS_SELECT_AT_LEAST_ONE'); ?>
            <div class="mb-3">
                <button type="button"
                        class="btn btn-primary"
                        data-modal-items-add
                        data-empty-selection-key="COM_ISHOP_MODAL_ITEMS_SELECT_AT_LEAST_ONE">
                    <span class="icon-plus" aria-hidden="true"></span>
                    <?php echo Text::_('COM_ISHOP_MODAL_PRODUCTS_ADD_SELECTED'); ?>
                </button>
            </div>
        <?php endif; ?>
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-sm">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_ISHOP_PRODUCTS'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <?php if ($multi) : ?>
                            <th scope="col" class="w-1 text-center">
                                <span class="visually-hidden"><?php echo Text::_('JSELECT'); ?></span>
                            </th>
                        <?php endif; ?>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="title">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?>
                        </th>
                        <?php if ($multilang) : ?>
                            <th scope="col" class="w-15">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_LANGUAGE', 'language', $listDirn, $listOrder); ?>
                            </th>
                        <?php endif; ?>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-1 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $iconStates = [
                    -2 => 'icon-trash',
                    0  => 'icon-unpublish',
                    1  => 'icon-publish',
                    2  => 'icon-archive',
                ];
                ?>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php
                    $lang = '';
                    if ($item->language && $multilang) {
                        $tag = strlen($item->language);
                        if ($tag == 5) {
                            $lang = substr($item->language, 0, 2);
                        } elseif ($tag == 6) {
                            $lang = substr($item->language, 0, 3);
                        }
                    }

                    $titleParts = array_filter([
                        $item->prefix_title ?? '',
                        $item->manufacturer_title ?? '',
                        $item->title ?? '',
                    ]);
                    $title    = trim(implode(' ', $titleParts));
                    $link     = RouteHelper::getProductRoute((int) $item->id, (int) $item->catid, $item->language);
                    $itemHtml = '<a href="' . $this->escape($link) . '"' . ($lang ? ' hreflang="' . $lang . '"' : '') . '>' . $title . '</a>';
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <?php $titleEscaped = $this->escape($title); ?>
                        <?php $checkboxId = 'modal-product-' . (int) $item->id; ?>
                        <?php if ($multi) : ?>
                            <td class="text-center">
                                <input type="checkbox"
                                       id="<?php echo $checkboxId; ?>"
                                       class="form-check-input"
                                       value="<?php echo (int) $item->id; ?>"
                                       data-modal-item-checkbox
                                       data-title="<?php echo $titleEscaped; ?>"
                                       <?php echo in_array((int) $item->id, $selected, true) ? ' checked' : ''; ?>>
                            </td>
                        <?php endif; ?>
                        <td class="text-center">
                            <span class="tbody-icon">
                                <span class="<?php echo $iconStates[$this->escape($item->state)]; ?>" aria-hidden="true"></span>
                            </span>
                        </td>
                        <th scope="row">
                            <?php $attribs = 'data-content-select data-content-type="com_ishop.product"'
                                . ' data-id="' . (int) $item->id . '"'
                                . ' data-title="' . $titleEscaped . '"'
                                . ' data-cat-id="' . (int) $item->catid . '"'
                                . ' data-uri="' . $this->escape($link) . '"'
                                . ' data-language="' . $this->escape($lang) . '"'
                                . ' data-html="' . $this->escape($itemHtml) . '"';
                            ?>
                            <?php if ($multi) : ?>
                                <label class="select-link" for="<?php echo $checkboxId; ?>">
                                    <?php echo $this->escape($item->prefix_title); ?> <?php echo $this->escape($item->manufacturer_title); ?><br>
                                    <?php echo $this->escape($item->title); ?>
                                </label>
                            <?php else : ?>
                                <a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>
                                    <?php echo $this->escape($item->prefix_title); ?> <?php echo $this->escape($item->manufacturer_title); ?><br>
                                    <?php echo $this->escape($item->title); ?>
                                </a>
                            <?php endif; ?>
                            <div class="small break-word">
                                <?php if (empty($item->note)) : ?>
                                    <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?>
                                <?php else : ?>
                                    <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($item->alias), $this->escape($item->note)); ?>
                                <?php endif; ?>
                            </div>
                        </th>
                        <td class="small d-none d-md-table-cell">
                            <?php echo $this->escape($item->category_title); ?>
                        </td>
                        <?php if ($multilang) : ?>
                            <td class="small">
                                <?php echo LayoutHelper::render('joomla.content.language', $item); ?>
                            </td>
                        <?php endif; ?>
                        <td class="small d-none d-md-table-cell">
                            <?php echo $item->created > 0 ? HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')) : '-'; ?>
                        </td>
                        <td class="small d-none d-md-table-cell">
                            <?php echo (int) $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>
        <?php echo $this->filterForm->renderControlFields(); ?>
    </form>
</div>
