<?php
/**
 * Cover Art Archive cover content loader.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Content\Covers;

/**
 * Cover Art Archive cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CoverArtArchive extends \VuFind\Content\AbstractCover
    implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Recordloader to fetch the current record
     *
     * @var \VuFind\Record\Loader
     */
    protected $recordLoader = null;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Loader $loader Record loader
     */
    public function __construct($loader)
    {
        $this->cacheAllowed = true;
        $this->recordLoader = $loader;
    }

    /**
     * Get image URL for a set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        if (empty($ids['recordid'])) {
            return false;
        }
        if ('medium' === $size) {
            $size = 'small';
        }
        try {
            $driver = $this->recordLoader->load(
                $ids['recordid'],
                $ids['source'] ?? DEFAULT_SEARCH_BACKEND
            );
            $mbids = $driver->tryMethod('getMusicBrainzIdentifiers') ?? [];
            foreach ($mbids as $mbid) {
                $url = 'https://coverartarchive.org/release/' . urlencode($mbid);
                $client = $this->httpService->createClient($url);
                $client->setOptions(['useragent' => 'VuFind']);
                $result = $client->send();
                if ($result->isSuccess()) {
                    $data = json_decode($result->getBody(), true);
                    if (!empty($data['images'])) {
                        foreach ($data['images'] as $image) {
                            if (in_array('Front', $image['types'])) {
                                if (!empty($image['thumbnails'][$size])) {
                                    return $image['thumbnails'][$size];
                                }
                                if (!empty($image['thumbnails']['large'])) {
                                    return $image['thumbnails']['large'];
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        return isset($ids['recordid']);
    }
}
