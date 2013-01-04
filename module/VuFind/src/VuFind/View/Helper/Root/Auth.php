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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Exception\RuntimeException;

/**
 * Authentication view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class Auth extends AbstractServiceLocator
{
    /**
     * Render a template within an auth module folder.
     *
     * @param string $name    Template name to render
     * @param array  $context Context for rendering template
     *
     * @return string
     */
    protected function renderTemplate($name, $context = array())
    {
        // Set up the needed context in the view:
        $contextHelper = $this->getView()->plugin('context');
        $oldContext = $contextHelper($this->getView())->apply($context);

        // Get the current auth module's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // template.
        $className = $this->getManager()->getAuthClass();
        $topClassName = $className; // for error message
        while (true) {
            // Guess the template name for the current class:
            $classParts = explode('\\', $className);
            $template = 'Auth/' . array_pop($classParts) . '/' . $name;
            try {
                // Try to render the template....
                $html = $this->getView()->render($template);
                $contextHelper($this->getView())->restore($oldContext);
                return $html;
            } catch (RuntimeException $e) {
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
        return $this->getServiceLocator()->get('VuFind\AuthManager');
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
    public function getCreateFields($context = array())
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
    public function getLoginFields($context = array())
    {
        return $this->renderTemplate('login.phtml', $context);
    }
}