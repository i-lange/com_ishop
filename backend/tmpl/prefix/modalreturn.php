<?php
/**
 * @package    com_ishop
 * @author     Pavel Lange <pavel@ilange.ru>
 * @link       https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Ilange\Component\Ishop\Site\Helper\RouteHelper;

/** @var Ilange\Component\Ishop\Administrator\View\Prefix\HtmlView $this */

$icon     = 'icon-check';
$title    = $this->item ? $this->item->title : '';
$content  = $this->item ? $this->item->alias : '';
$data     = ['contentType' => 'com_ishop.prefix'];

if ($this->item) {
    $data['id']    = $this->item->id;
    $data['title'] = $this->item->title;
    $data['uri']   = RouteHelper::getPrefixRoute($this->item->id, $this->item->language);
}

// Подключаем скрипт для выбора товара
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('modal-content-select');

// Передаем данные для работы скрипта
$this->getDocument()->addScriptOptions('content-select-on-load', $data, false);
?>
<div class="px-4 py-5 my-5 text-center">
    <span class="fa-8x mb-4 <?php echo $icon; ?>" aria-hidden="true"></span>
    <h1 class="display-5 fw-bold"><?php echo $title; ?></h1>
    <div class="col-lg-6 mx-auto">
        <p class="lead mb-4">
            <?php echo $content; ?>
        </p>
    </div>
</div>