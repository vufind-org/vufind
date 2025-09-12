<?php

/**
 * ConfigManager Integration Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFindTest\Integration\ConfigTestCase;

/**
 * ConfigManager Integration Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConfigManagerIntegrationTest extends ConfigTestCase
{
    /**
     * Test simple caching.
     *
     * @return void
     */
    public function testSimpleCaching(): void
    {
        $container = $this->getContainerWithConfigRelatedServices();
        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $this->setUpLocalConfigDir('defaultgenerator');
        $config = $configManager->getConfig('config');
        $this->assertEquals('VuFind 1.0', $config['Site']['generator']);
        // check that the cache is used and the change in the config files is ignored
        $this->setUpLocalConfigDir('customgenerator');
        $config = $configManager->getConfig('config');
        $this->assertEquals('VuFind 1.0', $config['Site']['generator']);
        // check that the cache is ignored
        $config = $configManager->getConfig('config', forceReload: true);
        $this->assertEquals('Custom Generator', $config['Site']['generator']);
    }

    /**
     * Test that the config PluginManager caching is disabled.
     *
     * @return void
     */
    public function testDisabledPluginManagerCaching(): void
    {
        $container = $this->getContainerWithConfigRelatedServices();
        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $pluginManager = $container->get(\VuFind\Config\PluginManager::class);
        $this->setUpLocalConfigDir('defaultgenerator');
        $config = $pluginManager->get('config')->toArray();
        $this->assertEquals('VuFind 1.0', $config['Site']['generator']);
        // check that the cache is used and the change in the config files is ignored
        $this->setUpLocalConfigDir('customgenerator');
        $config = $pluginManager->get('config')->toArray();
        $this->assertEquals('VuFind 1.0', $config['Site']['generator']);
        // check that the plugin manager cache is ignored
        $configManager->getConfig('config', forceReload: true);
        $config = $pluginManager->get('config')->toArray();
        $this->assertEquals('Custom Generator', $config['Site']['generator']);
    }

    /**
     * Test the userLocalConfig parameter.
     *
     * @return void
     */
    public function testUseLocalConfigParameter(): void
    {
        $container = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/defaultgenerator',
            baseSubDir: ''
        );
        $this->setUpLocalConfigDir('customgenerator');
        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $config = $configManager->getConfig('config');
        $this->assertEquals('Custom Generator', $config['Site']['generator']);
        $config = $configManager->getConfig('config', useLocalConfig: false);
        $this->assertEquals('VuFind 1.0', $config['Site']['generator']);
    }
}
