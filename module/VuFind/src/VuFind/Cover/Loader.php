<?php

/**
 * Book Cover Generator
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

namespace VuFind\Cover;

use VuFind\Content\Covers\PluginManager as ApiManager;
use VuFindCode\ISBN;
use VuFindCode\ISMN;

use function func_get_args;
use function in_array;
use function is_array;
use function is_callable;
use function strlen;

/**
 * Book Cover Generator
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
class Loader extends \VuFind\ImageLoader
{
    /**
     * Class for rendering cover images dynamically if no API match found. Omit
     * to disable functionality.
     *
     * @var Generator
     */
    protected $generator = null;

    /**
     * Filename constructed from ISBN
     *
     * @var string
     */
    protected $localFile = '';

    /**
     * Valid image sizes to request
     *
     * @var array
     */
    protected $validSizes = ['small', 'medium', 'large'];

    /**
     * VuFind configuration settings
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Plugin manager for API handlers
     *
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * HTTP client factory
     *
     * @var \VuFindHttp\HttpService
     */
    protected $httpService;

    /**
     * Directory to store downloaded images
     *
     * @var string
     */
    protected $baseDir;

    /**
     * User ISBNs parameter
     *
     * @var ISBN[]
     */
    protected $isbns = null;

    /**
     * User ISSN parameter
     *
     * @var string
     */
    protected $issn = null;

    /**
     * User OCLC number parameter
     *
     * @var string
     */
    protected $oclc = null;

    /**
     * User UPC number parameter
     *
     * @var string
     */
    protected $upc = null;

    /**
     * User National bibliography number parameter
     *
     * @var array
     */
    protected $nbn = null;

    /**
     * User ISMN parameter
     *
     * @var ISMN
     */
    protected $ismn = null;

    /**
     * User UUID parameter
     *
     * @var string
     */
    protected $uuid = null;

    /**
     * User record id number parameter
     *
     * @var string
     */
    protected $recordid = null;

    /**
     * User record source parameter
     *
     * @var string
     */
    protected $source = null;

    /**
     * User size parameter
     *
     * @var string
     */
    protected $size;

    /**
     * User type parameter
     *
     * @var string
     */
    protected $type;

    /**
     * Flag denoting the last loaded image was a FailImage
     *
     * @var bool
     */
    protected $hasLoadedUnavailable = false;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config  $config      VuFind configuration
     * @param ApiManager              $manager     Plugin manager for API handlers
     * @param \VuFindTheme\ThemeInfo  $theme       VuFind theme tools
     * @param \VuFindHttp\HttpService $httpService HTTP client factory
     * @param string                  $baseDir     Directory to store downloaded
     * images (set to system temp dir if not otherwise specified)
     */
    public function __construct(
        $config,
        ApiManager $manager,
        \VuFindTheme\ThemeInfo $theme,
        \VuFindHttp\HttpService $httpService,
        $baseDir = null
    ) {
        $this->setThemeInfo($theme);
        $this->config = $config;
        $this->configuredFailImage = $config->Content->noCoverAvailableImage ?? null;
        $this->apiManager = $manager;
        $this->httpService = $httpService;
        $this->baseDir = (null === $baseDir)
            ? rtrim(sys_get_temp_dir(), '\\/') . '/covers'
            : rtrim($baseDir, '\\/');
    }

    /**
     * Get settings for the cover generator.
     *
     * @return array
     */
    protected function getCoverGeneratorSettings()
    {
        $settings = isset($this->config->DynamicCovers)
            ? $this->config->DynamicCovers->toArray() : [];
        if (
            !isset($settings['backgroundMode'])
            && isset($this->config->Content->makeDynamicCovers)
        ) {
            $settings['backgroundMode'] = $this->config->Content->makeDynamicCovers;
        }
        $size = $this->size;
        $pickSize = function ($setting) use ($size) {
            if (isset($setting[$size])) {
                return $setting[$size];
            }
            if (isset($setting['*'])) {
                return $setting['*'];
            }
            return $setting;
        };
        return array_map($pickSize, $settings);
    }

    /**
     * Set Cover Generator Object
     *
     * @param Generator $generator Cover generator
     *
     * @return void
     */
    public function setCoverGenerator(Generator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get default settings for loadImage().
     *
     * @return array
     */
    protected function getDefaultSettings()
    {
        return [
            'isbns' => null,
            'size' => 'small',
            'type' => null,
            'title' => null,
            'author' => null,
            'callnumber' => null,
            'issn' => null,
            'oclc' => null,
            'upc' => null,
            'recordid' => null,
            'source' => null,
            'nbn' => null,
            'ismn' => null,
            'uuid' => null,
        ];
    }

    /**
     * Translate legacy function arguments into new-style array.
     *
     * @param array $args Function arguments
     *
     * @return array
     */
    protected function getImageSettingsFromLegacyArgs($args)
    {
        return [
            'isbn' => $args[0],
            'size' => $args[1],
            'type' => $args[2],
            'title' => $args[3],
            'author' => $args[4],
            'callnumber' => $args[5],
            'issn' => $args[6],
            'oclc' => $args[7],
            'upc' => $args[8],
        ];
    }

    /**
     * Support method for loadImage() -- sanitize and store some key values.
     *
     * @param array $settings Settings from loadImage
     *
     * @return void
     */
    protected function storeSanitizedSettings($settings)
    {
        $settings = array_merge($this->getDefaultSettings(), $settings);
        $this->isbns = array_map(
            function ($isbn) {
                return new ISBN($isbn);
            },
            $settings['isbns']
                ?? (empty($settings['isbn']) ? [] : [$settings['isbn']])
        );
        $this->ismn = new ISMN($settings['ismn'] ?? '');
        if (!empty($settings['issn'])) {
            $rawissn = preg_replace('/[^0-9X]/', '', strtoupper($settings['issn']));
            $this->issn = substr($rawissn, 0, 8);
        } else {
            $this->issn = null;
        }
        $this->oclc = $settings['oclc'];
        $this->upc = $settings['upc'];
        $this->recordid = $settings['recordid'];
        $this->source = $settings['source'];
        $this->nbn = $settings['nbn'];
        $this->uuid = $settings['uuid'];
        $this->type = preg_replace('/[^a-zA-Z]/', '', $settings['type'] ?? '');
        $this->size = $settings['size'];
    }

    /**
     * Load an image given an ISBN and/or content type.
     *
     * @param array $settings Array of settings used to calculate a cover; may
     * contain any or all of these keys: 'isbns' (array of ISBNs), 'size' (requested
     * size), 'type' (content type), 'title' (title of book, for dynamic covers),
     * 'author' (author of book, for dynamic covers), 'callnumber' (unique ID, for
     * dynamic covers), 'issn' (ISSN), 'oclc' (OCLC number), 'upc' (UPC number),
     * 'nbn' (national bibliography number), 'ismn' (ISMN), 'uuid' (Universally
     * unique identifier).
     *
     * @return void
     */
    public function loadImage($settings = [])
    {
        // reset to normal
        $this->hasLoadedUnavailable = false;
        // Load settings from legacy function parameters if they are not passed
        // in as an array:
        $settings = is_array($settings)
            ? $settings
            : $this->getImageSettingsFromLegacyArgs(func_get_args());

        // Store sanitized versions of some parameters for future reference:
        $this->storeSanitizedSettings($settings);

        // Display a fail image unless our parameters pass inspection and we
        // are able to display an ISBN or content-type-based image.
        if (!in_array($this->size, $this->validSizes)) {
            $this->loadUnavailable();
        } elseif (
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
     * {@inheritdoc}
     * Adds @see self::$hasLoadedUnavailable flag
     *
     * @return void
     */
    public function loadUnavailable()
    {
        $this->hasLoadedUnavailable = true;
        parent::loadUnavailable();
    }

    /**
     * Returns true if the last loaded image was the FailImage
     *
     * @return bool
     */
    public function hasLoadedUnavailable()
    {
        return $this->hasLoadedUnavailable;
    }

    /**
     * Support method for fetchFromAPI() -- set the localFile property.
     *
     * @param array $ids IDs returned by getIdentifiers() method
     *
     * @return string
     */
    protected function determineLocalFile($ids)
    {
        // We should check whether we have cached images for the 13- or 10-digit
        // ISBNs. If no file exists, we'll favor the 10-digit number if
        // available for the sake of brevity.
        if (isset($ids['isbn'])) {
            $file = $this->getCachePath($this->size, $ids['isbn']->get13());
            if (!is_readable($file) && $ids['isbn']->get10()) {
                return $this->getCachePath($this->size, $ids['isbn']->get10());
            }
            return $file;
        } elseif (isset($ids['issn'])) {
            return $this->getCachePath($this->size, $ids['issn']);
        } elseif (isset($ids['oclc'])) {
            return $this->getCachePath($this->size, 'OCLC' . $ids['oclc']);
        } elseif (isset($ids['upc'])) {
            return $this->getCachePath($this->size, 'UPC' . $ids['upc']);
        } elseif (isset($ids['nbn'])) {
            return $this->getCachePath($this->size, 'NBN' . $ids['nbn']);
        } elseif (isset($ids['ismn'])) {
            return $this->getCachePath($this->size, 'ISMN' . $ids['ismn']->get13());
        } elseif (isset($ids['uuid'])) {
            return $this->getCachePath($this->size, 'UUID' . $ids['uuid']);
        } elseif (isset($ids['recordid']) && isset($ids['source'])) {
            return $this->getCachePath(
                $this->size,
                'ID' . md5($ids['source'] . '|' . $ids['recordid'])
            );
        }
        throw new \Exception('Cannot determine local file path.');
    }

    /**
     * Get all valid identifiers as an associative array.
     *
     * @return array
     */
    protected function getIdentifiers()
    {
        $ids = [];
        if (!empty($this->isbns)) {
            $ids['isbn'] = $this->isbns[0];
            $ids['isbns'] = $this->isbns;
        }
        if ($this->issn && strlen($this->issn) == 8) {
            $ids['issn'] = $this->issn;
        }
        if ($this->oclc && strlen($this->oclc) > 0) {
            $ids['oclc'] = $this->oclc;
        }
        if ($this->upc && strlen($this->upc) > 0) {
            $ids['upc'] = $this->upc;
        }
        if ($this->nbn && strlen($this->nbn) > 0) {
            $ids['nbn'] = $this->nbn;
        }
        if ($this->ismn && $this->ismn->isValid()) {
            $ids['ismn'] = $this->ismn;
        }
        if ($this->uuid && strlen($this->uuid) > 0) {
            $ids['uuid'] = $this->uuid;
        }
        if ($this->recordid && strlen($this->recordid) > 0) {
            $ids['recordid'] = $this->recordid;
        }
        if ($this->source && strlen($this->source) > 0) {
            $ids['source'] = $this->source;
        }
        return $ids;
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

        // Set up local file path:
        $this->localFile = $this->determineLocalFile($ids);
        if (is_readable($this->localFile)) {
            // Load local cache if available
            $this->contentType = 'image/jpeg';
            $this->image = file_get_contents($this->localFile);
            return true;
        } else {
            $urls = $this->getCoverUrls();
            foreach ($urls as $url) {
                $success = $this->processImageURLForSource(
                    $url['url'],
                    $url['handler']->isCacheAllowed(),
                    $url['apiName']
                );
                if ($success) {
                    return true;
                }
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
        $base .= '/' . $size;
        if (!is_dir($base)) {
            mkdir($base);
        }
        return $base . '/' . $id . '.' . $extension;
    }

    /**
     * Load content type icon image from URL from theme images and display if
     * possible.
     *
     * @return bool        True if image loaded, false on failure.
     */
    protected function fetchFromContentType()
    {
        // Give up if no content type was passed in:
        if (empty($this->type)) {
            return false;
        }

        // Try to find an icon:
        $iconFile = $this->searchTheme(
            'images/' . $this->size . '/' . $this->type,
            ['.png', '.gif', '.jpg']
        );
        if ($iconFile !== false) {
            // Most content-type headers match file extensions... but
            // include a special case for jpg vs. jpeg:
            $format = substr($iconFile, -3);
            $this->contentType
                = 'image/' . ($format == 'jpg' ? 'jpeg' : $format);
            $this->image = file_get_contents($iconFile);
            return true;
        }

        // If we got this far, no icon was found:
        return false;
    }

    /**
     * Support method for validateAndMoveTempFile -- convert non-JPEG image data to a
     * JPEG file.
     *
     * @param string $imageData Raw image data
     * @param string $jpeg      JPEG file (output)
     *
     * @return bool             Did we succeed?
     */
    protected function convertNonJpeg($imageData, $jpeg)
    {
        // We can't proceed if we don't have image conversion functions:
        if (!is_callable('imagecreatefromstring')) {
            return false;
        }

        // Try to create a GD image and rewrite as JPEG, fail if we can't:
        if (!($imageGD = @imagecreatefromstring($imageData))) {
            return false;
        }
        if (!@imagejpeg($imageGD, $jpeg)) {
            return false;
        }

        return true;
    }

    /**
     * This method either moves the temporary file to its final location (true)
     * or detects an error and deletes it (false).
     *
     * @param string $image     Raw image data
     * @param string $tempFile  Temporary file
     * @param string $finalFile Final file location
     *
     * @return bool
     */
    protected function validateAndMoveTempFile($image, $tempFile, $finalFile)
    {
        [$width, $height, $type] = @getimagesize($tempFile);

        // File too small -- delete it and report failure.
        if ($width < 2 && $height < 2) {
            @unlink($tempFile);
            return false;
        }

        // Conversion needed -- do some normalization for non-JPEG images:
        if ($type != IMAGETYPE_JPEG) {
            // We no longer need the temp file:
            @unlink($tempFile);
            return $this->convertNonJpeg($image, $finalFile);
        }

        // If $tempFile is already a JPEG, let's store it in the cache.
        return @rename($tempFile, $finalFile);
    }

    /**
     * Wrapper around processImageURL to determine cache setting based on
     * image source.
     *
     * @param string $url        URL to load image from
     * @param bool   $allowCache Is caching allowed by the service?
     * @param string $source     Service being used for image loading
     *
     * @return bool         True if image loaded, false on failure.
     */
    protected function processImageURLForSource($url, $allowCache, $source)
    {
        // If caching is allowed at the source level, let's see if it's locally
        // configured....
        if ($allowCache) {
            // All other services cache based on configuration:
            $conf = isset($this->config->Content->coverimagesCache)
                ? trim(strtolower($this->config->Content->coverimagesCache)) : true;
            if ($conf === true || $conf === 1 || $conf === '1' || $conf === 'true') {
                $cache = true;
            } elseif (
                $conf === false || $conf === 0 || $conf === '0'
                || $conf === 'false'
            ) {
                $cache = false;
            } else {
                $conf = array_map('trim', explode(',', $conf));
                $source = strtolower($source);
                $cache = in_array($source, $conf);
            }
        } else {
            $cache = false;
        }
        return $this->processImageURL($url, $cache);
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
        // Check to see if url is a file path
        if (str_starts_with($url, 'file://')) {
            $imagePath = substr($url, 7);

            // Display the image:
            $this->contentType = mime_content_type($imagePath);
            $this->image = file_get_contents($imagePath);
            return true;
        } else {
            // Attempt to pull down the image:
            $result = $this->httpService->createClient($url)->send();
            if (!$result->isSuccess()) {
                $this->debug('Failed to retrieve image from ' . $url);
                return false;
            }
            $image = $result->getBody();

            if ('' == $image) {
                return false;
            }

            // Figure out file paths -- $tempFile will be used to store the
            // image for analysis. $finalFile will be used for long-term storage if
            // $cache is true or for temporary display purposes if $cache is false.
            $tempFile = str_replace('.jpg', uniqid(), $this->localFile);
            $finalFile = $cache ? $this->localFile : $tempFile . '.jpg';

            // Write image data to disk:
            if (!@file_put_contents($tempFile, $image)) {
                throw new \Exception('Unable to write to image directory.');
            }

            // Move temporary file to final location:
            if (!$this->validateAndMoveTempFile($image, $tempFile, $finalFile)) {
                return false;
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

    /**
     * Get urls for defined provider, works as generator
     *
     * @return array
     */
    protected function getCoverUrls()
    {
        $ids = $this->getIdentifiers();
        $handlers = $this->getHandlers();
        foreach ($handlers as $handler) {
            try {
                // Is the current provider appropriate for the available data?
                if ($handler['handler']->supports($ids)) {
                    $url = $handler['handler']
                        ->getUrl($handler['key'], $this->size, $ids);
                    if ($url) {
                        yield [
                            'url' => $url,
                            'apiName' => $handler['apiName'],
                            'handler' => $handler['handler'],
                        ];
                    }
                }
            } catch (\Exception $e) {
                $this->debug(
                    $e::class . ' during processing of ' . $handler['apiName']
                    . ': ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Return API handlers
     *
     * @return \Generator Array with keys: key - API key, apiName - api name from
     * configuration, handler - handler object
     */
    public function getHandlers()
    {
        if (!isset($this->config->Content->coverimages)) {
            return [];
        }
        $providers = explode(',', $this->config->Content->coverimages);
        foreach ($providers as $provider) {
            $provider = explode(':', trim($provider));
            $apiName = strtolower(trim($provider[0]));
            $key = isset($provider[1]) ? trim($provider[1]) : null;
            yield [
                'key' => $key,
                'apiName' => $apiName,
                'handler' => $this->apiManager->get($apiName),
            ];
        }
    }

    /**
     * Get identifiers for given settings
     *
     * @param array $settings Settings from loadImage
     *
     * @return array
     */
    public function getIdentifiersForSettings($settings)
    {
        $this->storeSanitizedSettings($settings);
        return $this->getIdentifiers();
    }
}
