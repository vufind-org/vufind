<?php
/**
 * Theme config view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\View\Helper\AbstractHelper;
use VuFindTheme\ThemeInfo;

/**
 * Theme config view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ThemeConfig extends AbstractHelper
{
    /**
     * Cache for merged configs
     *
     * @var StorageInterface
     */
    protected $cache;

    /**
     * ThemeInfo object to access themeConfig
     *
     * @var ThemeInfo
     */
    protected $themeInfo;

    /**
     * Constructor
     *
     * @param ThemeInfo        $themeInfo ThemeInfo
     * @param StorageInterface $cache     StorageInterface
     */
    public function __construct(ThemeInfo $themeInfo, StorageInterface $cache)
    {
        $this->themeInfo = $themeInfo;
        $this->cache = $cache;
    }

    /**
     * Returns config by path
     *
     * Examples:
     * - 'less' => all of less section
     * - ['less'] => same as above
     * - ['less', 'active'] => would return LESS active status
     *
     * @param string|string[] $path Path to return from theme.config.php
     *
     * @return mixed|null
     */
    public function __invoke($path = [])
    {
        // Ensure path is an array
        $path = (array)$path;

        $key = array_shift($path);

        $cacheKey = $this->themeInfo->getTheme() . $key;
        $cached = $this->cache->getItem($cacheKey);

        if ($cached == null) {
            $cached = $this->themeInfo->getMergedConfig($key, true);
            $this->cache->setItem($cacheKey, $cached);
        }

        // Follow the path
        $nextNode = $cached;
        foreach ($path as $p) {
            $nextNode = $nextNode[$p] ?? null;
        }

        return $nextNode;
    }
}
