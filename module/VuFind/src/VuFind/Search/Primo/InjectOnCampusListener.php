<?php
/**
 * OnCampus listener.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Primo;

use VuFind\Search\Primo\PrimoPermissionController;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * OnCampus listener.
 * This listener detects whether a user is on campus or not.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class InjectOnCampusListener
{
    use AuthorizationServiceAwareTrait;

    /**
     * Primo Permission Controller.
     *
     * @var PrimoPermissionController
     */
    protected $permissionController;

    /**
     * Is user on campus or not?
     *
     * @var boolean
     */
    protected $isOnCampus;

    /**
     * Constructor.
     *
     * @param PrimoPermissionController $ppc Primo Permission Controller
     *
     * @return void
     */
    public function __construct($ppc = null)
    {
        $this->permissionController = $ppc;
        $this->isOnCampus = false;
    }

    /**
     * Constructor.
     *
     * @param PrimoPermissionController $ppc Primo Permission Controller
     *
     * @return void
     */
    public function setPermissionController($ppc)
    {
        $this->permissionController = $ppc;
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
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    /**
     * Determines, which value is needed for the onCampus parameter
     *
     * @return void
     */
    protected function getOnCampus()
    {
        if ($this->permissionController) {
            // The user is getting authenticated as default user
            if ($this->permissionController->isOnDefaultPermission()) {
                // In this case we have to check, if the default user has enough
                // permission to get all results
                if ($this->permissionController->checkDefaultPermission()) {
                    $this->isOnCampus = true;
                }
            }
            // If its not the default user, check if the user has been authenticated
            // by a rule.
            else if ($this->permissionController->isAuthenticated()) {
                $this->isOnCampus = true;
            }
        }
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
        $this->getOnCampus();
        $params = $event->getParam('params');
        $params->set('onCampus', $this->isOnCampus);

        return $event;
    }

}
