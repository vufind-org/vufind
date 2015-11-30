<?php
/**
 * Record image loader
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace Finna\Cover;
use VuFindCode\ISBN;

/**
 * Record image loader
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
class Loader extends \VuFind\Cover\Loader
{
    /**
     * Image URL
     *
     * @var string
     */
    protected $url;

    /**
     * Record id
     *
     * @var string
     */
    protected $id;

    /**
     * Image index
     *
     * @var int
     */
    protected $index;

    /**
     * Image width
     *
     * @var int
     */
    protected $width;

    /**
     * Image height
     *
     * @var int
     */
    protected $height;

    /**
     * Use full-resolution image?
     *
     * @var boolean
     */
    protected $fullRes;

    /**
     * Set image parameters.
     *
     * @param int     $width   Image width
     * @param int     $height  Image height
     * @param boolean $fullRes Use full-resolution image?
     *
     * @return void
     */
    public function setParams($width, $height, $fullRes = false)
    {
        $this->width = $width;
        $this->height = $height;
        $this->fullRes = $fullRes;
    }

    /**
     * Load an image given an ISBN and/or content type.
     *
     * @param array $settings Array of settings used to calculate a cover; may
     * contain any or all of these keys: 'isbn' (ISBN), 'size' (requested size),
     * 'type' (content type), 'title' (title of book, for dynamic covers), 'author'
     * (author of book, for dynamic covers), 'callnumber' (unique ID, for dynamic
     * covers), 'issn' (ISSN), 'oclc' (OCLC number), 'upc' (UPC number).
     *
     * @return void
     */
    public function loadImage($settings = [])
    {
        // Load settings from legacy function parameters if they are not passed
        // in as an array:
        $settings = is_array($settings)
            ? array_merge($this->getDefaultSettings(), $settings)
            : $this->getImageSettingsFromLegacyArgs(func_get_args());

        // Store sanitized versions of some parameters for future reference:
        $this->storeSanitizedSettings($settings);

        // Display a fail image unless our parameters pass inspection and we
        // are able to display an ISBN or content-type-based image.
        if (!$this->fetchFromAPI()
            && !$this->fetchFromContentType()
        ) {
            if (isset($this->config->Content->makeDynamicCovers)
                && false !== $this->config->Content->makeDynamicCovers
            ) {
                $this->image = $this->getCoverGenerator()->generate(
                    $settings['title'], $settings['author'], $settings['callnumber']
                );
                $this->contentType = 'image/png';
            } else {
                $this->loadUnavailable();
            }
        }
    }

    /**
     * Load a record image.
     *
     * @param \Vufind\RecordDriver\SolrDefault $driver Record
     * @param int                              $index  Image index
     *
     * @return void
     */
    public function loadRecordImage(
        \VuFind\RecordDriver\SolrDefault $driver, $index = 0
    ) {
        $this->index = $index;

        $params = $driver->getRecordImage(
            $this->fullRes ? 'large' : 'medium', $index
        );

        if (isset($params['url'])) {
            $this->id = $params['id'];
            $this->url = $params['url'];
            return parent::fetchFromAPI();
        }
    }

    /**
     * Get all valid identifiers as an associative array.
     *
     * @return array
     */
    protected function getIdentifiers()
    {
        if ($this->url) {
            return ['url' => $this->url];
        } else {
            return parent::getIdentifiers();
        }
    }

    /**
     * Support method for fetchFromAPI() -- set the localFile property.
     *
     * @param array $ids IDs returned by getIdentifiers() method
     *
     * @return void
     */
    protected function determineLocalFile($ids)
    {
        $keys = [];

        if (isset($this->url)) {
            $keys['id'] = $this->id;
        } else {
            if (isset($ids['isbn'])) {
                $keys['isbn'] = $ids['isbn']->get13();
            } else if (isset($ids['issn'])) {
                $keys['issn'] = $ids['issn'];
            } else if (isset($ids['oclc'])) {
                $keys['oclc'] = $ids['oclc'];
            } else if (isset($ids['upc'])) {
                $keys['upc'] = $ids['upc'];
            }
        }

        $keys = array_merge(
            $keys,
            [$this->index, $this->width, $this->height, $this->fullRes ? '1' : '0']
        );

        $file = implode('-', $keys);
        return $this->getCachePath('finna', $file);
    }

    /**
     * Return a path to the image cache for the given size and ID; ensure that
     * directories are created as needed.
     *
     * @param string $size      Size category
     * @param string $id        Unique identifier (ISBN / ISSN)
     * @param string $extension File extension to use (default = jpg)
     *
     * @return string      Cache path
     */
    protected function getCachePath($size, $id, $extension = 'jpg')
    {
        $base = $this->baseDir;
        if (!is_dir($base)) {
            mkdir($base);
        }
        $base .= '/finna';
        if (!is_dir($base)) {
            mkdir($base);
        }
        return $base . '/' . $id . '.' . $extension;
    }

    /**
     * Load image from URL, store in cache if requested, display if possible.
     *
     * @param string $url   URL to load image from
     * @param string $cache Boolean -- should we store in local cache?
     *
     * @return bool         True if image loaded, false on failure.
     */
    protected function processImageURL($url, $cache = true)
    {
        // Attempt to pull down the image:
        $result = $this->client->setUri($url)->send();
        if (!$result->isSuccess()) {
            $this->debug("Failed to retrieve image from " + $url);
            return false;
        }

        $image = $result->getBody();

        if ('' == $image) {
            return false;
        }

        // Figure out file paths -- $tempFile will be used to store the
        // image for analysis.  $finalFile will be used for long-term storage if
        // $cache is true or for temporary display purposes if $cache is false.
        $tempFile = str_replace('.jpg', uniqid(), $this->localFile);
        $finalFile = $cache ? $this->localFile : $tempFile . '.jpg';

        // Write image data to disk:
        if (!@file_put_contents($tempFile, $image)) {
            throw new \Exception("Unable to write to image directory.");
        }

        // We can't proceed if we don't have image conversion functions:
        if (!is_callable('imagecreatefromstring')) {
            return false;
        }

        // Try to create a GD image and rewrite as JPEG, fail if we can't:
        if (!($imageGD = @imagecreatefromstring($image))) {
            return false;
        }

        list($width, $height, $type) = @getimagesize($tempFile);

        $reqWidth = $this->width;
        $reqHeight = $this->height;

        if ($reqWidth && $reqHeight) {
            $quality = 90;

            if ($width > $reqWidth || $height > $reqHeight) {
                $newHeight = min($height, $reqHeight);
                $newWidth = round($newHeight * ($width / $height));
                if ($newWidth > $reqWidth) {
                    $newWidth = $reqWidth;
                    $newHeight = round($newWidth * ($height / $width));
                }

                $imageGDResized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled(
                    $imageGDResized, $imageGD, 0, 0, 0, 0,
                    $newWidth, $newHeight, $width, $height
                );
                if (!@imagejpeg($imageGDResized, $finalFile, $quality)) {
                    return false;
                }
            } else {
                if (!@imagejpeg($imageGD, $finalFile, $quality)) {
                    return false;
                }
            }

            // We no longer need the temp file:
            @unlink($tempFile);
        } else {
            // Move temporary file to final location:
            if (!$this->validateAndMoveTempFile($image, $tempFile, $finalFile)) {
                return false;
            }
        }

        // Display the image:
        $this->contentType = 'image/jpeg';
        $this->image = file_get_contents($finalFile);

        // If we don't want to cache the image, delete it now that we're done.
        if (!$cache) {
            @unlink($finalFile);
        }

        return true;
    }
}
