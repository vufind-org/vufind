<?php
/**
 * Authentication view helper
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Exception\RuntimeException;

/**
 * Authentication view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Auth extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Authentication manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $manager;

    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager $manager Authentication manager
     */
    public function __construct(\VuFind\Auth\Manager $manager)
    {
        $this->manager = $manager;
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

        // Set up the needed context in the view:
        $contextHelper = $this->getView()->plugin('context');
        $context['topClass'] = $this->getBriefClass($className);
        $oldContext = $contextHelper($this->getView())->apply($context);

        // Start a loop in case we need to use a parent class' name to find the
        // appropriate template.
        $topClassName = $className; // for error message
        $resolver = $this->getView()->resolver();
        while (true) {
            // Guess the template name for the current class:
            $template = 'Auth/' . $this->getBriefClass($className) . '/' . $name;
            if ($resolver->resolve($template)) {
                // Try to render the template....
                $html = $this->getView()->render($template);
                $contextHelper($this->getView())->restore($oldContext);
                return $html;
            } else {
                // If the template doesn't exist, let's see if we can inherit a
                // template from a parent class:
                $className = get_parent_class($className);
                if (empty($className)) {
                    // No more parent classes left to try?  Throw an exception!
                    throw new RuntimeException(
                        'Cannot find ' . $name . ' template for auth module: '
                        . $topClassName
                    );
                }
            }
        }
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
     * Helper to grab the end of the class name
     *
     * @param string $className Class name to abbreviate
     *
     * @return string
     */
    protected function getBriefClass($className)
    {
        $classParts = explode('\\', $className);
        return array_pop($classParts);
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
