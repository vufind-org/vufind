<?php
    /**
     * Factory for broadcasts form
     *
     * PHP version 8
     *
     * Copyright (C) effective WEBWORK GmbH 2023.
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
     * @package  Db_Table
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Site
     */
    namespace VuFind\Form;

    use Interop\Container\ContainerInterface;
    use Laminas\Mvc\I18n\Translator;
    use Laminas\ServiceManager\Factory\FactoryInterface;

    /**
     * Factory for broadcasts form
     *
     * @category VuFind
     * @package  Db_Table
     * @author   Demian Katz <demian.katz@villanova.edu>
     * @author   Johannes Schultze <schultze@effective-webwork.de>
     * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
     * @link     https://vufind.org Main Site
     */
    class BroadcastsFormFactory implements FactoryInterface
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
         *
         * @SuppressWarnings(PHPMD.UnusedFormalParameter)
         */
        public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
        {
            $translator = $container->get(Translator::class);

            return new BroadcastsForm(
                $translator,
                $container->get(\VuFind\Config\YamlReader::class)->get('Notifications.yaml')
            );
        }
    }