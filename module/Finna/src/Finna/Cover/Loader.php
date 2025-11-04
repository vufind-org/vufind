<?php

/**
 * Record image loader
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @package  Cover_Generator
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */

namespace Finna\Cover;

use function func_get_args;
use function is_array;
use function is_callable;
use function strlen;

/**
 * Record image loader
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
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
     * Image parameters
     *
     * @var array
     */
    protected $imageParams = [];

    /**
     * Record id
     *
     * @var string
     */
    protected $id;

    /**
     * Invalid ISBN
     *
     * @var string
     */
    protected $invalidIsbn = '';

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
    protected $width = 100;

    /**
     * Image height
     *
     * @var int
     */
    protected $height = 100;

    /**
     * Image size to use
     *
     * @var boolean
     */
    protected $size = 'medium';

    /**
     * Datasource spesific cover image configuration
     *
     * @var string
     */
    protected $datasourceCoverConfig = null;

    /**
     * Name for the unsized image used as a base for resizing new images
     *
     * @var string
     */
    protected $unsizedImageFile = '';

    /**
     * Set datasource spesific cover image configuration.
     *
     * @param string $providers Comma separated list of cover image providers
     *
     * @return void
     */
    public function setDatasourceConfig($providers)
    {
        $this->datasourceCoverConfig = $providers;
    }

    /**
     * Set image parameters.
     *
     * @param int    $width  Image width
     * @param int    $height Image height
     * @param string $size   Image size to use
     *
     * @return void
     */
    public function setParams($width, $height, $size = 'medium')
    {
        $this->width = $width;
        $this->height = $height;
        $this->size = $size;
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
        if (
            !$this->fetchFromAPI()
            && !$this->fetchFromContentType()
        ) {
            if ($this->generator) {
                $this->generator->setOptions($this->getCoverGeneratorSettings());
                $this->image = $this->generator->generate(
                    $settings['title'],
                    $settings['author'],
                    $settings['callnumber']
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
     * @param \Vufind\RecordDriver\DefaultRecord $driver Record
     * @param int                                $index  Image index
     * @param string                             $size   Requested size
     *
     * @return void
     */
    public function loadRecordImage(
        \VuFind\RecordDriver\DefaultRecord $driver,
        $index,
        $size
    ) {
        $this->index = $index;

        $params = $driver->tryMethod('getRecordImage', [$size, $index]);
        if (isset($params['url'])) {
            $this->id = $driver->getUniqueID();
            $this->url = $params['url'];
            $this->imageParams = $params;
            // Sometimes records only contain 1 size of an image, so check if the current requested image
            // is actually different size image. This helps to reduce amount of images cached.
            $this->size = $params['cacheSize'] ?? $size;
            return $this->fetchFromAPI();
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
        }

        $identifiers = parent::getIdentifiers();
        if ($this->invalidIsbn) {
            $identifiers['invisbn'] = $this->invalidIsbn;
        }
        return $identifiers;
    }

    /**
     * Support method for fetchFromAPI() -- set the localFile property.
     *
     * @param array  $ids     IDs returned by getIdentifiers() method
     * @param string $apiName Name of the API
     * @param bool   $unsized Get unsized image name used for scaling other images
     *
     * @return string
     */
    protected function determineLocalFile($ids, $apiName = 'default', bool $unsized = false)
    {
        $keys = [];

        if (isset($this->url)) {
            $keys['url'] = md5($this->url);
            $host = parse_url($this->url, PHP_URL_HOST);
            $keys['host'] = substr($host, 0, 100);
        } elseif (isset($ids['isbn'])) {
            $keys['isbn'] = $ids['isbn']->get13();
        } elseif (isset($ids['issn'])) {
            $keys['issn'] = $ids['issn'];
        } elseif (isset($ids['oclc'])) {
            $keys['oclc'] = $ids['oclc'];
        } elseif (isset($ids['upc'])) {
            $keys['upc'] = $ids['upc'];
        } elseif (isset($ids['invisbn'])) {
            $keys['invisbn'] = $ids['invisbn'];
        } else {
            if (isset($ids['recordid'])) {
                $keys['recordid'] = $ids['recordid'];
            }
            if (isset($ids['source'])) {
                $keys['source'] = $ids['source'];
            }
        }

        $keys = $unsized ? array_merge(
            $keys,
            [$this->index, 0, 0, $this->size]
        ) : array_merge(
            $keys,
            [$this->index, $this->width, $this->height, $this->size]
        );
        $file = implode('-', $keys);
        return $this->getCachePath('finna', "$apiName-$file");
    }

    /**
     * Load bookcover from cache or remote provider and display if possible.
     *
     * @return bool        True if image loaded, false on failure.
     */
    protected function fetchFromAPI()
    {
        // Check that we have at least one valid identifier:
        $ids = $this->getIdentifiers();
        if (empty($ids)) {
            return false;
        }
        $datasourceProviders = !empty($this->datasourceCoverConfig)
            ? explode(',', $this->datasourceCoverConfig) : [];
        $commonProviders = !empty($this->config->Content->coverimages)
            ? explode(',', $this->config->Content->coverimages) : [];
        $providers = array_filter(
            array_merge($datasourceProviders, $commonProviders)
        );

        // Try to find provider-specific cache file
        foreach ($providers as $provider) {
            $provider = explode(':', trim($provider));
            $apiName = strtolower(trim($provider[0]));
            $key = isset($provider[1]) ? trim($provider[1]) : null;
            try {
                $handler = $this->apiManager->get($apiName);

                // Is the current provider appropriate for the available data?
                if (
                    !$handler->supports($ids)
                    || !$handler->getUrl($key, $this->size, $ids)
                ) {
                    continue;
                }
                $this->localFile = $this->determineLocalFile($ids, $apiName);
                $this->unsizedImageFile = $this->determineLocalFile($ids, $apiName, true);
                if (is_readable($this->localFile)) {
                    // Load local cache if available
                    $this->contentType = 'image/jpeg';
                    $this->image = file_get_contents($this->localFile);
                    return true;
                } elseif (
                    is_readable($this->unsizedImageFile)
                    && $this->localFile = $this->createResizedImage($this->unsizedImageFile, $this->localFile)
                ) {
                    $this->contentType = 'image/jpeg';
                    $this->image = file_get_contents($this->localFile);
                    return true;
                }
            } catch (\Exception $e) {
                $this->debug(
                    $e::class . ' during cache processing of ' . $apiName
                    . ': ' . $e->getMessage()
                );
            }
        }
        // Try to fetch from providers
        foreach ($providers as $provider) {
            $provider = explode(':', trim($provider));
            $apiName = strtolower(trim($provider[0]));
            // Set up local file path:
            $this->localFile = $this->determineLocalFile($ids, $apiName);
            $this->unsizedImageFile = $this->determineLocalFile($ids, $apiName, true);
            $key = isset($provider[1]) ? trim($provider[1]) : null;
            try {
                $handler = $this->apiManager->get($apiName);

                // Is the current provider appropriate for the available data?
                if ($handler->supports($ids)) {
                    if ($url = $handler->getUrl($key, $this->size, $ids)) {
                        $success = $this->processImageURLForSource(
                            $url,
                            $handler->isCacheAllowed(),
                            $apiName
                        );
                        if ($success) {
                            return true;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->debug(
                    $e::class . ' during processing of ' . $apiName
                    . ': ' . $e->getMessage()
                );
            }
        }
        return false;
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
        // We can't proceed if we don't have image conversion functions:
        if (!is_callable('imagecreatefromstring')) {
            return false;
        }

        if (!$this->getUnsizedImage($url)) {
            return false;
        }
        // Set local file path to temporary file if the image is not to be cached
        $targetFile = $cache ? $this->localFile : str_replace('.jpg', uniqid(), $this->localFile) . '.jpg';
        // If the requested image has width and height of 0, then return the unsized image
        if (!$this->width && !$this->height) {
            $this->localFile = $this->unsizedImageFile;
        } elseif (!($this->localFile = $this->createResizedImage($this->unsizedImageFile, $targetFile))) {
            return false;
        }
        // Display the image:
        $this->contentType = 'image/jpeg';
        $this->image = file_get_contents($this->localFile);
        if (!$cache) {
            @unlink($this->unsizedImageFile);
            @unlink($this->localFile);
        }

        return true;
    }

    /**
     * Try to get the full size image to be used for scaling other images
     *
     * @param string $url URL to load image from
     *
     * @return bool
     */
    protected function getUnsizedImage(string $url)
    {
        $url = str_replace(
            [' ', 'ä','ö','å','Ä','Ö','Å'],
            ['%20','%C3%A4','%C3%B6','%C3%A5','%C3%84','%C3%96','%C3%85'],
            trim($url)
        );
        // Figure out file paths -- $tempFile will be used to store the
        // image for analysis. $finalFile will be used for long-term storage if
        // $cache is true or for temporary display purposes if $cache is false.
        // $statusFile is used for blocking a non-responding server for a while.
        $tempFile = str_replace('.jpg', uniqid(), $this->unsizedImageFile);

        $pdfFile
            = ($this->imageParams['pdf'] ?? false) || preg_match('/\.pdf$/i', $url);

        $convertPdfService
            = $this->config->Content->convertPdfToCoverImageService
            ?? false;

        if ($pdfFile && !$convertPdfService) {
            return false;
        }

        if ($pdfFile) {
            // Convert pdf to jpg
            $encodedUrl = urlencode($url);
            if (str_contains($convertPdfService, '%%url%%')) {
                $url = str_replace('%%url%%', $encodedUrl, $convertPdfService);
            } else {
                // BC
                $url = "$convertPdfService?url=$encodedUrl";
            }
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($this->isHostBlocked($host)) {
            return false;
        }

        // Attempt to pull down the image:
        try {
            $client = $this->httpService->createClient(
                $url,
                \Laminas\Http\Request::METHOD_GET,
                20
            );
            $client->setOptions(['useragent' => 'VuFind']);
            $client->setStream($tempFile);
            $result = $client->send();

            if (!$result->isSuccess()) {
                $this->debug("Failed to retrieve image from $url");
                return false;
            }
            if (filesize($tempFile) === 0) {
                throw new \Exception('Received empty file');
            }
            $this->addHostSuccess($host);
        } catch (\Exception $e) {
            $this->logError(
                "Exception trying to load '$url' (record: " . ($this->id ?: '-')
                . '): ' . $e->getMessage()
            );
            $this->addHostFailure($host);
            return false;
        }
        $exif = @exif_read_data($tempFile);
        $image = file_get_contents($tempFile);

        // We no longer need the temp file:
        @unlink($tempFile);

        if (strlen($image) === 0) {
            return false;
        }

        // Try to create a GD image and rewrite as JPEG, fail if we can't:
        if (!($imageGD = @imagecreatefromstring($image))) {
            return false;
        }

        if (isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];
            if ($orientation > 1 && $orientation < 9) {
                $imageGD = $this->rotateImage(
                    $imageGD,
                    $orientation
                );
            }
        }

        [$width, $height, $type] = @getimagesizefromstring($image);
        // Save unsized image, with quality 100
        if ($type !== IMG_JPG) {
            if (!@imagejpeg($imageGD, $this->unsizedImageFile, 100)) {
                return false;
            }
        } elseif (false === file_put_contents($this->unsizedImageFile, $image)) {
            return false;
        }

        return true;
    }

    /**
     * Create a resized image from the unsized file.
     *
     * @param string $originalFile Original file path
     * @param string $targetFile   Target file path
     *
     * @return string|false Returns the target file if successful, false on failure
     */
    protected function createResizedImage(string $originalFile, string $targetFile): string|false
    {
        if (!file_exists($originalFile)) {
            return false;
        }

        $image = file_get_contents($originalFile);
        [$width, $height, $type] = @getimagesizefromstring($image);
        $this->localFile = $targetFile;
        // Try to create a GD image and rewrite as JPEG, fail if we can't:
        if (!($imageGD = @imagecreatefromstring($image))) {
            return false;
        }
        // If image width and size is requested, then create a new image from the unsized image
        $reqWidth = $this->width ?: $width;
        $reqHeight = $this->height ?: $height;

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
                $imageGDResized,
                $imageGD,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $width,
                $height
            );

            if (!@imagejpeg($imageGDResized, $targetFile, $quality)) {
                return false;
            }
        } elseif ($type !== IMG_JPG) {
            if (!@imagejpeg($imageGD, $targetFile, $quality)) {
                return false;
            }
        } else {
            file_put_contents($targetFile, $image);
        }

        return $targetFile;
    }

    /**
     * Method for rotating the given image with exif orientation data
     *
     * @param resource $image       Image to rotate
     * @param int      $orientation Orientation data of the original image
     *
     * @return resource
     */
    protected function rotateImage($image, $orientation)
    {
        switch ($orientation) {
            case 2: // horizontal flip
                return imageflip($image, 1);
                break;
            case 3: // 180 rotate left
                return imagerotate($image, 180, 0);
                break;
            case 4: // vertical flip
                return imageflip($image, 2);
                break;
            case 5: // vertical flip + 90 rotate right
                return imagerotate(imageflip($image, 2), -90, 0);
                break;
            case 6: // 90 rotate right
                return imagerotate($image, -90, 0);
                break;
            case 7: // horizontal flip + 90 rotate right
                return imagerotate(imageflip($image, 1), -90, 0);
                break;
            case 8: // 90 rotate left
                return imagerotate($image, 90, 0);
                break;
            default: // no rotation found
                return $image;
                break;
        }
    }

    /**
     * Support method for loadImage() -- sanitize and store some key values.
     *
     * @param array $settings Settings from loadImage (with missing defaults
     * already filled in).
     *
     * @return void
     */
    protected function storeSanitizedSettings($settings)
    {
        parent::storeSanitizedSettings($settings);
        $this->invalidIsbn = $settings['invisbn'] ?? '';
    }

    /**
     * Check if a server has been temporarily blocked due to failures
     *
     * @param string $host Host name
     *
     * @return bool
     */
    protected function isHostBlocked($host)
    {
        $statusFile = $this->getStatusFilePath($host);
        if (!file_exists($statusFile)) {
            return false;
        }
        $blockDuration = $this->config->Content->coverServerFailureBlockDuration
            ?? 3600;
        if (filemtime($statusFile) + $blockDuration < time()) {
            unlink($statusFile);
            $this->logWarning("Host $host has been unblocked");
            return false;
        }
        $tries = file_get_contents($statusFile);
        $blockThreshold = $this->config->Content->coverServerFailureBlockThreshold
            ?? 10;
        if ($tries >= $blockThreshold) {
            $reCheckTime = $this->config->Content->coverServerFailureReCheckTime
                ?? 60;
            if (filemtime($statusFile) + $reCheckTime < time()) {
                $this->logWarning("Host $host has been tentatively unblocked");
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Record a failure for a server
     *
     * @param string $host Host name
     *
     * @return void
     */
    protected function addHostFailure($host)
    {
        $statusFile = $this->getStatusFilePath($host);
        $failures = 0;
        $blockDuration = $this->config->Content->coverServerFailureBlockDuration
            ?? 3600;
        if (
            file_exists($statusFile)
            && filemtime($statusFile) + $blockDuration >= time()
        ) {
            $failures = file_get_contents($statusFile);
        }
        ++$failures;
        file_put_contents($statusFile, $failures, LOCK_EX);
        $this->logWarning("Host $host has $failures recorded failures");
    }

    /**
     * Record a success for a server
     *
     * @param string $host Host name
     *
     * @return void
     */
    protected function addHostSuccess($host)
    {
        $statusFile = $this->getStatusFilePath($host);
        if (file_exists($statusFile)) {
            $this->logWarning("Host $host success, failure count cleared");
            unlink($statusFile);
        }
    }

    /**
     * Get status tracking file path for a host
     *
     * @param string $host Host name
     *
     * @return string
     */
    protected function getStatusFilePath($host)
    {
        $base = $this->baseDir;
        if (!is_dir($base)) {
            mkdir($base);
        }
        $base .= '/';
        if (!is_dir($base)) {
            mkdir($base);
        }
        return $base . '/' . urlencode($host) . '.status';
    }
}
