<?php

/**
 * Authentication view helper
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Exception\ILS as ILSException;

/**
 * Authentication view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Auth extends \Laminas\View\Helper\AbstractHelper
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Authentication manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $manager;

    /**
     * ILS Authenticator
     *
     * @var \VuFind\Auth\ILSAuthenticator
     */
    protected $ilsAuthenticator;

    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager          $manager          Authentication manager
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuthenticator ILS Authenticator
     */
    public function __construct(
        \VuFind\Auth\Manager $manager,
        \VuFind\Auth\ILSAuthenticator $ilsAuthenticator
    ) {
        $this->manager = $manager;
        $this->ilsAuthenticator = $ilsAuthenticator;
    }

    /**
     * Render a template within an auth module folder.
     *
     * @param string $name    Template name to render
     * @param array  $context Context for rendering template
     *
     * @return string
     */
    protected function renderTemplate($name, $context = [])
    {
        // Get the current auth module's class name
        $className = $this->getManager()->getAuthClassForTemplateRendering();
        $template = 'Auth/%s/' . $name;
        $context['topClass'] = $this->getBriefClass($className);
        return $this->renderClassTemplate($template, $className, $context);
    }

    /**
     * Get manager
     *
     * @return \VuFind\Auth\Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Checks whether the user is logged in.
     *
     * @return \VuFind\Db\Row\User|bool Object if user is logged in, false
     * otherwise.
     */
    public function isLoggedIn()
    {
        return $this->getManager()->isLoggedIn();
    }

    /**
     * Render the create account form fields.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getCreateFields($context = [])
    {
        return $this->renderTemplate('create.phtml', $context);
    }

    /**
     * Get ILS patron record for the currently logged-in user.
     *
     * @return array|bool Patron array if available, false otherwise.
     */
    public function getILSPatron()
    {
        try {
            return $this->ilsAuthenticator->storedCatalogLogin();
        } catch (ILSException $e) {
            return false;
        }
    }

    /**
     * Render the login form fields.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getLoginFields($context = [])
    {
        return $this->renderTemplate('loginfields.phtml', $context);
    }

    /**
     * Render the login template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getLogin($context = [])
    {
        return $this->renderTemplate('login.phtml', $context);
    }

    /**
     * Render the login description template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getLoginDesc($context = [])
    {
        return $this->renderTemplate('logindesc.phtml', $context);
    }

    /**
     * Render the new password form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getNewPasswordForm($context = [])
    {
        return $this->renderTemplate('newpassword.phtml', $context);
    }

    /**
     * Render the password recovery form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getPasswordRecoveryForm($context = [])
    {
        return $this->renderTemplate('recovery.phtml', $context);
    }
}
