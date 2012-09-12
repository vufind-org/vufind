<?php
/**
 * VuFind SearchSpecs Configuration Reader
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Config;
use Horde_Yaml as Yaml,
    VuFind\Cache\Manager as CacheManager;

/**
 * VuFind SearchSpecs Configuration Reader
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SearchSpecsReader
{
    /**
     * Cache of loaded search specs.
     *
     * @var array
     */
    protected $searchSpecs = array();

    /**
     * Return search specs
     *
     * @param string $filename config file name
     *
     * @return array
     */
    public function get($filename)
    {
        // Load data if it is not already in the object's cache:
        if (!isset($this->searchSpecs[$filename])) {
            // Connect to searchspecs cache:
            $cache = CacheManager::getInstance()->getCache('searchspecs');

            // Determine full configuration file path:
            $fullpath = Reader::getBaseConfigPath($filename);
            $local = Reader::getLocalConfigPath($filename);

            // Generate cache key:
            $key = $filename . '-' . filemtime($fullpath);
            if (!empty($local)) {
                $key .= '-local-' . filemtime($local);
            }
            $key = md5($key);

            // Generate data if not found in cache:
            if (!$cache || !($results = $cache->getItem($key))) {
                $results = Yaml::load(file_get_contents($fullpath));
                if (!empty($local)) {
                    $localResults = Yaml::load(file_get_contents($local));
                    foreach ($localResults as $key => $value) {
                        $results[$key] = $value;
                    }
                }
                if ($cache) {
                    $cache->setItem($key, $results);
                }
            }
            $this->searchSpecs[$filename] = $results;
        }

        return $this->searchSpecs[$filename];
    }
}