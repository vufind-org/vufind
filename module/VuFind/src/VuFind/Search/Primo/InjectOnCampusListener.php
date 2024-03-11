<?php

/**
 * OnCampus listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Primo;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;
use VuFindSearch\Service;

/**
 * OnCampus listener.
 * This listener detects whether a user is on campus or not.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InjectOnCampusListener
{
    use AuthorizationServiceAwareTrait;

    /**
     * Primo Permission Handler.
     *
     * @var PrimoPermissionHandler
     */
    protected $permissionHandler;

    /**
     * Is user on campus or not?
     *
     * @var bool
     */
    protected $isOnCampus;

    /**
     * Constructor.
     *
     * @param PrimoPermissionHandler $pph Primo Permission Handler
     *
     * @return void
     */
    public function __construct($pph = null)
    {
        $this->setPermissionHandler($pph);
    }

    /**
     * Constructor.
     *
     * @param PrimoPermissionHandler $pph Primo Permission Handler
     *
     * @return void
     */
    public function setPermissionHandler($pph)
    {
        $this->permissionHandler = $pph;
        $this->isOnCampus = null; // clear cache
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            Service::class,
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
    }

    /**
     * Determines, which value is needed for the onCampus parameter
     *
     * @return bool
     */
    protected function getOnCampus()
    {
        if (null === $this->isOnCampus) {
            $this->isOnCampus = $this->permissionHandler
                ? $this->permissionHandler->hasPermission() : false;
        }
        return $this->isOnCampus;
    }

    /**
     * Set up onCampus Listener.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $params = $event->getParam('command')->getSearchParameters();
        $params->set('onCampus', $this->getOnCampus());

        return $event;
    }
}
