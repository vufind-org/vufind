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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Config;
use Symfony\Component\Yaml\Yaml;

/**
 * VuFind SearchSpecs Configuration Reader
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SearchSpecsReader
{
    /**
     * Cache manager
     *
     * @var \VuFind\Cache\Manager
     */
    protected $cacheManager;

    /**
     * Cache of loaded search specs.
     *
     * @var array
     */
    protected $searchSpecs = [];

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
            $cache = (null !== $this->cacheManager)
                ? $this->cacheManager->getCache('searchspecs') : false;

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

            // Generate data if not found in cache:
            if ($cache === false || !($results = $cache->getItem($cacheKey))) {
                $results = file_exists($fullpath)
                    ? $this->parseYaml($fullpath) : [];
                if (!empty($local)) {
                    $localResults = $this->parseYaml($local);
                    foreach ($localResults as $key => $value) {
                        $results[$key] = $value;
                    }
                }
                if ($cache !== false) {
                    $cache->setItem($cacheKey, $results);
                }
            }
            $this->searchSpecs[$filename] = $results;
        }

        return $this->searchSpecs[$filename];
    }

    /**
     * Returns content of yaml as an array, considers import of other a parent-yaml-file using "@parent_yaml="
     *
     * @param string $file_contents yaml as a string
     *
     * @return array
     */
    private function parseYaml($filepath)
    {
        $file_contents = file_get_contents($filepath);
        $parentYamlDirectiveString = '@parent_yaml=';
        $parent_directive_startpos = strpos($file_contents, $parentYamlDirectiveString);
        $parent_yaml_array = null;

        if ($parent_directive_startpos !== false) {
            // read parent yaml:
            $parent_directive_endpos = strpos($file_contents, PHP_EOL, $parent_directive_startpos + 1);
            $parent_yaml_filepath =
                substr(
                    $file_contents, $parent_directive_startpos + strlen($parentYamlDirectiveString),
                    $parent_directive_endpos - $parent_directive_startpos - strlen($parentYamlDirectiveString)
                );
            $parent_yaml_filepath = pathinfo($filepath)[dirname] . "/" .  $parent_yaml_filepath;
            $parent_yaml_array = Yaml::parse(file_get_contents($parent_yaml_filepath));

            //remove include-directive from yaml:
            $file_contents =
                substr($file_contents, 0, $parent_directive_startpos) . substr(
                    $file_contents,
                    $parent_directive_endpos + 1
                );
        }

        $yaml_array = Yaml::parse($file_contents);
        if ($parent_yaml_array !== null) {
            // overwrite parent_yaml_array with yaml_array
            $yaml_array = array_replace_recursive($parent_yaml_array, $yaml_array);
        }
        return $yaml_array;
    }

}
