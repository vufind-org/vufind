<?php

/**
 * Context manager (useful for using render() instead of partial() for better
 * performance -- this allows us to set and roll back variables in the global
 * scope instead of relying on the overhead of building a whole new scope).
 *
 * PHP version 8
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

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Renderer\RendererInterface;

/**
 * Context manager (useful for using render() instead of partial() for better
 * performance -- this allows us to set and roll back variables in the global
 * scope instead of relying on the overhead of building a whole new scope).
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Context extends AbstractHelper
{
    /**
     * Set an array of variables in the view; return the previous values of those
     * variables so they can be restored.
     *
     * @param array $vars Variables to set
     *
     * @return array
     */
    public function apply($vars)
    {
        $view = $this->getView();

        $oldVars = [];
        foreach ($vars as $k => $v) {
            $oldVars[$k] = $view->$k ?? null;
            $view->$k = $v;
        }
        return $oldVars;
    }

    /**
     * Restore an old context returned by apply().
     *
     * @param array $vars Variables to set
     *
     * @return void
     */
    public function restore($vars)
    {
        $view = $this->getView();

        foreach ($vars as $k => $v) {
            if (null === $v) {
                unset($view->$k);
            } else {
                $view->$k = $v;
            }
        }
    }

    /**
     * Render a template using a temporary context; restore the view to its
     * original state when done. This offers the template full access to the
     * global scope, modified by $context, then puts the global scope back
     * the way it was.
     *
     * @param string $template Template to render
     * @param array  $context  Array of context variables to set temporarily
     *
     * @return string          Rendered template output
     */
    public function renderInContext($template, $context)
    {
        $oldContext = $this->apply($context);
        $html = $this->getView()->render($template);
        $this->restore($oldContext);
        return $html;
    }

    /**
     * Grab the helper object, so we can call methods on it.
     *
     * @param ?RendererInterface $view View object to modify.
     *
     * @return Context
     */
    public function __invoke(RendererInterface $view = null)
    {
        if (null !== $view) {
            $this->setView($view);
        }
        return $this;
    }
}
