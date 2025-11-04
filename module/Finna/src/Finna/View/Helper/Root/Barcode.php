<?php

/**
 * Barcode view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

/**
 * Barcode view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Barcode extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Create a barcode PNG
     *
     * @param string $code   String to use as the barcode
     * @param int    $width  Barcode narrow bar width
     * @param int    $height Barcode height
     * @param string $type   Barcode type
     *
     * @return string Base 64 encoded image data
     */
    public function createPng($code, $width = 2, $height = 30, $type = null)
    {
        try {
            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
            $type = null !== $type ? $type : $generator::TYPE_CODE_39;
            return base64_encode(
                $generator->getBarcode($code, $type, $width, $height)
            );
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Create a CODE 39 as SVG from a barcode string
     *
     * @param string $barcode         Barcode
     * @param float  $widthFactor     Minimum width of a single bar in user units.
     * @param int    $height          Height of barcode in user units.
     * @param string $foregroundColor Foreground color (in SVG format) for bar elements (background is transparent).
     *
     * @return string
     */
    public function createCode39SVG(
        string $barcode,
        float $widthFactor = 2,
        int $height = 30,
        string $foregroundColor = 'black'
    ): string {
        // Strip any non-printable characters from the barcode string:
        $barcode = preg_replace('/[\pC]/u', '', $barcode);

        $code39 = new \Picqer\Barcode\Types\TypeCode39();
        try {
            $barcodeData = $code39->getBarcodeData($barcode);
        } catch (\Picqer\Barcode\Exceptions\InvalidCharacterException $e) {
            return '';
        }

        // replace table for special characters
        $repstr = ["\0" => '', '&' => '&amp;', '<' => '&lt;', '>' => '&gt;'];

        $width = round(($barcodeData->getWidth() * $widthFactor), 3);

        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' '
        . $height . '" version="1.1" xmlns="http://www.w3.org/2000/svg">' . PHP_EOL;
        $svg .= "\t" . '<desc>' . strtr($barcodeData->getBarcode(), $repstr) . '</desc>' . PHP_EOL;
        $svg .= "\t" . '<g id="bars" fill="' . $foregroundColor . '" stroke="none">' . PHP_EOL;

        // print bars
        $positionHorizontal = 0;
        foreach ($barcodeData->getBars() as $bar) {
            $barWidth = round(($bar->getWidth() * $widthFactor), 3);
            $barHeight = round(($bar->getHeight() * $height / $barcodeData->getHeight()), 3);

            if ($bar->isBar() && $barWidth > 0) {
                $positionVertical = round(($bar->getPositionVertical() * $height / $barcodeData->getHeight()), 3);
                // draw a vertical bar
                $svg .= "\t\t" . '<rect x="' . $positionHorizontal . '" y="' . $positionVertical . '" '
                . 'width="' . $barWidth . '" height="' . $barHeight . '" />' . PHP_EOL;
            }

            $positionHorizontal += $barWidth;
        }
        $svg .= "\t</g>" . PHP_EOL;
        $svg .= '</svg>' . PHP_EOL;

        return $svg;
    }
}
