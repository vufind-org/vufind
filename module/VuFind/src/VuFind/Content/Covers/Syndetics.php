<?php

/**
 * Syndetics cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use DOMDocument;

/**
 * Syndetics cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Syndetics extends \VuFind\Content\AbstractCover implements \VuFind\Http\CachingDownloaderAwareInterface
{
    use \VuFind\Http\CachingDownloaderAwareTrait;

    /**
     * Use SSL URLs?
     *
     * @var bool
     */
    protected $useSSL;

    /**
     * Use Syndetics image fallback ?
     *
     * @var bool
     */
    protected $useSyndeticsCoverImageFallback;

    /**
     * Constructor
     *
     * @param ?\Laminas\Config\Config $config Syndetics configuration
     */
    public function __construct(?\Laminas\Config\Config $config = null)
    {
        $this->useSSL = $config->use_ssl ?? false;
        $this->useSyndeticsCoverImageFallback = $config->use_syndetics_cover_image_fallback ?? false;
        $this->supportsIsbn = $this->supportsIssn = $this->supportsOclc
            = $this->supportsUpc = $this->cacheAllowed = true;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        $baseUrl = $this->getBaseUrl($key, $ids);
        if ($baseUrl == false) {
            return false;
        }
        if ($this->useSyndeticsCoverImageFallback) {
            $filename = $this->getImageFilenameFromSize($size);
            if ($filename == false) {
                return false;
            }
        } else {
            $xmldoc = $this->getMetadataXML($baseUrl);
            if ($xmldoc == false) {
                return false;
            }
            $filename = $this->getImageFilenameFromMetadata($xmldoc, $size);
            if ($filename == false) {
                return false;
            }
        }
        return $this->getImageUrl($baseUrl, $filename);
    }

    /**
     * Return the base Syndetics URL for both the metadata and image URLs.
     *
     * @param string $key API key
     * @param array  $ids Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool Base URL, or false if no identifier can be used
     */
    protected function getBaseUrl($key, $ids)
    {
        $url = $this->useSSL
            ? 'https://secure.syndetics.com' : 'http://syndetics.com';
        $url .= "/index.aspx?client={$key}";
        $ident = '';
        if (isset($ids['isbn']) && $ids['isbn']->isValid()) {
            $isbn = $ids['isbn']->get13();
            $ident .= "&isbn={$isbn}";
        }
        if (isset($ids['issn'])) {
            $ident .= "&issn={$ids['issn']}";
        }
        if (isset($ids['oclc'])) {
            $ident .= "&oclc={$ids['oclc']}";
        }
        if (isset($ids['upc'])) {
            $ident .= "&upc={$ids['upc']}";
        }
        if (empty($ident)) {
            return false;
        }
        return $url . $ident;
    }

    /**
     * Calculate the image filename based on the size, without checking if it exists in the metadata.
     *
     * @param string $size Size of image to load (small/medium/large)
     *
     * @return string|bool Image filename, or false if the size is not 'small', 'medium' or 'large'
     */
    protected function getImageFilenameFromSize($size)
    {
        return match ($size) {
            'small' => 'SC.GIF',
            'medium' => 'MC.GIF',
            'large' => 'LC.JPG',
            default => false,
        };
    }

    /**
     * Get the Syndetics metadata as XML, using a cache.
     *
     * @param $baseUrl string  Base URL for the Syndetics query
     *
     * @return DOMDocument|bool The metadata as a DOM XML document, or false if the document cannot be parsed.
     */
    protected function getMetadataXML($baseUrl)
    {
        $url = $baseUrl . '/index.xml';
        if (!isset($this->cachingDownloader)) {
            throw new \Exception('CachingDownloader initialization failed.');
        }
        $body = $this->cachingDownloader->download($url);
        $dom = new DOMDocument();
        return $dom->loadXML($body) ? $dom : false;
    }

    /**
     * Find the image filename in the XML returned from API.
     *
     * @param DOMDocument $xmldoc Parsed XML document
     * @param string      $size   Size of image to load (small/medium/large)
     *
     * @return string|bool Image filename, or false if none matches
     */
    protected function getImageFilenameFromMetadata($xmldoc, $size)
    {
        $elementName = match ($size) {
            'small' => 'SC',
            'medium' => 'MC',
            'large' => 'LC',
            default => false,
        };
        if ($elementName == false) {
            return false;
        }
        $nodes = $xmldoc->getElementsByTagName($elementName);
        if ($nodes->length == 0) {
            return false;
        }
        return $nodes->item(0)->nodeValue;
    }

    /**
     * Return the full image url.
     *
     * @param $baseUrl  string  Base URL for the Syndetics query
     * @param $filename string  Image filename
     *
     * @return string Full url of the image
     */
    protected function getImageUrl($baseUrl, $filename)
    {
        return $baseUrl . "/{$filename}";
    }
}
