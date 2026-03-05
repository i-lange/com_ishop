<?php
/**
 * @package        com_ishop
 * @author         Pavel Lange <pavel@ilange.ru>
 * @link           https://github.com/i-lange/com_ishop
 * @copyright  (C) 2023 Pavel Lange <https://ilange.ru>
 * @license        GNU General Public License version 2 or later
 */

namespace Ilange\Component\Ishop\Site\Helper;

use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die;

/**
 * Helper компонента com_ishop для вывода изображений
 * @since 1.0.0
 */
class ImageHelper
{
    /**
     * Формируем html код изображения
     *
     * @param   string       $image
     * @param   string       $alt
     * @param   string       $class
     * @param   string|null  $srcset
     * @param   string|null  $sizes
     *
     * @return  string  html изображения
     * @since 1.0.0
     */
    public static function renderImage(string $image, string $alt = '', string $sizes = null, string $class = '', string $srcset = null)
    {
        $image = HTMLHelper::cleanImageURL($image);

        $attributes = [
            'width'         => $image->attributes['width'],
            'height'        => $image->attributes['height'],
            'alt'           => $alt,
            'class'         => $class,
            'src'           => '/' . ltrim($image->url, '/'),
            'decoding'      => 'async',
            'itemprop'      => 'image',
        ];

        if ($srcset && $sizes) {
            $attributes['srcset'] = '/images/placeholder.svg';
            $attributes['data-srcset'] = $srcset;
            $attributes['sizes'] = $sizes;
        } elseif ($sizes) {
            $attributes['srcset'] = '/images/placeholder.svg';
            $attributes['data-srcset'] = '/' . ltrim($image->url, '/') . ' ' . $image->attributes['width'] . 'w';
            $attributes['sizes'] = $sizes;
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= $name . '="' . $value . '" ';
        }

        return '<img ' . $html . '>';
    }
}