<?php
/**
 * Record image loader
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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

use VuFindCode\ISBN;

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
    protected $invalidIsbn;

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
        if (!$this->fetchFromAPI()
            && !$this->fetchFromContentType()
        ) {
            if (isset($this->config->Content->makeDynamicCovers)
                && $this->config->Content->makeDynamicCovers
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
     * Loads an external image from provider and sends it to browser
     * in chunks. Used for big image files
     *
     * @param string $url      to load
     * @param string $format   type of the image to load
     * @param string $filename filename for the downloaded image
     *
     * @return bool
     */
    public function loadExternalImage($url, $format, $filename)
    {
        $contentType = '';
        switch ($format) {
        case 'tif':
        case 'tiff':
            $contentType = 'image/tiff';
            break;
        default:
            $contentType = 'image/jpeg';
            break;
        }
        header("Content-Type: $contentType");
        header("Content-disposition: attachment; filename=\"{$filename}\"");
        $client = $this->httpService->createClient(
            $url, \Zend\Http\Request::METHOD_GET, 300
        );
        $client->setStream();
        $adapter = new \Zend\Http\Client\Adapter\Curl();
        $client->setAdapter($adapter);
        $adapter->setOptions(
            [
                'curloptions' => [
                    CURLOPT_WRITEFUNCTION => function ($ch, $str) {
                        echo $str;
                        return strlen($str);
                    }
                ]
            ]
        );
        $result = $client->send();

        if (!$result->isSuccess()) {
            $this->debug("Failed to retrieve image from $url");
            return false;
        }

        return true;
    }

    /**
     * Load a record image.
     *
     * @param \Vufind\RecordDriver\SolrDefault $driver Record
     * @param int                              $index  Image index
     * @param string                           $size   Requested size
     *
     * @return void
     */
    public function loadRecordImage(
        \VuFind\RecordDriver\SolrDefault $driver, $index, $size
    ) {
        $this->index = $index;

        $params = $driver->getRecordImage($size, $index);

        if (isset($params['url'])) {
            $this->id = $driver->getUniqueID();
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
        }

        $identifiers = parent::getIdentifiers();
        if ($this->invalidIsbn) {
            $identifiers['invalid_isbn'] = $this->invalidIsbn;
        }
        return $identifiers;
    }

    /**
     * Support method for fetchFromAPI() -- set the localFile property.
     *
     * @param array  $ids     IDs returned by getIdentifiers() method
     * @param string $apiName Name of the API
     *
     * @return void
     */
    protected function determineLocalFile($ids, $apiName = 'default')
    {
        $keys = [];

        if (isset($this->url)) {
            $keys['url'] = md5($this->url);
            $host = parse_url($this->url, PHP_URL_HOST);
            $keys['host'] = substr($host, 0, 100);
        } else {
            if (isset($ids['isbn'])) {
                $keys['isbn'] = $ids['isbn']->get13();
            } elseif (isset($ids['issn'])) {
                $keys['issn'] = $ids['issn'];
            } elseif (isset($ids['oclc'])) {
                $keys['oclc'] = $ids['oclc'];
            } elseif (isset($ids['upc'])) {
                $keys['upc'] = $ids['upc'];
            } elseif (isset($ids['invalid_isbn'])) {
                $keys['invalid_isbn'] = $ids['invalid_isbn'];
            }
        }

        if (empty($keys)) {
            if (isset($ids['recordid'])) {
                $keys['recordid'] = $ids['recordid'];
            }
            if (isset($ids['source'])) {
                $keys['source'] = $ids['source'];
            }
        }

        $keys = array_merge(
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
        $datasourceProviders = isset($this->datasourceCoverConfig)
            ? explode(',', $this->datasourceCoverConfig) : [];
        $commonProviders = isset($this->config->Content->coverimages)
            ? explode(',', $this->config->Content->coverimages) : [];
        $providers = array_merge($datasourceProviders, $commonProviders);

        // Try to find provider-specific cache file
        foreach ($providers as $provider) {
            $provider = explode(':', trim($provider));
            $apiName = strtolower(trim($provider[0]));
            $localFile = $this->determineLocalFile($ids, $apiName);

            if (is_readable($localFile)) {
                // Load local cache if available
                $this->contentType = 'image/jpeg';
                $this->image = file_get_contents($localFile);
                return true;
            }
        }
        // Try to fetch from providers
        foreach ($providers as $provider) {
            $provider = explode(':', trim($provider));
            $apiName = strtolower(trim($provider[0]));
            // Set up local file path:
            $this->localFile = $this->determineLocalFile($ids, $apiName);
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

        $url = str_replace(
            [' ', 'ä','ö','å','Ä','Ö','Å'],
            ['%20','%C3%A4','%C3%B6','%C3%A5','%C3%84','%C3%96','%C3%85'],
            trim($url)
        );

        // Figure out file paths -- $tempFile will be used to store the
        // image for analysis.  $finalFile will be used for long-term storage if
        // $cache is true or for temporary display purposes if $cache is false.
        $tempFile = str_replace('.jpg', uniqid(), $this->localFile);
        $finalFile = $cache ? $this->localFile : $tempFile . '.jpg';

        $pdfFile = preg_match('/\.pdf$/i', $url);
        $convertPdfService
            = $this->config->Content->convertPdfToCoverImageService
            ?? false;

        if ($pdfFile && !$convertPdfService) {
            return false;
        }

        if ($pdfFile) {
            // Convert pdf to jpg
            $url = "$convertPdfService?url=" . urlencode($url);
        }

        // Attempt to pull down the image:
        $client = $this->httpService->createClient(
            $url, \Zend\Http\Request::METHOD_GET, 20
        );
        $client->setStream($tempFile);
        $result = $client->send();

        if (!$result->isSuccess()) {
            $this->debug("Failed to retrieve image from $url");
            return false;
        }

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

        list($width, $height, $type) = @getimagesizefromstring($image);

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
                $imageGDResized, $imageGD, 0, 0, 0, 0,
                $newWidth, $newHeight, $width, $height
            );
            if (!@imagejpeg($imageGDResized, $finalFile, $quality)) {
                return false;
            }
        } else {
            if ($type !== IMG_JPG) {
                if (!@imagejpeg($imageGD, $finalFile, $quality)) {
                    return false;
                }
            } else {
                file_put_contents($finalFile, $image);
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
        $this->invalidIsbn = $settings['invalid_isbn'];
    }
}
