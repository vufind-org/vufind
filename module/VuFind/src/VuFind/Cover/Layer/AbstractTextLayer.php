<?php
/**
 * Abstract cover text layer
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\Cover\Layer;

/**
 * Abstract cover text layer
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractTextLayer extends AbstractLayer
{
    /**
     * Returns the width a string would render to
     *
     * @param string $text Text to test
     * @param string $font Full font path
     * @param string $size Size of the font
     *
     * @return int
     */
    protected function textWidth($text, $font, $size)
    {
        $p = imagettfbbox($size, 0, $font, $text);
        return $p[2] - $p[0];
    }

    /**
     * Returns the height a string would render to
     *
     * @param string $text Text to test
     * @param string $font Full font path
     * @param string $size Size of the font
     *
     * @return int
     */
    protected function textHeight($text, $font, $size)
    {
        $p = imagettfbbox($size, 0, $font, $text);
        return $p[1] - $p[5];
    }

    /**
     * Simulate outlined text
     *
     * @param resource $im       Active image resource
     * @param object   $settings Generator settings object
     * @param string   $text     Text to render
     * @param int      $y        Top position
     * @param string   $font     Full path to font
     * @param int      $fontSize Size of the font
     * @param int      $mcolor   Main text color
     * @param int      $scolor   Secondary border color
     * @param string   $align    'left','center','right'
     *
     * @return void
     */
    protected function drawText($im, $settings, $text, $y, $font, $fontSize, $mcolor,
        $scolor = false, $align = null
    ) {
        // In case the text contains non-normalized UTF-8, fix that for proper
        // display:
        $text = \Normalizer::normalize($text);

        // If the wrap width is smaller than the image width, we want to account
        // for this when right or left aligning to maintain padding on the image.
        $wrapGap = ($settings->width - $settings->wrapWidth) / 2;

        $textWidth = $this->textWidth($text, $font, $fontSize);
        if ($textWidth > $settings->width) {
            $align = 'left';
            $wrapGap = 0; // kill wrap gap to maximize text fit
        }
        if (null == $align) {
            $align = $settings->textAlign;
        }
        if ($align == 'left') {
            $x = $wrapGap;
        }
        if ($align == 'center') {
            $x = ($settings->width - $textWidth) / 2;
        }
        if ($align == 'right') {
            $x = $settings->width - ($textWidth + $wrapGap);
        }

        // Generate 5 lines of text, 4 offset in a border color
        if ($scolor) {
            imagettftext($im, $fontSize, 0, $x, $y + 1, $scolor, $font, $text);
            imagettftext($im, $fontSize, 0, $x, $y - 1, $scolor, $font, $text);
            imagettftext($im, $fontSize, 0, $x + 1, $y, $scolor, $font, $text);
            imagettftext($im, $fontSize, 0, $x - 1, $y, $scolor, $font, $text);
        }
        // 1 centered in main color
        imagettftext($im, $fontSize, 0, $x, $y, $mcolor, $font, $text);
    }
}
