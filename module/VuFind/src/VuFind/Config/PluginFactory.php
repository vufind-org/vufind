<?php
/**
 * VuFind Config Plugin Factory
 *
 * PHP version 7
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config;

use Laminas\Config\Config;
use Laminas\Config\Reader\Ini as IniReader;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * VuFind Config Plugin Factory
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginFactory implements AbstractFactoryInterface
{
    /**
     * INI file reader
     *
     * @var IniReader
     */
    protected $iniReader;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Use ASCII 0 as a nest separator; otherwise some of the unusual key names
        // we have (i.e. in WorldCat.ini search options) will get parsed in
        // unexpected ways.
        $this->iniReader = new IniReader();
        $this->iniReader->setNestSeparator(chr(0));
    }

    /**
     * Load the specified configuration file.
     *
     * @param string   $filename     Config file name
     * @param ?string  $path         Path relative to VuFind base (optional; use null
     * for default)
     * @param callable $pathCallback Callback for getting an absolute config
     * file path (optional; defaults to Locator::getConfigPath)
     *
     * @return Config
     */
    protected function loadConfigFile(
        $filename,
        $path = null,
        callable $pathCallback = null
    ) {
        $configs = [];

        $fullpath
            = ($pathCallback ?? [Locator::class, 'getConfigPath'])($filename, $path);

        // Return empty configuration if file does not exist:
        if (!file_exists($fullpath)) {
            return new Config([]);
        }

        // Retrieve and parse at least one configuration file, and possibly a whole
        // chain of them if the Parent_Config setting is used:
        do {
            $configs[]
                = new Config($this->iniReader->fromFile($fullpath), true);

            $i = count($configs) - 1;
            if (isset($configs[$i]->Parent_Config->path)) {
                $fullpath = $configs[$i]->Parent_Config->path;
            } elseif (isset($configs[$i]->Parent_Config->relative_path)) {
                $fullpath = pathinfo($fullpath, PATHINFO_DIRNAME)
                    . DIRECTORY_SEPARATOR
                    . $configs[$i]->Parent_Config->relative_path;
            } else {
                $fullpath = false;
            }
        } while ($fullpath);

        // The last element in the array will be the top of the inheritance tree.
        // Let's establish a baseline:
        $config = array_pop($configs);

        // Now we'll pull all the children down one at a time and override settings
        // as appropriate:
        while (null !== ($child = array_pop($configs))) {
            $overrideSections = isset($child->Parent_Config->override_full_sections)
                ? explode(
                    ',',
                    str_replace(
                        ' ',
                        '',
                        $child->Parent_Config->override_full_sections
                    )
                )
                : [];
            foreach ($child as $section => $contents) {
                // Check if arrays in the current config file should be merged with
                // preceding arrays from config files defined as Parent_Config.
                $mergeArraySettings
                    = !empty($child->Parent_Config->merge_array_settings);

                // Omit Parent_Config from the returned configuration; it is only
                // needed during loading, and its presence will cause problems in
                // config files that iterate through all of the sections (e.g.
                // combined.ini, permissions.ini).
                if ($section === 'Parent_Config') {
                    continue;
                }
                if (in_array($section, $overrideSections)
                    || !isset($config->$section)
                ) {
                    $config->$section = $child->$section;
                } else {
                    foreach (array_keys($contents->toArray()) as $key) {
                        // If a key is defined as key[] in the config file the key
                        // remains a Laminas\Config\Config object. If the current
                        // section is not configured as an override section we try to
                        // merge the key[] values instead of overwriting them.
                        if (is_object($config->$section->$key)
                            && is_object($child->$section->$key)
                            && $mergeArraySettings
                        ) {
                            $config->$section->$key = array_merge(
                                $config->$section->$key->toArray(),
                                $child->$section->$key->toArray()
                            );
                        } else {
                            $config->$section->$key = $child->$section->$key;
                        }
                    }
                }
            }
        }

        $config->setReadOnly();
        return $config;
    }

    /**
     * Can we create a service for the specified name?
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        // Assume that configurations exist:
        return true;
    }

    /**
     * Create a service for the specified name.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Name of service
     * @param array              $options       Options (unused)
     *
     * @return object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        return $this->loadConfigFile(
            $requestedName . '.ini',
            null,
            [$container->get(PathResolver::class), 'getConfigPath']
        );
    }
}
