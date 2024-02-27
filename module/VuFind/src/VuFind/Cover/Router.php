<?php

/**
 * Cover image router
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */

namespace VuFind\Cover;

use VuFind\Cover\Loader as CoverLoader;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function get_class;
use function is_array;

/**
 * Cover image router
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
class Router implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base URL for dynamic cover images.
     *
     * @var string
     */
    protected $dynamicUrl;

    /**
     * Cover loader
     *
     * @var CoverLoader
     */
    protected $coverLoader;

    /**
     * Constructor
     *
     * @param string      $url         Base URL for dynamic cover images.
     * @param CoverLoader $coverLoader Cover loader
     */
    public function __construct($url, CoverLoader $coverLoader)
    {
        $this->dynamicUrl = $url;
        $this->coverLoader = $coverLoader;
    }

    /**
     * Generate a thumbnail URL (return false if unsupported; return null to indicate
     * that a subsequent AJAX check is needed).
     *
     * @param RecordDriver $driver         Record driver
     * @param string       $size           Size of thumbnail (small, medium or large;
     * small is default).
     * @param bool         $resolveDynamic Should we resolve dynamic cover data into
     * a URL (true) or simply return false (false)?
     * @param bool         $testLoadImage  If true the function will try to load the
     * cover image in advance and returns false in case no image could be loaded
     *
     * @return string|false|null
     */
    public function getUrl(
        RecordDriver $driver,
        $size = 'small',
        $resolveDynamic = true,
        $testLoadImage = false
    ) {
        $metadata = $this->getMetadata(
            $driver,
            $size,
            $resolveDynamic,
            $testLoadImage
        );
        // getMetadata could return null or false, that is the reason we are
        // respecting the returned value - in case it is not empty array to be on
        // safe side and not return bad type here
        return $metadata['url'] ?? (!is_array($metadata) ? $metadata : false);
    }

    /**
     * Generate a thumbnail metadata (return false if unsupported; return null to
     * indicate that a subsequent AJAX check is needed).
     *
     * @param RecordDriver $driver         Record driver
     * @param string       $size           Size of thumbnail (small, medium or large;
     * small is default).
     * @param bool         $resolveDynamic Should we resolve dynamic cover data into
     * a URL (true) or simply return false (false)?
     * @param bool         $testLoadImage  If true the function will try to load the
     * cover image in advance and returns false in case no image could be loaded
     * @param bool         $ajax           True if the function is called from ajax
     * handler
     *
     * @return false|array|null
     */
    public function getMetadata(
        RecordDriver $driver,
        $size = 'small',
        $resolveDynamic = true,
        $testLoadImage = false,
        $ajax = false
    ) {
        // Try to build thumbnail:
        $thumb = $driver->tryMethod('getThumbnail', [$size]);

        // No thumbnail?  Return false:
        if (empty($thumb)) {
            return false;
        }

        // Array? It's parameters to send to the cover generator:
        if (is_array($thumb)) {
            if (!$resolveDynamic) {
                return null;
            }
            $dynamicUrl =  $this->dynamicUrl . '?' . http_build_query($thumb);
        } else {
            return ['url' => $thumb];
        }

        $settings = is_array($thumb) ? array_merge($thumb, ['size' => $size])
            : ['size' => $size];
        $handlers = $this->coverLoader->getHandlers();
        $ids = $this->coverLoader->getIdentifiersForSettings($settings);
        foreach ($handlers as $handler) {
            $backlinkLocations
                = $handler['handler']->getMandatoryBacklinkLocations();
            if (!empty($backlinkLocations) && !$ajax) {
                $this->logWarning(
                    'Cover provider ' . get_class($handler['handler'])
                    . ' needs ajaxcovers setting to be on'
                );
                continue;
            }
            try {
                // Is the current provider appropriate for the available data?
                if (
                    $handler['handler']->supports($ids)
                    && $handler['handler']->useDirectUrls()
                ) {
                    $nextMetadata = $handler['handler']
                        ->getMetadata($handler['key'], $size, $ids);
                    if (!empty($nextMetadata)) {
                        $nextMetadata['backlink_locations'] = $backlinkLocations;
                        $metadata = $nextMetadata;
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->debug(
                    $e::class . ' during processing of '
                    . get_class($handler['handler']) . ': ' . $e->getMessage()
                );
            }
        }

        if (isset($metadata)) {
            return $metadata;
        }
        if ($testLoadImage) {
            $this->coverLoader->loadImage($settings);
            if ($this->coverLoader->hasLoadedUnavailable()) {
                return false;
            }
        }
        return ['url' => $dynamicUrl];
    }
}
