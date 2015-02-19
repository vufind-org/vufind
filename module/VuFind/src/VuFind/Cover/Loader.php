<?php
/**
 * Book Cover Generator
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace VuFind\Cover;
use VuFindCode\ISBN, VuFind\Content\Covers\PluginManager as ApiManager;

/**
 * Book Cover Generator
 *
 * @category VuFind2
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
class Loader extends \VuFind\ImageLoader
{
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
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Plugin manager for API handlers
     *
     * @var ApiManager
     */
    protected $apiManager;

    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * Directory to store downloaded images
     *
     * @var string
     */
    protected $baseDir;

    /**
     * User ISBN parameter
     *
     * @var ISBN
     */
    protected $isbn = null;

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
     * Constructor
     *
     * @param \Zend\Config\Config    $config  VuFind configuration
     * @param ApiManager             $manager Plugin manager for API handlers
     * @param \VuFindTheme\ThemeInfo $theme   VuFind theme tools
     * @param \Zend\Http\Client      $client  HTTP client
     * @param string                 $baseDir Directory to store downloaded images
     * (set to system temp dir if not otherwise specified)
     */
    public function __construct($config, ApiManager $manager,
        \VuFindTheme\ThemeInfo $theme, \Zend\Http\Client $client, $baseDir = null
    ) {
        $this->setThemeInfo($theme);
        $this->config = $config;
        $this->configuredFailImage = isset($config->Content->noCoverAvailableImage)
            ? $config->Content->noCoverAvailableImage : null;
        $this->apiManager = $manager;
        $this->client = $client;
        $this->baseDir = (null === $baseDir)
            ? rtrim(sys_get_temp_dir(), '\\/') . '/covers'
            : rtrim($baseDir, '\\/');
    }

    /**
     * Get Cover Generator Object
     *
     * @return VuFind\Cover\Generator
     */
    public function getCoverGenerator()
    {
        return new \VuFind\Cover\Generator(
            $this->themeTools,
            ['mode' => $this->config->Content->makeDynamicCovers]
        );
    }

    /**
     * Load an image given an ISBN and/or content type.
     *
     * @param string $isbn       ISBN
     * @param string $size       Requested size
     * @param string $type       Content type
     * @param string $title      Title of book (for dynamic covers)
     * @param string $author     Author of the book (for dynamic covers)
     * @param string $callnumber Callnumber (unique id for dynamic covers)
     * @param string $issn       ISSN
     * @param string $oclc       OCLC number
     * @param string $upc        UPC number
     *
     * @return void
     */
    public function loadImage($isbn = null, $size = 'small', $type = null,
        $title = null, $author = null, $callnumber = null, $issn = null,
        $oclc = null, $upc = null
    ) {
        // Sanitize parameters:
        $this->isbn = new ISBN($isbn);
        $this->issn = empty($issn)
            ? null
            : substr(preg_replace('/[^0-9X]/', '', strtoupper($issn)), 0, 8);
        $this->oclc = $oclc;
        $this->upc = $upc;
        $this->type = preg_replace("/[^a-zA-Z]/", "", $type);
        $this->size = $size;

        // Display a fail image unless our parameters pass inspection and we
        // are able to display an ISBN or content-type-based image.
        if (!in_array($this->size, $this->validSizes)) {
            $this->loadUnavailable();
        } else if (!$this->fetchFromAPI()
            && !$this->fetchFromContentType()
        ) {
            if (isset($this->config->Content->makeDynamicCovers)
                && false !== $this->config->Content->makeDynamicCovers
            ) {
                $this->image = $this->getCoverGenerator()
                    ->generate($title, $author, $callnumber);
                $this->contentType = 'image/png';
            } else {
                $this->loadUnavailable();
            }
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
        // We should check whether we have cached images for the 13- or 10-digit
        // ISBNs. If no file exists, we'll favor the 10-digit number if
        // available for the sake of brevity.
        if (isset($ids['isbn'])) {
            $file = $this->getCachePath($this->size, $ids['isbn']->get13());
            if (!is_readable($file) && $ids['isbn']->get10()) {
                return $this->getCachePath($this->size, $ids['isbn']->get10());
            }
            return $file;
        } else if (isset($ids['issn'])) {
            return $this->getCachePath($this->size, $ids['issn']);
        } else if (isset($ids['oclc'])) {
            return $this->getCachePath($this->size, 'OCLC' . $ids['oclc']);
        } else if (isset($ids['upc'])) {
            return $this->getCachePath($this->size, 'UPC' . $ids['upc']);
        }
        throw new \Exception('Unexpected code path reached!');
    }

    /**
     * Get all valid identifiers as an associative array.
     *
     * @return array
     */
    protected function getIdentifiers()
    {
        $ids = [];
        if ($this->isbn && $this->isbn->isValid()) {
            $ids['isbn'] = $this->isbn;
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
        } else if (isset($this->config->Content->coverimages)) {
            $providers = explode(',', $this->config->Content->coverimages);
            foreach ($providers as $provider) {
                $provider = explode(':', trim($provider));
                $apiName = strtolower(trim($provider[0]));
                $key = isset($provider[1]) ? trim($provider[1]) : null;
                try {
                    $handler = $this->apiManager->get($apiName);

                    // Is the current provider appropriate for the available data?
                    if ($handler->supports($ids)) {
                        if ($url = $handler->getUrl($key, $this->size, $ids)) {
                            $success = $this->processImageURLForSource(
                                $url, $handler->isCacheAllowed(), $apiName
                            );
                            if ($success) {
                                return true;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->debug(
                        get_class($e) . ' during processing of ' . $apiName
                        . ': ' . $e->getMessage()
                    );
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
        list($width, $height, $type) = @getimagesize($tempFile);

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
            } else if ($conf === false || $conf === 0 || $conf === '0'
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
