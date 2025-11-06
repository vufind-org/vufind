<?php

/**
 * Factory for configurable forms.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2022.
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
 * @package  Config
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Form;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for configurable forms.
 *
 * @category VuFind
 * @package  Config
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FormFactory extends \VuFind\Form\FormFactory
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
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $config = $configManager->getConfigArray('config');

        $form = parent::__invoke($container, $requestedName, $options);
        if (isset($config['Site']['institution'])) {
            $form->setInstitution($config['Site']['institution']);
        }
        if (isset($config['Site']['email'])) {
            $form->setInstitutionEmail($config['Site']['email']);
        }
        if ($user = $container->get(\VuFind\Auth\Manager::class)->getUserObject()) {
            $roles = $container->get(\VuFind\Role\PermissionManager::class)
                ->getActivePermissions();
            try {
                $patron = $container->get(\VuFind\Auth\ILSAuthenticator::class)
                    ->storedCatalogLogin();
            } catch (\Exception $e) {
                $patron = [];
            }
            $form->setUser($user, $roles, $patron ?: []);
        }
        $form->setRecordRequestFormsWithBarcode(
            (array)($config['Record']['repository_library_request_form'] ?? null)
        );
        $form->setDataSourceConfig($configManager->getConfigArray('datasources'));
        $form->setRecordLoader($container->get(\VuFind\Record\Loader::class));
        return $form;
    }
}
