<?php
/**
 * VuFind I18n Locale Settings Factory
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Locale;

use Interop\Container\ContainerInterface;
use VuFind\Config\PluginManager as ConfigManager;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Creates a Locale settings instance.
 *
 * @category VuFind
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SettingsFactory implements FactoryInterface
{
    /**
     * Creates an initializer instance.
     *
     * @param ContainerInterface $container Container
     * @param string $requestedName Requested name
     * @param array|null $options Options
     *
     * @return Settings
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new $requestedName($container->get(ConfigManager::class)->get('config'));
    }
}
