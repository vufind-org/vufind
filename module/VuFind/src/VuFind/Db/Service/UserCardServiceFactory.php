<?php

/**
<<<<<<<< HEAD:module/VuFind/src/VuFind/Db/Service/FeedbackServiceInterface.php
 * Database service interface for feedback.
========
 * Database usercard service factory
>>>>>>>> dev:module/VuFind/src/VuFind/Db/Service/UserCardServiceFactory.php
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\FeedbackEntityInterface;

/**
<<<<<<<< HEAD:module/VuFind/src/VuFind/Db/Service/FeedbackServiceInterface.php
 * Database service interface for feedback.
========
 * Database usercard service factory
>>>>>>>> dev:module/VuFind/src/VuFind/Db/Service/UserCardServiceFactory.php
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
<<<<<<<< HEAD:module/VuFind/src/VuFind/Db/Service/FeedbackServiceInterface.php
interface FeedbackServiceInterface extends DbServiceInterface
========
class UserCardServiceFactory extends AbstractDbServiceFactory
>>>>>>>> dev:module/VuFind/src/VuFind/Db/Service/UserCardServiceFactory.php
{
    /**
     * Create a feedback entity object.
     *
     * @return FeedbackEntityInterface
     */
<<<<<<<< HEAD:module/VuFind/src/VuFind/Db/Service/FeedbackServiceInterface.php
    public function createEntity(): FeedbackEntityInterface;
========
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory!');
        }
        return parent::__invoke(
            $container,
            $requestedName,
            [
                $container->get(\VuFind\Auth\ILSAuthenticator::class),
                $container->get(\VuFind\Config\AccountCapabilities::class),
            ]
        );
    }
>>>>>>>> dev:module/VuFind/src/VuFind/Db/Service/UserCardServiceFactory.php
}
