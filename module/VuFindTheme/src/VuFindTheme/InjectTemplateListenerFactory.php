<?php
/**
 * Factory for InjectTemplateListener
 *
 * PHP version 7
 *
 * Copyright (C) 2019 Leipzig University Library
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @category VuFind
 * @package  Theme
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org Main Site
 */
namespace VuFindTheme;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Factory for InjectTemplateListener
 *
 * @category VuFind
 * @package  Theme
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU GPLv2
 * @link     https://vufind.org Main Site
 */
class InjectTemplateListenerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $config = $container->get('config');
        $prefixes = $config['vufind']['extra_theme_prefixes'] ?? [];
        $exclude = $config['vufind']['excluded_theme_prefixes'] ?? [];

        // we assume that - by default - modules try loading templates from
        // their own namespace, thus all loaded modules are included for inflection
        $modules = array_map(
            function ($module) {
                return str_replace('\\', '/', $module) . '/';
            },
            $container->get('ModuleManager')->getModules()
        );
        $prefixes = array_filter(
            array_merge($prefixes, $modules),
            function ($prefix) use ($exclude) {
                foreach ($exclude as $current) {
                    if (strpos($prefix, $current) === 0) {
                        return false;
                    }
                }
                return true;
            }
        );

        $this->loadConfiguredJavascriptFilesFromMixin($config, $container);

        return new $requestedName(array_unique($prefixes));
    }

    /**
     * Load configured javascript files from mixin.config.php if existing
     *
     * @param array              $config    main config
     * @param ContainerInterface $container Service manager
     *
     * @return void
     */
    protected function loadConfiguredJavascriptFilesFromMixin(
        array $config,
        ContainerInterface $container
    ): void {
        $templatePathStack = $config['view_manager']['template_path_stack'] ?? false;
        if ($templatePathStack) {
            foreach ($templatePathStack as $templatePath) {
                if (file_exists($mixin = $templatePath . '/../mixin.config.php')) {
                    $resourceContainer = $container
                        ->get(\VuFindTheme\ResourceContainer::class);
                    $resources = include $mixin;
                    foreach ($resources as $resourceType => $resourceFiles) {
                        switch ($resourceType) {
                        case 'js':
                            foreach ($resourceFiles as $file) {
                                $resourceContainer->addJs(
                                    dirname($mixin) . "/$resourceType/$file"
                                );
                            }
                        }
                    }
                }
            }
        }
    }
}
