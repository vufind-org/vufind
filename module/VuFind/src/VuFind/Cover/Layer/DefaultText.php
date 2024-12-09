<?php

/**
 * Default cover text layer
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

use function count;

/**
 * Default cover text layer
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class DefaultText extends AbstractTextLayer
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
        if (null !== $details['title']) {
            $lineHeight = $settings->height / 8;
            $this->drawTitle($im, $settings, $details['title'], $lineHeight);
        }
        if (null !== $details['author']) {
            $this->drawAuthor($im, $settings, $details['author']);
        }
    }

    /**
     * Render title in wrapped, black text with white border
     *
     * @param resource $im         Image resource to draw on
     * @param object   $settings   Settings object
     * @param string   $title      Title to write
     * @param int      $lineHeight Pixels we move down each line
     *
     * @return void
     */
    protected function drawTitle($im, $settings, $title, $lineHeight)
    {
        $titleFillColor = $this->getColor($im, $settings->titleFillColor);
        $titleBorderColor = $this->getColor($im, $settings->titleBorderColor);
        $words = explode(' ', $title);
        // Wrap words into image
        // Add words until off image, go back and print
        $line = '';
        $lineCount = 0;
        $i = 0;
        while (
            $i < count($words)
            && $lineCount < $settings->maxTitleLines - 1
        ) {
            $pline = $line;
            // Format
            $text = $words[$i];
            $line .= $text . ' ';
            $textWidth = $this->textWidth(
                rtrim($line, ' '),
                $settings->titleFont,
                $settings->titleFontSize
            );
            if ($textWidth > $settings->wrapWidth) {
                // Print black with white border
                $this->drawText(
                    $im,
                    $settings,
                    rtrim($pline, ' '),
                    $settings->topPadding + $lineHeight * $lineCount,
                    $settings->titleFont,
                    $settings->titleFontSize,
                    $titleFillColor,
                    $titleBorderColor
                );
                $line = $text . ' ';
                $lineCount++;
            }
            $i++;
        }
        // Print the last words
        $this->drawText(
            $im,
            $settings,
            rtrim($line, ' '),
            $settings->topPadding + $lineHeight * $lineCount,
            $settings->titleFont,
            $settings->titleFontSize,
            $titleFillColor,
            $titleBorderColor
        );
        // Add ellipses if we've truncated
        if ($i < count($words) - 1) {
            $this->drawText(
                $im,
                $settings,
                '...',
                $settings->topPadding + $settings->maxTitleLines * $lineHeight,
                $settings->titleFont,
                $settings->titleFontSize + 1,
                $titleFillColor,
                $titleBorderColor
            );
        }
    }

    /**
     * Render author at bottom in wrapped, white text with black border
     *
     * @param resource $im       Image resource to draw on
     * @param object   $settings Settings object
     * @param string   $author   Author to write
     *
     * @return void
     */
    protected function drawAuthor($im, $settings, $author)
    {
        $authorFillColor = $this->getColor($im, $settings->authorFillColor);
        $authorBorderColor = $this->getColor($im, $settings->authorBorderColor);
        // Scale author to fit by incrementing fontsizes down
        $fontSize = $settings->authorFontSize + 1;
        do {
            $fontSize--;
            $textWidth = $this->textWidth(
                $author,
                $settings->authorFont,
                $fontSize
            );
        } while (
            $textWidth > $settings->wrapWidth &&
              $fontSize > $settings->minAuthorFontSize
        );
        // Too small to read? Align left
        $textWidth = $this->textWidth(
            $author,
            $settings->authorFont,
            $fontSize
        );
        $align = $textWidth > $settings->width ? 'left' : null;
        $this->drawText(
            $im,
            $settings,
            $author,
            $settings->height - $settings->bottomPadding,
            $settings->authorFont,
            $fontSize,
            $authorFillColor,
            $authorBorderColor,
            $align
        );
    }
}
