<?php
/**
 * VuFind Config Plugin Factory
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Config;
use Zend\Config\Config, Zend\Config\Reader\Ini as IniReader,
    Zend\ServiceManager\AbstractFactoryInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * VuFind Config Plugin Factory
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
     * @param string $filename config file name
     * @param string $path     path relative to VuFind base (optional; defaults
     * to config/vufind
     *
     * @return Config
     */
    protected function loadConfigFile($filename, $path = 'config/vufind')
    {
        $configs = [];

        $fullpath = Locator::getConfigPath($filename, $path);

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
        while (!is_null($child = array_pop($configs))) {
            $overrideSections = isset($child->Parent_Config->override_full_sections)
                ? explode(
                    ',', str_replace(
                        ' ', '', $child->Parent_Config->override_full_sections
                    )
                )
                : [];
            foreach ($child as $section => $contents) {
                if (in_array($section, $overrideSections)
                    || !isset($config->$section)
                ) {
                    $config->$section = $child->$section;
                } else {
                    foreach (array_keys($contents->toArray()) as $key) {
                        $config->$section->$key = $child->$section->$key;
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
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        // Assume that configurations exist:
        return true;
    }

    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return object
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,
        $name, $requestedName
    ) {
        return $this->loadConfigFile($requestedName . '.ini');
    }
}
