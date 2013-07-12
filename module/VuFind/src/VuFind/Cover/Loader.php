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
use VuFind\Code\ISBN, Zend\Log\LoggerInterface, ZendService\Amazon\Amazon;

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
class Loader implements \Zend\Log\LoggerAwareInterface
{
    /**
     * filename constructed from ISBN
     *
     * @var string
     */
    protected $localFile = '';

    /**
     * valid image sizes to request
     *
     * @var array
     */
    protected $validSizes = array('small', 'medium', 'large');

    /**
     * property to hold VuFind configuration settings
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * directory to store downloaded images
     *
     * @var string
     */
    protected $baseDir;

    /**
     * User ISN parameter
     *
     * @var string
     */
    protected $isn;

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
     * Logger (or false for none)
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Theme tools
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeTools;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config    $config  VuFind configuration
     * @param \VuFindTheme\ThemeInfo $theme   VuFind theme tools
     * @param \Zend\Http\Client      $client  HTTP client
     * @param string                 $baseDir Directory to store downloaded images
     * (set to system temp dir if not otherwise specified)
     */
    public function __construct($config, \VuFindTheme\ThemeInfo $theme,
        \Zend\Http\Client $client, $baseDir = null
    ) {
        $this->config = $config;
        $this->themeTools = $theme;
        $this->client = $client;
        $this->baseDir = rtrim(
            is_null($baseDir) ? sys_get_temp_dir() : $baseDir, '\\/'
        );
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * Get the image data (usually called after loadImage)
     *
     * @return string
     */
    public function getImage()
    {
        // No image loaded?  Use "unavailable" as default:
        if (is_null($this->image)) {
            $this->loadUnavailable();
        }
        return $this->image;
    }

    /**
     * Get the content type of the current image (usually called after loadImage)
     *
     * @return string
     */
    public function getContentType()
    {
        // No content type loaded?  Use "unavailable" as default:
        if (is_null($this->contentType)) {
            $this->loadUnavailable();
        }
        return $this->contentType;
    }

    /**
     * Load an image given an ISBN and/or content type.
     *
     * @param string $isn  ISBN
     * @param string $size Requested size
     * @param string $type Content type
     *
     * @return void
     */
    public function loadImage($isn, $size = 'small', $type = null)
    {
        // Sanitize parameters:
        $this->isn = preg_replace('/[^0-9xX]/', '', $isn);
        $this->type = preg_replace("/[^a-zA-Z]/", "", $type);
        $this->size = $size;

        // Display a fail image unless our parameters pass inspection and we
        // are able to display an ISBN or content-type-based image.
        if (!in_array($this->size, $this->validSizes)) {
            $this->loadUnavailable();
        } else if (!$this->fetchFromISBN()
            && !$this->fetchFromContentType()
        ) {
            $this->loadUnavailable();
        }
    }

    /**
     * Load bookcover fom URL from cache or remote provider and display if possible.
     *
     * @return bool        True if image displayed, false on failure.
     */
    protected function fetchFromISBN()
    {
        if (empty($this->isn)) {
            return false;
        }

        // We should check whether we have cached images for the 13- or 10-digit
        // ISBNs. If no file exists, we'll favor the 10-digit number if
        // available for the sake of brevity.
        $isbn = new ISBN($this->isn);
        if ($isbn->get13()) {
            $this->localFile = $this->getCachePath($this->size, $isbn->get13());
        } else {
            // Invalid ISBN?  Keep it as-is to avoid a bad file path; the error will
            // be caught later down the line anyway.
            $this->localFile = $this->getCachePath($this->size, $this->isn);
        }
        if (!is_readable($this->localFile) && $isbn->get10()) {
            $this->localFile = $this->getCachePath($this->size, $isbn->get10());
        }
        if (is_readable($this->localFile)) {
            // Load local cache if available
            $this->contentType = 'image/jpeg';
            $this->image = file_get_contents($this->localFile);
            return true;
        } else {
            // Fetch from provider
            if (isset($this->config->Content->coverimages)) {
                $providers = explode(',', $this->config->Content->coverimages);
                foreach ($providers as $provider) {
                    $provider = explode(':', trim($provider));
                    $func = trim($provider[0]);
                    $key = isset($provider[1]) ? trim($provider[1]) : null;
                    if ($this->$func($key)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Return a path to the image cache for the given size and ISN; ensure that
     * directories are created as needed.
     *
     * @param string $size      Size category
     * @param string $isn       ISBN
     * @param string $extension File extension to use (default = jpg)
     *
     * @return string      Cache path
     */
    protected function getCachePath($size, $isn, $extension = 'jpg')
    {
        $base = $this->baseDir . '/covers';
        if (!is_dir($base)) {
            mkdir($base);
        }
        $base .= '/' . $size;
        if (!is_dir($base)) {
            mkdir($base);
        }
        return $base . '/' . $isn . '.' . $extension;
    }

    /**
     * Load content type icon image from URL from theme images and display if
     * possible.
     *
     * @return bool        True if image displayed, false on failure.
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
            array('.png', '.gif', '.jpg')
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
     * Find a file in the themes (return false if no file exists).
     *
     * @param string $path    Relative path of file to find.
     * @param array  $formats Optional array of suffixes to add to $path while
     * searching theme (used to check multiple extensions in each theme).
     *
     * @return string|bool
     */
    protected function searchTheme($path, $formats = array(''))
    {
        // Check all supported image formats:
        $filenames = array();
        foreach ($formats as $format) {
            $filenames[] =  $path . $format;
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
        // Get "no cover" image from config.ini:
        $noCoverImage = isset($this->config->Content->noCoverAvailableImage)
            ? $this->searchTheme($this->config->Content->noCoverAvailableImage)
            : null;

        // No setting -- use default, and don't log anything:
        if (empty($noCoverImage)) {
            // log?
            return $this->loadDefaultFailImage();
        }

        // If file defined but does not exist, log error and display default:
        if (!file_exists($noCoverImage) || !is_readable($noCoverImage)) {
            $this->debug("Cannot access file: '$noCoverImage'");
            return $this->loadDefaultFailImage();
        }

        // Array containing map of allowed file extensions to mimetypes
        // (to be extended)
        $allowedFileExtensions = array(
            "gif" => "image/gif",
            "jpeg" => "image/jpeg", "jpg" => "image/jpeg",
            "png" => "image/png",
            "tiff" => "image/tiff", "tif" => "image/tiff"
        );

        // Log error and bail out if file lacks a known image extension:
        $parts = explode('.', $noCoverImage);
        $fileExtension = strtolower(end($parts));
        if (!array_key_exists($fileExtension, $allowedFileExtensions)) {
            $this->debug(
                "Illegal file-extension '$fileExtension' for image '$noCoverImage'"
            );
            return $this->loadDefaultFailImage();
        }

        // Get mime type from file extension:
        $this->contentType = $allowedFileExtensions[$fileExtension];

        // Load the image data:
        $this->image = file_get_contents($noCoverImage);
    }

    /**
     * Display the default "cover unavailable" graphic and terminate execution.
     *
     * @return void
     */
    protected function loadDefaultFailImage()
    {
        $this->contentType = 'image/gif';
        $this->image = file_get_contents($this->searchTheme('images/noCover2.gif'));
    }

    /**
     * Load image from URL, store in cache if requested, display if possible.
     *
     * @param string $url   URL to load image from
     * @param string $cache Boolean -- should we store in local cache?
     *
     * @return bool         True if image displayed, false on failure.
     */
    protected function processImageURL($url, $cache = true)
    {
        // Attempt to pull down the image:
        $result = $this->client->setUri($url)->send();
        if ($result->isSuccess()) {
            $image = $result->getBody();

            // Figure out file paths -- $tempFile will be used to store the
            // image for analysis.  $finalFile will be used for long-term storage if
            // $cache is true or for temporary display purposes if $cache is false.
            $tempFile = str_replace('.jpg', uniqid(), $this->localFile);
            $finalFile = $cache ? $this->localFile : $tempFile . '.jpg';

            // If some services can't provide an image, they will serve a 1x1 blank
            // or give us invalid image data.  Let's analyze what came back before
            // proceeding.
            if (!@file_put_contents($tempFile, $image)) {
                throw new \Exception("Unable to write to image directory.");
            }
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

                // We can't proceed if we don't have image conversion functions:
                if (!is_callable('imagecreatefromstring')) {
                    return false;
                }

                // Try to create a GD image and rewrite as JPEG, fail if we can't:
                if (!($imageGD = @imagecreatefromstring($image))) {
                    return false;
                }
                if (!@imagejpeg($imageGD, $finalFile)) {
                    return false;
                }
            } else {
                // If $tempFile is already a JPEG, let's store it in the cache.
                @rename($tempFile, $finalFile);
            }

            // Display the image:
            $this->contentType = 'image/jpeg';
            $this->image = file_get_contents($finalFile);

            // If we don't want to cache the image, delete it now that we're done.
            if (!$cache) {
                @unlink($finalFile);
            }

            return true;
        } else {
            $this->debug("Failed to retrieve image from " + $url);
            return false;
        }
    }

    /**
     * Retrieve a Syndetics cover.
     *
     * @param string $id Syndetics client ID.
     *
     * @return bool      True if image displayed, false otherwise.
     */
    protected function syndetics($id)
    {
        switch ($this->size) {
        case 'small':
            $size = 'SC.GIF';
            break;
        case 'medium':
            $size = 'MC.GIF';
            break;
        case 'large':
            $size = 'LC.JPG';
            break;
        }

        $url = isset($this->config->Syndetics->url) ?
                $this->config->Syndetics->url : 'http://syndetics.com';
        $url .= "/index.aspx?type=xw12&isbn={$this->isn}/{$size}&client={$id}";
        return $this->processImageURL($url);
    }

    /**
     * Retrieve a Content Cafe cover.
     *
     * @param string $id Content Cafe client ID.
     *
     * @return bool      True if image displayed, false otherwise.
     */
    protected function contentcafe($id)
    {
        switch ($this->size) {
        case 'small':
            $size = 'S';
            break;
        case 'medium':
            $size = 'M';
            break;
        case 'large':
            $size = 'L';
            break;
        }
        $pw = $this->config->Contentcafe->pw;
        $url = isset($this->config->Contentcafe->url)
            ? $this->config->Contentcafe->url : 'http://contentcafe2.btol.com';
        $url .= "/ContentCafe/Jacket.aspx?UserID={$id}&Password={$pw}&Return=1" .
            "&Type={$size}&Value={$this->isn}&erroroverride=1";
        return $this->processImageURL($url);
    }

    /**
     * Retrieve a LibraryThing cover.
     *
     * @param string $id LibraryThing client ID.
     *
     * @return bool      True if image displayed, false otherwise.
     */
    protected function librarything($id)
    {
        $url = 'http://covers.librarything.com/devkey/' . $id . '/' .
            $this->size . '/isbn/' . $this->isn;
        return $this->processImageURL($url);
    }

    /**
     * Retrieve an OpenLibrary cover.
     *
     * @return bool True if image displayed, false otherwise.
     */
    protected function openlibrary()
    {
        // Convert internal size value to openlibrary equivalent:
        switch ($this->size) {
        case 'large':
            $size = 'L';
            break;
        case 'medium':
            $size = 'M';
            break;
        case 'small':
        default:
            $size = 'S';
            break;
        }

        // Retrieve the image; the default=false parameter indicates that we
        // want a 404 if the ISBN is not supported.
        $url = 'http://covers.openlibrary.org/b/isbn/' . $this->isn .
            "-{$size}.jpg?default=false";
        return $this->processImageURL($url);
    }

    /**
     * Retrieve a Google Books cover.
     *
     * @return bool True if image displayed, false otherwise.
     */
    protected function google()
    {
        // Don't bother trying if we can't read JSON:
        if (is_callable('json_decode')) {
            // Construct the request URL:
            $url = 'http://books.google.com/books?jscmd=viewapi&' .
                   'bibkeys=ISBN:' . $this->isn . '&callback=addTheCover';

            // Make the HTTP request:
            $result = $this->client->setUri($url)->send();

            // Was the request successful?
            if ($result->isSuccess()) {
                // grab the response:
                $json = $result->getBody();

                // extract the useful JSON from the response:
                $count = preg_match('/^[^{]*({.*})[^}]*$/', $json, $matches);
                if ($count < 1) {
                    return false;
                }
                $json = $matches[1];

                // convert \x26 or \u0026 to &
                $json = str_replace(array("\\x26", "\\u0026"), "&", $json);

                // decode the object:
                $json = json_decode($json, true);

                // convert a flat object to an array -- probably unnecessary, but
                // retained just in case the response format changes:
                if (isset($json['thumbnail_url'])) {
                    $json = array($json);
                }

                // find the first thumbnail URL and process it:
                foreach ($json as $current) {
                    if (isset($current['thumbnail_url'])) {
                        return $this->processImageURL(
                            $current['thumbnail_url'], false
                        );
                    }
                }
            }
        }
        return false;
    }

    /**
     * Retrieve an Amazon cover.
     *
     * @param string $id Amazon Web Services client ID.
     *
     * @return bool      True if image displayed, false otherwise.
     */
    protected function amazon($id)
    {
        try {
            $amazon = new Amazon($id, 'US', $this->config->Content->amazonsecret);
            $params = array(
                'ResponseGroup' => 'Images',
                'AssociateTag' => isset($this->config->Content->amazonassociate)
                    ? $this->config->Content->amazonassociate : null
            );
            $result = $amazon->itemLookup($this->isn, $params);
        } catch (\Exception $e) {
            // Something went wrong?  Just report failure:
            return false;
        }

        // Where in the response can we find the URL we need?
        switch ($this->size) {
        case 'small':
            $imageIndex = 'SmallImage';
            break;
        case 'medium':
            $imageIndex = 'MediumImage';
            break;
        case 'large':
            $imageIndex = 'LargeImage';
            break;
        default:
            $imageIndex = false;
            break;
        }

        if ($imageIndex && isset($result->$imageIndex->Url)) {
            $imageUrl = (string)$result->$imageIndex->Url;
            return $this->processImageURL($imageUrl, false);
        }

        return false;
    }

    /**
     * Retrieve a Summon cover.
     *
     * @param string $id Serials Solutions client key.
     *
     * @return bool      True if image displayed, false otherwise.
     */
    protected function summon($id)
    {
        // convert normalized 10 char isn to 13 digits
        $isn = $this->isn;
        if (strlen($isn) != 13) {
            $ISBN = new ISBN($isn);
            $isn = $ISBN->get13();
        }
        $url = 'http://api.summon.serialssolutions.com/image/isbn/' . $id .
            '/' . $isn . '/' . $this->size;
        return $this->processImageURL($url);
    }


    public function booksite($id)
    {
        // convert normalized 10 char isn to 13 digits
        $isn = $this->isn;
        if (strlen($isn) != 13){
          $ISBN = new ISBN($isn);
          $isn = $ISBN->get13();
        }
        $url = isset($this->config->Booksite->url) ?
                     $this->config->Booksite->url  : 'https://api.booksite.com';
        if (! isset($this->config->Booksite->key)) {
            throw new \Exception("Booksite 'key' not set in VuFind config");
        }
        $key = $this->config->Booksite->key;
        $url = $url . '/poca/content_img?key=' . $key . '&ean=' . $isn;
        return $this->processImageURL($url);
    }

}
