<?php

/**
 * Base class for loading images (shared by Cover\Loader and QRCode\Loader)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */

namespace VuFind;

use function array_key_exists;

/**
 * Base class for loading images (shared by Cover\Loader and QRCode\Loader)
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
class ImageLoader implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Property for storing raw image data; may be null if image is unavailable
     *
     * @var string
     */
    protected $image = null;

    /**
     * Content type of data in $image property
     *
     * @var string
     */
    protected $contentType = null;

    /**
     * Theme tools
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeTools = null;

    /**
     * User-configured image to load from theme on error.
     *
     * @var string
     */
    protected $configuredFailImage = null;

    /**
     * Default image to load from theme if user-configured option fails.
     *
     * @var string
     */
    protected $defaultFailImage = 'images/noCover2.gif';

    /**
     * Array containing map of allowed file extensions to mimetypes
     * (to be extended)
     *
     * @var array
     */
    protected $allowedFileExtensions = [
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'tiff' => 'image/tiff', 'tif' => 'image/tiff',
    ];

    /**
     * Setter for dependency
     *
     * @param \VuFindTheme\ThemeInfo $theme VuFind theme tools
     *
     * @return void
     */
    public function setThemeInfo(\VuFindTheme\ThemeInfo $theme)
    {
        $this->themeTools = $theme;
    }

    /**
     * Get the image data (not meant to be called until after image is populated)
     *
     * @return string
     */
    public function getImage()
    {
        // No image loaded?  Use "unavailable" as default:
        if (null === $this->image) {
            $this->loadUnavailable();
        }
        return $this->image;
    }

    /**
     * Get the content type of the current image (not meant to be called until after
     * contentType is populated)
     *
     * @return string
     */
    public function getContentType()
    {
        // No content type loaded?  Use "unavailable" as default:
        if (null === $this->contentType) {
            $this->loadUnavailable();
        }
        return $this->contentType;
    }

    /**
     * Find a file in the themes (return false if no file exists).
     *
     * @param string $path    Relative path of file to find.
     * @param array  $formats Optional array of suffixes to add to $path while
     * searching theme (used to check multiple extensions in each theme).
     *
     * @return string|bool
     */
    protected function searchTheme($path, $formats = [''])
    {
        // Check all supported image formats:
        $filenames = [];
        foreach ($formats as $format) {
            $filenames[] = $path . $format;
        }
        if (null === $this->themeTools) {
            throw new \Exception('\VuFindTheme\ThemeInfo object missing');
        }
        $fileMatch = $this->themeTools->findContainingTheme($filenames, true);
        return empty($fileMatch) ? false : $fileMatch;
    }

    /**
     * Load the user-specified "cover unavailable" graphic (or default if none
     * specified).
     *
     * @return void
     * @author Thomas Schwaerzler <vufind-tech@lists.sourceforge.net>
     */
    public function loadUnavailable()
    {
        // No setting -- use default, and don't log anything:
        if (empty($this->configuredFailImage)) {
            $this->loadDefaultFailImage();
            return;
        }

        // Setting found -- get "no cover" image from config.ini:
        $noCoverImage = $this->searchTheme($this->configuredFailImage);

        // If file is blank/inaccessible, log error and display default:
        if (
            empty($noCoverImage) || !file_exists($noCoverImage)
            || !is_readable($noCoverImage)
        ) {
            $this->debug("Cannot access '{$this->configuredFailImage}'");
            $this->loadDefaultFailImage();
            return;
        }

        try {
            // Get mime type from file extension:
            $this->contentType = $this->getContentTypeFromExtension($noCoverImage);
        } catch (\Exception $e) {
            // Log error and bail out if file lacks a known image extension:
            $this->debug($e->getMessage());
            $this->loadDefaultFailImage();
            return;
        }

        // Load the image data:
        $this->image = file_get_contents($noCoverImage);
    }

    /**
     * Display the default "cover unavailable" graphic.
     *
     * @return void
     */
    protected function loadDefaultFailImage()
    {
        $file = $this->searchTheme($this->defaultFailImage);
        if (!file_exists($file)) {
            throw new \Exception('Could not load default fail image.');
        }
        $this->contentType = $this->getContentTypeFromExtension($file);
        $this->image = file_get_contents($file);
    }

    /**
     * Get the content-type for a file based on extension. Throw an exception if
     * an illegal extension is provided.
     *
     * @param string $filename Filename to analyze.
     *
     * @return string
     * @throws \Exception
     */
    protected function getContentTypeFromExtension($filename)
    {
        $parts = explode('.', $filename);
        $fileExtension = strtolower(end($parts));
        if (!array_key_exists($fileExtension, $this->allowedFileExtensions)) {
            throw new \Exception(
                "Illegal file-extension '$fileExtension' for image '$filename'"
            );
        }

        // Get mime type from file extension:
        return $this->allowedFileExtensions[$fileExtension];
    }
}
