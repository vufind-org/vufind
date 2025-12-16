<?php

/**
 * Autowiring factory test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

declare(strict_types=1);

namespace VuFindTest\ServiceManager\Factory\TestHarness;

use Laminas\View\HelperPluginManager;
use VuFind\Auth\Manager;
use VuFind\Config\Config;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Url;

/**
 * Autowiring factory test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AutowiredClass
{
    /**
     * Constructor
     *
     * @param array      $config        Configuration
     * @param array      $configArray   Configuration (same as $config)
     * @param Config     $configObject  Configuration object (same configuration as $config)
     * @param array      $yamlConfig    YAML-based configuration
     * @param Url        $url           URL helper
     * @param Manager    $authManager   Authentication manager
     * @param Connection $ilsConnection ILS Connection
     */
    public function __construct(
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(config: 'config', configType: 'array')]
        protected array $configArray,
        #[Autowire(config: 'config', configType: 'object')]
        protected Config $configObject,
        #[Autowire(config: 'config2', configType: 'yaml')]
        protected array $yamlConfig,
        #[Autowire(container: HelperPluginManager::class)]
        protected Url $url,
        protected Manager $authManager,
        #[Autowire(service: Connection::class)]
        protected $ilsConnection,
    ) {
        if (!($ilsConnection instanceof Connection)) {
            throw new \Exception('Invalid ILS Connection');
        }
        if (!isset($config['Foo'])) {
            throw new \Exception('Invalid configuration');
        }
        if (!isset($configArray['Foo'])) {
            throw new \Exception('Invalid array configuration');
        }
        if (!isset($configObject->Foo)) {
            throw new \Exception('Invalid object configuration');
        }
        if (!isset($yamlConfig['YAML'])) {
            throw new \Exception('Invalid YAML configuration');
        }
    }
}
