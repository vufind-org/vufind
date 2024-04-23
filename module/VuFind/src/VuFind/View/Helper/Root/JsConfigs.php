<?php

/**
 * JsConfigs helper for passing configs to Javascript
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Config\Config;
use Laminas\View\Helper\AbstractHelper;
use VuFind\Config\PluginManager;

use function is_array;

/**
 * JsConfigs helper for passing configs to Javascript
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class JsConfigs extends AbstractHelper
{
    /**
     * Config paths
     *
     * @var array
     */
    protected array $configPaths = [];

    /**
     * Constructor
     *
     * @param PluginManager $configLoader Config loader
     */
    public function __construct(
        protected PluginManager $configLoader,
    ) {
    }

    /**
     * Add config paths to the internal array.
     *
     * @param array $new Paths to add
     *
     * @return void
     */
    public function addConfigPaths(array $new): void
    {
        $this->configPaths = array_merge_recursive($this->configPaths, $new);
    }

    /**
     * Generate JSON from the internal arrays
     *
     * @return string
     */
    public function getJSON(): string
    {
        $res = [];
        foreach ($this->configPaths as $configFile => $configPath) {
            $config = $this->configLoader->get($configFile);
            $res[$configFile] = $this->getConfigRecursive($configPath, $config);
        }
        $res = $this->filterNullAndEmpty($res);
        if (empty($res)) {
            return '{}';
        }
        return json_encode($res);
    }

    /**
     * Get config recursive.
     *
     * @param string|array $configPaths Config paths
     * @param ?Config      $config      Config
     *
     * @return array|string|null
     */
    protected function getConfigRecursive(string|array $configPaths, ?Config $config): array|string|null
    {
        $res = [];
        if (!is_array($configPaths)) {
            $res[$configPaths] = $this->convertToJsonSerializable($config?->$configPaths);
        } elseif (array_is_list($configPaths)) {
            foreach ($configPaths as $configPath) {
                $res[$configPath] = $this->convertToJsonSerializable($config?->$configPath);
            }
        } else {
            foreach ($configPaths as $configKey => $configPath) {
                $res[$configKey] = $this->getConfigRecursive($configPath, $config?->$configKey);
            }
        }
        return $this->filterNullAndEmpty($res);
    }

    /**
     * Filter null and empty array.
     *
     * @param array $array Array
     *
     * @return array
     */
    protected function filterNullAndEmpty(array $array): array
    {
        return array_filter($array, function ($value) {
            return $value !== null && $value !== [];
        });
    }

    /**
     * Convert to json serializable.
     *
     * @param null|string|Config $input Input
     *
     * @return null|string|array
     */
    protected function convertToJsonSerializable(null|string|Config $input): null|string|array
    {
        if ($input instanceof Config) {
            return $input->toArray();
        }
        return $input;
    }
}
