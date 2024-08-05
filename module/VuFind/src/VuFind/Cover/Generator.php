<?php

/**
 * Dynamic Book Cover Generator
 *
 * PHP version 8
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

use function count;

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
    public function __construct(
        ThemeInfo $themeTools,
        LayerManager $lm,
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
            [$settings['width'], $settings['height']] = $parts;
        }

        // Store the results as an object:
        $this->settings = (object)$settings;

        // Reinitialize everything based on settings:
        $this->initImage();
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
        $this->getTextLayer()->render($this->im, $details, $this->settings);

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
     * Get the layer plugin for the text
     *
     * @return LayerInterface
     */
    protected function getTextLayer()
    {
        $service = strtolower($this->settings->textMode) . 'text';
        return $this->layerManager->get(
            $this->layerManager->has($service) ? $service : 'defaulttext'
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
}
