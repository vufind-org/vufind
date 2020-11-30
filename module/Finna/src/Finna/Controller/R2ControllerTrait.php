<?php
/**
 * R2 controller trait.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Laminas\EventManager\EventInterface;

/**
 * R2 controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait R2ControllerTrait
{
    /**
     * Handle onDispatch event
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        $helper = $this->getViewRenderer()->plugin('R2');
        if (!$helper->isAvailable()) {
            throw new \Exception('R2 is disabled');
        }

        return parent::onDispatch($e);
    }

    /**
     * Attach listener to shared event manager.
     *
     * @return void
     */
    public function attachDefaultListeners()
    {
        parent::attachDefaultListeners();

        $events = $this->serviceLocator->get('SharedEventManager');
        $events->attach(
            \FinnaSearch\Backend\R2\Connector::class,
            \FinnaSearch\Backend\R2\Connector::EVENT_REMS_SESSION_EXPIRED,
            [$this, 'onRemsSessionExpired']
        );
    }

    /**
     * REMS session expired listener.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onRemsSessionExpired(EventInterface $event)
    {
        $url = $this->serviceLocator->get(\VuFind\Auth\Manager::class)
            ->logout($this->url()->fromRoute('myresearch-home'));

        $session
            = $this->serviceLocator->get(\Laminas\Session\SessionManager::class);
        // Logout closed the previous session. Start a new one:
        $session->start();
        // Use a new session id so that any single logout hook doesn't destroy it:
        $session->regenerateId();
        $this->flashMessenger()->addErrorMessage('R2_rems_session_expired');

        return $this->redirect()->toUrl($url);
    }
}
