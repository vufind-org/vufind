<?php

/**
 * Initial cover text layer
 *
 * PHP version 8
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
 * Initial cover text layer
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class InitialText extends AbstractTextLayer
{
    /**
     * Render the layer
     *
     * @param resource $im       Image resource to draw on
     * @param array    $details  Cover details array (with title/author/call_number)
     * @param object   $settings Settings object
     *
     * @return void
     */
    public function render($im, $details, $settings)
    {
        // Get the first letter of title or author...
        $initial = mb_substr($details['title'] . $details['author'], 0, 1, 'UTF-8');

        // Get the height of a character with no descenders:
        $heightWithoutDescenders
            = $this->textHeight('O', $settings->titleFont, $settings->titleFontSize);

        // Get the height of the currently selected character:
        $textHeight = $this
            ->textHeight($initial, $settings->titleFont, $settings->titleFontSize);

        // Draw the letter... Note that the way we are using $textHeight and
        // $heightWithoutDescenders is something of a fudge driven by the fact
        // that PHP measures text in total pixels, but positions text using the
        // baseline (thus not accounting for descenders). To truly vertically
        // center something, we need more information than we can get without
        // using an extension or library to read more information from the font
        // file. The formula here is not particularly well-informed but seems
        // to produce acceptable results for many scenarios.
        $this->drawText(
            $im,
            $settings,
            $initial,
            $heightWithoutDescenders + ($settings->height - $textHeight) / 2,
            $settings->titleFont,
            $settings->titleFontSize,
            $this->getColor($im, $settings->titleFillColor),
            $this->getColor($im, $settings->titleBorderColor),
            $settings->textAlign
        );
    }
}
