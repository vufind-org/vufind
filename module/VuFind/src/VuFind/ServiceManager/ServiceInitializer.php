<?php

/**
 * VuFind Service Initializer
 *
 * PHP version 8
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

namespace VuFind\ServiceManager;

use Laminas\ServiceManager\Initializer\InitializerInterface;
use Psr\Container\ContainerInterface;

/**
 * VuFind Service Initializer
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ServiceInitializer implements InitializerInterface
{
    /**
     * Check if the record cache is enabled within a service manager.
     *
     * @param ContainerInterface $sm Service manager
     *
     * @return bool
     */
    protected function isCacheEnabled(ContainerInterface $sm)
    {
        // Use static cache to save time on repeated lookups:
        static $enabled = null;
        if (null === $enabled) {
            // Return true if Record Cache is enabled for any data source
            $cacheConfig = $sm->get(\VuFind\Config\PluginManager::class)
                ->get('RecordCache');
            $enabled = false;
            foreach ($cacheConfig as $section) {
                foreach ($section as $setting) {
                    if (
                        isset($setting['operatingMode'])
                        && $setting['operatingMode'] !== 'disabled'
                    ) {
                        $enabled = true;
                        break 2;    // quit looping -- we know the answer!
                    }
                }
            }
        }
        return $enabled;
    }

    /**
     * Given an instance and a Service Manager, initialize the instance.
     *
     * @param ContainerInterface $sm       Service manager
     * @param object             $instance Instance to initialize
     *
     * @return object
     */
    public function __invoke(ContainerInterface $sm, $instance)
    {
        if ($instance instanceof \VuFind\Db\Table\DbTableAwareInterface) {
            $instance->setDbTableManager(
                $sm->get(\VuFind\Db\Table\PluginManager::class)
            );
        }
        if ($instance instanceof \VuFind\Db\Service\DbServiceAwareInterface) {
            $instance->setDbServiceManager(
                $sm->get(\VuFind\Db\Service\PluginManager::class)
            );
        }
        if ($instance instanceof \Laminas\Log\LoggerAwareInterface) {
            $instance->setLogger($sm->get(\VuFind\Log\Logger::class));
        }
        if ($instance instanceof \VuFind\I18n\Translator\TranslatorAwareInterface) {
            $instance->setTranslator($sm->get(\Laminas\Mvc\I18n\Translator::class));
        }
        if ($instance instanceof \VuFindHttp\HttpServiceAwareInterface) {
            $instance->setHttpService($sm->get(\VuFindHttp\HttpService::class));
        }
        // Only inject cache if configuration enabled (to save resources):
        if (
            $instance instanceof \VuFind\Record\Cache\RecordCacheAwareInterface
            && $this->isCacheEnabled($sm)
        ) {
            $instance->setRecordCache($sm->get(\VuFind\Record\Cache::class));
        }
        return $instance;
    }
}
