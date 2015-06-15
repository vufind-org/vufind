<?php
/**
 * VuFind OpenUrlRules Reader
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010, Leipzig University Library 2015.
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
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Config;
use Zend\Config\Reader\Json as Json;

/**
 * VuFind OpenUrlRules Reader
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class OpenUrlRulesReader
{
    /**
     * Cache manager
     *
     * @var \VuFind\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Cache of loaded OpenURL rules.
     *
     * @var array
     */
    protected $openUrlRules = [];

    /**
     * Constructor
     *
     * @param \VuFind\Cache\Manager $cacheManager Cache manager (optional)
     */
    public function __construct(\VuFind\Cache\Manager $cacheManager = null)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Return OpenURL rules
     *
     * @param string $filename config file name
     *
     * @return array
     */
    public function get($filename)
    {
        // Load data if it is not already in the object's cache:
        if (!isset($this->openUrlRules[$filename])) {
            // Connect to openurlrules cache:
            $cache = (null !== $this->cacheManager)
                ? $this->cacheManager->getCache('config') : false;

            // Determine full configuration file path:
            $fullpath = Locator::getBaseConfigPath($filename);
            $local = Locator::getLocalConfigPath($filename);

            // Generate cache key:
            $cacheKey = $filename . '-'
                . (file_exists($fullpath) ? filemtime($fullpath) : 0);
            if (!empty($local)) {
                $cacheKey .= '-local-' . filemtime($local);
            }
            $cacheKey = md5($cacheKey);

            $jsonReader = new Json();

            // Generate data if not found in cache:
            if ($cache === false || !($results = $cache->getItem($cacheKey))) {
                $results = file_exists($fullpath)
                    ? $jsonReader->fromFile($fullpath)
                    : [];
                if (!empty($local)) {
                    $localResults = $jsonReader->fromFile($local);
                    foreach ($localResults as $key => $value) {
                        $results[$key] = $value;
                    }
                }
                if ($cache !== false) {
                    $cache->setItem($cacheKey, $results);
                }
            }
            $this->openUrlRules[$filename] = $results;
        }

        return $this->openUrlRules[$filename];
    }
}