<?php

/**
 * FormatBased cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @package  Content
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use VuFind\Record\Loader as RecordLoader;

use function in_array;

/**
 * FormatBased cover content loader.
 *
 * Returns a cover image depending on the format of the record. This is meant
 * to be used as the last entry of the coverimages list in the [Content]
 * section of config.ini: it only produces a result if none of the other
 * providers did, and maps the record format (Solr field "format") to a
 * locally stored image file.
 *
 * Configuration is read from the [FormatBasedCovers] section of config.ini:
 * - image_dir: directory containing one image file per format, named after
 *   the format value (e.g. "book.png" for format "book"); if not set, the
 *   default directory <VUFIND_HOME>/themes/bootstrap5/images/format-covers
 *   (which contains a set of simple default images) is used;
 * - default: image file used for all formats without a dedicated image;
 * - any other key is treated as an explicit mapping of a format value to an
 *   image file path (takes precedence over image_dir).
 *
 * Image paths may be absolute filesystem paths or file:// or http(s):// URLs.
 *
 * @category VuFind
 * @package  Content
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FormatBased extends \VuFind\Content\AbstractCover
{
    /**
     * Image file extensions to look for in image_dir, in order of preference.
     *
     * @var array
     */
    protected $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    /**
     * Constructor.
     *
     * @param RecordLoader $recordLoader Record loader
     * @param string       $imageDir     Directory containing per-format images
     * @param array        $mapping      Explicit format => image path mapping
     * @param string       $default      Image path used for unknown formats
     */
    public function __construct(
        protected RecordLoader $recordLoader,
        protected string $imageDir = '',
        protected array $mapping = [],
        protected string $default = ''
    ) {
        $this->cacheAllowed = true;
        $this->supportsRecordid = true;
    }

    /**
     * Get image URL for a particular recordId (or false if not found).
     *
     * @param string $key  API key, unused
     * @param string $size Size of image to load (small/medium/large), unused
     * @param array  $ids  Associative array of identifiers
     *
     * @return string|bool URL of the image, or false if no valid image is found
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        $recordId = $ids['recordid'];
        $source = $ids['source'] ?? DEFAULT_SEARCH_BACKEND;
        $driver = $this->recordLoader->load($recordId, $source);
        $formats = method_exists($driver, 'getFormats') ? $driver->getFormats() : [];
        $format = $formats[0] ?? '';
        $file = $this->findFile($format);
        return $file ? $this->toUrl($file) : false;
    }

    /**
     * Find the image file for the given format.
     *
     * @param string $format Record format value
     *
     * @return string|bool Path or URL of the image, or false if none found
     */
    protected function findFile(string $format)
    {
        if ($format !== '') {
            // Explicit mapping takes precedence:
            if (isset($this->mapping[$format])) {
                return $this->mapping[$format];
            }
            if ($this->imageDir !== '') {
                $file = $this->findImageInDir($this->imageDir, $format);
                if ($file !== false) {
                    return $file;
                }
            }
        }
        // Fall back to the default image:
        if ($this->default !== '') {
            return $this->default;
        }
        if ($this->imageDir !== '') {
            return $this->findImageInDir($this->imageDir, 'default');
        }
        return false;
    }

    /**
     * Look for an image file with the given name in a directory.
     *
     * @param string $dir  Directory to search
     * @param string $name Base file name (sanitized here, as it may come
     *                     from the index)
     *
     * @return string|bool Path of the image, or false if not found
     */
    protected function findImageInDir(string $dir, string $name)
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '', $name);
        if (in_array($safe, ['', '.', '..'], true)) {
            return false;
        }
        foreach ($this->extensions as $ext) {
            $file = rtrim($dir, '/') . '/' . $safe . '.' . $ext;
            if (is_readable($file)) {
                return $file;
            }
        }
        return false;
    }

    /**
     * Convert a filesystem path to a URL understood by the image loader.
     *
     * @param string $file File path or URL
     *
     * @return string URL
     */
    protected function toUrl(string $file)
    {
        if (preg_match('#^(file|https?)://#', $file)) {
            return $file;
        }
        return 'file://' . $file;
    }
}
