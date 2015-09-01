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
     * onCampus IP Range.
     *
     * @var string
     */
    protected $onCampusIPRange;

    /**
     * Is user on campus or not?
     *
     * @var boolean
     */
    protected $isOnCampus;

    /**
     * Constructor.
     *
     * @param string $onCampusIPRange Name of the permission set
     *
     * @return void
     */
    public function __construct($onCampusIPRange = null)
    {
        $this->onCampusIPRange = $onCampusIPRange;
        $this->isOnCampus = false;
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
     * Get the onCampus network
     *
     * @return void
     */
    protected function getOnCampus()
    {
        $authService = $this->getAuthorizationService();

        // if no authorization service is available, don't do anything
        if (!$authService) {
            return;
        }

        // otherwise the condition should match to apply the filter
        if ($authService->isGranted($this->onCampusIPRange)) {
            $this->isOnCampus = true;
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
