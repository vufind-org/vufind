<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Controller;
use Zend\Mvc\Controller\AbstractActionController,
    Zend\ServiceManager\ServiceLocatorInterface,
    Zend\ServiceManager\ServiceLocatorAwareInterface, Zend\View\Model\ViewModel;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class AbstractBase extends AbstractActionController
    implements ServiceLocatorAwareInterface
{
    protected $serviceLocator;

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = new ViewModel($params);

        // Always make flash messenger available to view:
        $view->flashMessenger = $this->flashMessenger();

        return $view;
    }

    /**
     * Get the service locator object.
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        if (!is_object($this->serviceLocator)) {
            throw new \Exception("Problem accessing service locator");
        }
        return $this->serviceLocator;
    }

    /**
     * Set the service locator object.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator.
     *
     * @return void
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Get the account manager object.
     *
     * @return \VuFind\Account\Manager
     */
    protected function getAuthManager()
    {
        return $this->getServiceLocator()->get('AuthManager');
    }

    /**
     * Get the user object if logged in, false otherwise.
     *
     * @return object|bool
     */
    protected function getUser()
    {
        return $this->getAuthManager()->isLoggedIn();
    }

    /**
     * Redirect the user to the login screen.
     *
     * @param string $msg    Flash message to display on login screen
     * @param array  $extras Associative array of extra fields to store
     *
     * @return ViewModel
     */
    protected function forceLogin($msg = null, $extras = array())
    {
        // Set default message if necessary.
        if (is_null($msg)) {
            $msg = 'You must be logged in first';
        }

        /* TODO:
        // Store the current URL as a login followup action unless we are in a
        // lightbox (since lightboxes use a different followup mechanism).
        if ($this->_helper->layout->getLayout() != 'lightbox') {
            $this->_helper->followup->store($extras);
        }
         */
        if (!empty($msg)) {
            $this->flashMessenger()->setNamespace('error')->addMessage($msg);
        }

        // Set a flag indicating that we are forcing login:
        $this->getRequest()->getPost()->set('forcingLogin', true);

        return $this->forward()->dispatch('MyResearch', array('action' => 'Login'));
    }

    /**
     * Does the user have catalog credentials available?  Returns associative array
     * of patron data if so, otherwise forwards to appropriate login prompt and
     * returns false.
     *
     * @return ViewModel|array|bool
     */
    protected function catalogLogin()
    {
        // First make sure user is logged in to VuFind:
        $account = $this->getAuthManager();
        if ($account->isLoggedIn() == false) {
            $this->forceLogin();
            return false;
        }

        // Now check if the user has provided credentials with which to log in:
        if (($username = $this->params()->fromPost('cat_username', false))
            && ($password = $this->params()->fromPost('cat_password', false))
        ) {
            $patron = $account->newCatalogLogin($username, $password);

            // If login failed, store a warning message:
            if (!$patron) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Invalid Patron Login');
            }
        } else {
            // If no credentials were provided, try the stored values:
            $patron = $account->storedCatalogLogin();
        }

        // If catalog login failed, send the user to the right page:
        if (!$patron) {
            return $this->forward()
                ->dispatch('MyResearch', array('action' => 'CatalogLogin'));
        }

        // Send value (either false or patron array) back to caller:
        return $patron;
    }
}