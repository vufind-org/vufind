<?php
/**
 * Cover image router
 *
 * PHP version 7
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
     * @return string|bool
     */
    public function getUrl(RecordDriver $driver, $size = 'small',
        $resolveDynamic = true, $testLoadImage = false
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
            return $thumb;
        }

        $settings = is_array($thumb) ? array_merge($thumb, ['size' => $size])
            : ['size' => $size];
        $handlers = $this->coverLoader->getHandlers();
        $ids = $this->coverLoader->getIdentifiersForSettings($settings);

        foreach ($handlers as $handler) {
            try {
                // Is the current provider appropriate for the available data?
                if ($handler['handler']->supports($ids)
                    && $handler['handler']->useDirectUrls()
                ) {
                    $nextDirectUrl = $handler['handler']
                        ->getUrl($handler['key'], $size, $ids);
                    if ($nextDirectUrl !== false) {
                        $directUrl = $nextDirectUrl;
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->debug(
                    get_class($e) . ' during processing of '
                    . get_class($handler['handler']) . ': ' . $e->getMessage()
                );
            }
        }

        if (isset($directUrl)) {
            return $directUrl;
        } elseif (isset($dynamicUrl)) {
            if ($testLoadImage) {
                $this->coverLoader->loadImage($settings);
                if ($this->coverLoader->hasLoadedUnavailable()) {
                    return false;
                }
            }
            return $dynamicUrl;
        }

        return false;
    }
}
