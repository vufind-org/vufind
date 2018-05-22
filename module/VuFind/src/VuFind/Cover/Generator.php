<?php
/**
 * Dynamic Book Cover Generator
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
namespace VuFind\Cover;

use VuFind\Cover\Layer\LayerInterface;
use VuFind\Cover\Layer\PluginManager as LayerManager;
use VuFindTheme\ThemeInfo;

/**
 * Dynamic Book Cover Generator
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
class Generator
{
    /**
     * Default settings (values used by setOptions() if not overridden).
     *
     * @var array
     */
    protected $defaultSettings = [
        'backgroundMode' => 'grid',
        'textMode' => 'default',
        'authorFont' => 'DroidSerif-Bold.ttf',
        'titleFontSize' => 7,
        'authorFontSize' => 6,
        'lightness' => 220,
        'maxTitleLines' => 5,
        'minAuthorFontSize' => 5,
        'saturation' => 80,
        'size' => 84,
        'textAlign' => 'center',
        'titleFont' => 'DroidSerif-Bold.ttf',
        'topPadding' => 19,
        'bottomPadding' => 3,
        'wrapWidth' => 80,
        'titleFillColor' => 'black',
        'titleBorderColor' => 'none',
        'authorFillColor' => 'white',
        'authorBorderColor' => 'black',
        'baseColor' => 'white',
        'accentColor' => 'random',
    ];

    /**
     * Active style configuration
     *
     * @var object
     */
    protected $settings;

    /**
     * Mapping of color names to RGB values.
     *
     * @var array
     */
    protected $colorMap = [
        'black' => [0, 0, 0],
        'silver' => [192, 192, 192],
        'gray' => [128, 128, 128],
        'white' => [255, 255, 255],
        'maroon' => [128, 0, 0],
        'red' => [255, 0, 0],
        'purple' => [128, 0, 128],
        'fuchsia' => [255, 0, 255],
        'green' => [0, 128, 0],
        'lime' => [0, 255, 0],
        'olive' => [128, 128, 0],
        'yellow' => [255, 255, 0],
        'navy' => [0, 0, 128],
        'blue' => [0, 0, 255],
        'teal' => [0, 128, 128],
        'aqua' => [0, 255, 255],
    ];

    /**
     * Title's fill color
     *
     * @var int
     */
    protected $titleFillColor;

    /**
     * Title's border color
     *
     * @var int
     */
    protected $titleBorderColor;

    /**
     * Author's fill color
     *
     * @var int
     */
    protected $authorFillColor;

    /**
     * Author's border color
     *
     * @var int
     */
    protected $authorBorderColor;

    /**
     * Base for image
     *
     * @var resource
     */
    protected $im;

    /**
     * ThemeInfo object
     *
     * @var ThemeInfo
     */
    protected $themeTools;

    /**
     * Layer manager
     *
     * @var LayerManager
     */
    protected $layerManager;

    /**
     * Constructor
     *
     * @param ThemeInfo    $themeTools For font loading
     * @param LayerManager $lm         Layer manager
     * @param array        $settings   Overwrite styles
     */
    public function __construct(ThemeInfo $themeTools, LayerManager $lm,
        array $settings = []
    ) {
        $this->themeTools = $themeTools;
        $this->layerManager = $lm;
        $this->setOptions($settings);
    }

    /**
     * Set the generator options.
     *
     * @param array $rawSettings Overwrite styles
     *
     * @return void
     */
    public function setOptions($rawSettings)
    {
        // Merge incoming settings with defaults:
        $settings = $rawSettings + $this->defaultSettings;

        // Adjust font paths:
        $settings['authorFont'] = $this->fontPath($settings['authorFont']);
        $settings['titleFont']  = $this->fontPath($settings['titleFont']);

        // Determine final dimensions:
        $parts = explode('x', strtolower($settings['size']));
        if (count($parts) < 2) {
            $settings['width'] = $settings['height'] = $parts[0];
        } else {
            list($settings['width'], $settings['height']) = $parts;
        }

        // Store the results as an object:
        $this->settings = (object)$settings;

        // Reinitialize everything based on settings:
        $this->initImage();
        $this->initColors();
    }

    /**
     * Initialize colors to be used in the image.
     *
     * @return void
     */
    protected function initColors()
    {
        $this->titleFillColor = $this->getColor($this->settings->titleFillColor);
        $this->titleBorderColor = $this->getColor($this->settings->titleBorderColor);
        $this->authorFillColor = $this->getColor($this->settings->authorFillColor);
        $this->authorBorderColor = $this->getColor(
            $this->settings->authorBorderColor
        );
    }

    /**
     * Initialize the image in the object.
     *
     * @return void
     */
    protected function initImage()
    {
        // Create image
        $this->im = imagecreate($this->settings->width, $this->settings->height);
        if (!$this->im) {
            throw new \Exception('Cannot Initialize new GD image stream');
        }
    }

    /**
     * Clear the resources associated with the image in the object.
     *
     * @return void
     */
    protected function destroyImage()
    {
        imagedestroy($this->im);
    }

    /**
     * Render the contents of the image in the object to a PNG; return as string.
     *
     * @return string
     */
    protected function renderPng()
    {
        ob_start();
        imagepng($this->im);
        $img = ob_get_contents();
        ob_end_clean();
        return $img;
    }

    /**
     * Check and allocates color
     *
     * @param string $color Legal color name from HTML4
     *
     * @return allocated color
     */
    protected function getColor($color)
    {
        // Case one: named color found in map
        $key = strtolower($color);
        if (isset($this->colorMap[$key])) {
            return imagecolorallocate($this->im, ...$this->colorMap[$key]);
        }
        // Case two: hex color
        if (substr($color, 0, 1) == '#' && strlen($color) == 7) {
            $r = hexdec(substr($color, 1, 2));
            $g = hexdec(substr($color, 3, 2));
            $b = hexdec(substr($color, 5, 2));
            return imagecolorallocate($this->im, $r, $g, $b);
        }
        // Default case: unsupported color
        return false;
    }

    /**
     * Generates a dynamic cover image from elements of the item
     *
     * @param string $title      Title of the book
     * @param string $author     Author of the book
     * @param string $callnumber Callnumber of the book
     *
     * @return string contents of image file
     */
    public function generate($title, $author, $callnumber = null)
    {
        $details = compact('title', 'author', 'callnumber');

        // Build the image
        $this->getBackgroundLayer()->render($this->im, $details, $this->settings);
        $this->drawTextLayer($title, $author);

        // Render the image
        $png = $this->renderPng();
        $this->destroyImage();
        return $png;
    }

    /**
     * Get the layer plugin for the background
     *
     * @return LayerInterface
     */
    protected function getBackgroundLayer()
    {
        $service = strtolower($this->settings->backgroundMode) . 'background';
        return $this->layerManager->get(
            $this->layerManager->has($service) ? $service : 'gridbackground'
        );
    }

    /**
     * Position the text on the image.
     *
     * @param string $title  Title of the book
     * @param string $author Author of the book
     *
     * @return void
     */
    protected function drawTextLayer($title, $author)
    {
        // Construct a method name using the mode setting; if the method is not
        // defined, use the default drawGridBackground().
        $mode = ucwords(strtolower($this->settings->textMode));
        $method = "draw{$mode}Text";
        return method_exists($this, $method)
            ? $this->$method($title, $author)
            : $this->drawDefaultText($title, $author);
    }

    /**
     * Position the text on the image using default rules.
     *
     * @param string $title  Title of the book
     * @param string $author Author of the book
     *
     * @return void
     */
    protected function drawDefaultText($title, $author)
    {
        if (null !== $title) {
            $this->drawTitle($title, $this->settings->height / 8);
        }
        if (null !== $author) {
            $this->drawAuthor($author);
        }
    }

    /**
     * Position the text on the image using "initials" rules.
     *
     * @param string $title  Title of the book
     * @param string $author Author of the book
     *
     * @return void
     */
    protected function drawInitialText($title, $author)
    {
        // Get the first letter of title or author...
        $initial = mb_substr($title . $author, 0, 1, 'UTF-8');

        // Get the height of a character with no descenders:
        $heightWithoutDescenders = $this->textHeight(
            'O', $this->settings->titleFont, $this->settings->titleFontSize
        );

        // Get the height of the currently selected character:
        $textHeight = $this->textHeight(
            $initial, $this->settings->titleFont, $this->settings->titleFontSize
        );

        // Draw the letter... Note that the way we are using $textHeight and
        // $heightWithoutDescenders is something of a fudge driven by the fact
        // that PHP measures text in total pixels, but positions text using the
        // baseline (thus not accounting for descenders). To truly vertically
        // center something, we need more information than we can get without
        // using an extension or library to read more information from the font
        // file. The formula here is not particularly well-informed but seems
        // to produce acceptable results for many scenarios.
        $this->drawText(
            $initial,
            $heightWithoutDescenders + ($this->settings->height - $textHeight) / 2,
            $this->settings->titleFont,
            $this->settings->titleFontSize,
            $this->titleFillColor,
            $this->titleBorderColor,
            $this->settings->textAlign
        );
    }

    /**
     * Render title in wrapped, black text with white border
     *
     * @param string $title      Title to write
     * @param int    $lineHeight Pixels we move down each line
     *
     * @return void
     */
    protected function drawTitle($title, $lineHeight)
    {
        $words = explode(' ', $title);
        // Wrap words into image
        // Add words until off image, go back and print
        $line = '';
        $lineCount = 0;
        $i = 0;
        while ($i < count($words)
            && $lineCount < $this->settings->maxTitleLines - 1
        ) {
            $pline = $line;
            // Format
            $text = $words[$i];
            $line .= $text . ' ';
            $textWidth = $this->textWidth(
                rtrim($line, ' '),
                $this->settings->titleFont,
                $this->settings->titleFontSize
            );
            if ($textWidth > $this->settings->wrapWidth) {
                // Print black with white border
                $this->drawText(
                    rtrim($pline, ' '),
                    $this->settings->topPadding + $lineHeight * $lineCount,
                    $this->settings->titleFont,
                    $this->settings->titleFontSize,
                    $this->titleFillColor,
                    $this->titleBorderColor
                );
                $line = $text . ' ';
                $lineCount++;
            }
            $i++;
        }
        // Print the last words
        $this->drawText(
            rtrim($line, ' '),
            $this->settings->topPadding + $lineHeight * $lineCount,
            $this->settings->titleFont,
            $this->settings->titleFontSize,
            $this->titleFillColor,
            $this->titleBorderColor
        );
        // Add ellipses if we've truncated
        if ($i < count($words) - 1) {
            $this->drawText(
                '...',
                $this->settings->topPadding
                + $this->settings->maxTitleLines * $lineHeight,
                $this->settings->titleFont,
                $this->settings->titleFontSize + 1,
                $this->titleFillColor,
                $this->titleBorderColor
            );
        }
    }

    /**
     * Render author at bottom in wrapped, white text with black border
     *
     * @param string $author Author to write
     *
     * @return void
     */
    protected function drawAuthor($author)
    {
        // Scale author to fit by incrementing fontsizes down
        $fontSize = $this->settings->authorFontSize + 1;
        do {
            $fontSize--;
            $textWidth = $this->textWidth(
                $author,
                $this->settings->authorFont,
                $fontSize
            );
        } while ($textWidth > $this->settings->wrapWidth &&
              $fontSize > $this->settings->minAuthorFontSize
          );
        // Too small to read? Align left
        $textWidth = $this->textWidth(
            $author,
            $this->settings->authorFont,
            $fontSize
        );
        $align = $textWidth > $this->settings->width ? 'left' : null;
        $this->drawText(
            $author,
            $this->settings->height - $this->settings->bottomPadding,
            $this->settings->authorFont,
            $fontSize,
            $this->authorFillColor,
            $this->authorBorderColor,
            $align
        );
    }

    /**
     * Find font in the theme folder
     *
     * @param string $font Font_name.ttf
     *
     * @return string file path
     */
    protected function fontPath($font)
    {
        // Check all supported image formats:
        $filenames = ['css/font/' . $font];
        $fileMatch = $this->themeTools->findContainingTheme($filenames, true);
        return empty($fileMatch) ? false : $fileMatch;
    }

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
     * @param string $text     Text to render
     * @param int    $y        Top position
     * @param string $font     Full path to font
     * @param int    $fontSize Size of the font
     * @param int    $mcolor   Main text color
     * @param int    $scolor   Secondary border color
     * @param string $align    'left','center','right'
     *
     * @return void
     */
    protected function drawText($text, $y, $font, $fontSize, $mcolor,
        $scolor = false, $align = null
    ) {
        // If the wrap width is smaller than the image width, we want to account
        // for this when right or left aligning to maintain padding on the image.
        $wrapGap = ($this->settings->width - $this->settings->wrapWidth) / 2;

        $textWidth = $this->textWidth(
            $text,
            $font,
            $fontSize
        );
        if ($textWidth > $this->settings->width) {
            $align = 'left';
            $wrapGap = 0; // kill wrap gap to maximize text fit
        }
        if (null == $align) {
            $align = $this->settings->textAlign;
        }
        if ($align == 'left') {
            $x = $wrapGap;
        }
        if ($align == 'center') {
            $x = ($this->settings->width - $textWidth) / 2;
        }
        if ($align == 'right') {
            $x = $this->settings->width - ($textWidth + $wrapGap);
        }

        // Generate 5 lines of text, 4 offset in a border color
        if ($scolor) {
            imagettftext(
                $this->im, $fontSize, 0, $x, $y + 1, $scolor, $font, $text
            );
            imagettftext(
                $this->im, $fontSize, 0, $x, $y - 1, $scolor, $font, $text
            );
            imagettftext(
                $this->im, $fontSize, 0, $x + 1, $y, $scolor, $font, $text
            );
            imagettftext(
                $this->im, $fontSize, 0, $x - 1, $y, $scolor, $font, $text
            );
        }
        // 1 centered in main color
        imagettftext($this->im, $fontSize, 0, $x, $y, $mcolor, $font, $text);
    }
}
