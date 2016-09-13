<?php
/**
 * Cover image router
 *
 * PHP version 5
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
class Router
{
    /**
     * Base URL for dynamic cover images.
     *
     * @var string
     */
    protected $dynamicUrl;

    /**
     * Constructor
     *
     * @param string $url Base URL for dynamic cover images.
     */
    public function __construct($url)
    {
        $this->dynamicUrl = $url;
    }

    /**
     * Generate a thumbnail URL (return false if unsupported).
     *
     * @param RecordDriver $driver Record driver
     * @param string       $size   Size of thumbnail (small, medium or large --
     * small is default).
     *
     * @return string|bool
     */
    public function getUrl(RecordDriver $driver, $size = 'small')
    {
        // Try to build thumbnail:
        $thumb = $driver->tryMethod('getThumbnail', [$size]);

        // No thumbnail?  Return false:
        if (empty($thumb)) {
            return false;
        }

        // Array?  It's parameters to send to the cover generator:
        if (is_array($thumb)) {
            return $this->dynamicUrl . '?' . http_build_query($thumb);
        }

        // Default case -- return fixed string:
        return $thumb;
    }
}
