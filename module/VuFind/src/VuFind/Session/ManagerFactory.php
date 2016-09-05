<?php
/**
 * Factory for instantiating Session Manager
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Session;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\SessionManager;

/**
 * Factory for instantiating Session Manager
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class ManagerFactory implements \Zend\ServiceManager\FactoryInterface
{
    /**
     * Build the options array.
     *
     * @param ServiceLocatorInterface $sm Service manager
     *
     * @return array
     */
    protected function getOptions(ServiceLocatorInterface $sm)
    {
        $cookieManager = $sm->get('VuFind\CookieManager');
        $options = [
            'cookie_path' => $cookieManager->getPath(),
            'cookie_secure' => $cookieManager->isSecure()
        ];
        $domain = $cookieManager->getDomain();
        if (!empty($domain)) {
            $options['cookie_domain'] = $domain;
        }
        return $options;
    }

    /**
     * Set up the session handler by retrieving all the pieces from the service
     * manager and injecting appropriate dependencies.
     *
     * @param ServiceLocatorInterface $sm Service manager
     *
     * @return array
     */
    protected function getHandler(ServiceLocatorInterface $sm)
    {
        // Load and validate session configuration:
        $config = $sm->get('VuFind\Config')->get('config');
        if (!isset($config->Session->type)) {
            throw new \Exception('Cannot initialize session; configuration missing');
        }

        $sessionPluginManager = $sm->get('VuFind\SessionPluginManager');
        $sessionHandler = $sessionPluginManager->get($config->Session->type);
        $sessionHandler->setConfig($config->Session);
        return $sessionHandler;
    }

    /**
     * According to the PHP manual, session_write_close should always be
     * registered as a shutdown function when using an object as a session
     * handler: http://us.php.net/manual/en/function.session-set-save-handler.php
     *
     * This method sets that up.
     *
     * @param SessionManager $sessionManager Session manager instance
     *
     * @return void
     */
    protected function registerShutdownFunction(SessionManager $sessionManager)
    {
        register_shutdown_function(
            function () use ($sessionManager) {
                // If storage is immutable, the session is already closed:
                if (!$sessionManager->getStorage()->isImmutable()) {
                    $sessionManager->writeClose();
                }
            }
        );
    }

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $sm Service manager
     *
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $sm)
    {
        // Build configuration:
        $sessionConfig = new \Zend\Session\Config\SessionConfig();
        $sessionConfig->setOptions($this->getOptions($sm));

        // Build session manager and attach handler:
        $sessionManager = new SessionManager($sessionConfig);
        $sessionManager->setSaveHandler($this->getHandler($sm));

        // Start up the session:
        $sessionManager->start();

        // Check if we need to immediately stop it based on the settings object
        // (which may have been informed by a controller that sessions should not
        // be written as part of the current process):
        $settings = $sm->get('VuFind\Session\Settings');
        if ($settings->setSessionManager($sessionManager)->isWriteDisabled()) {
            $sessionManager->getSaveHandler()->disableWrites();
        } else {
            // If the session is not disabled, we should set up the normal
            // shutdown function:
            $this->registerShutdownFunction($sessionManager);
        }

        return $sessionManager;
    }
}
