<?php
/**
 * Abstract base class for helpers that render a template based on a class name.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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

use Laminas\View\Exception\RuntimeException;
use Laminas\View\Helper\AbstractHelper;

/**
 * Authentication view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractClassBasedTemplateRenderer extends AbstractHelper
{
    /**
     * Recursively locate and render a template that matches the provided class
     * name (or one of its parent classes); throw an exception if no match is
     * found.
     *
     * @param string $template     Template path (with %s as class name placeholder)
     * @param string $className    Name of class to apply to template.
     * @param string $topClassName Top-level parent class of $className (or null
     * if $className is already the top level; used for recursion only).
     *
     * @return string
     * @throws RuntimeException
     */
    protected function resolveClassTemplate($template, $className,
        $topClassName = null
    ) {
        // If the template resolves, we can render it!
        $templateWithClass = sprintf($template, $this->getBriefClass($className));
        if ($this->getView()->resolver()->resolve($templateWithClass)) {
            return $this->getView()->render($templateWithClass);
        }

        // If the template doesn't resolve, let's see if we can inherit a
        // template from a parent class:
        $parentClass = get_parent_class($className);
        if (empty($parentClass)) {
            // No more parent classes left to try?  Throw an exception!
            throw new RuntimeException(
                'Cannot find ' . $templateWithClass . ' template for class: '
                . ($topClassName ?? $className)
            );
        }

        // Recurse until we find a template or run out of parents...
        return $this->resolveClassTemplate(
            $template, $parentClass, $topClassName ?? $className
        );
    }

    /**
     * Render a template associated with the provided class name, applying to
     * specified context variables.
     *
     * @param string $template  Template path (with %s as class name placeholder)
     * @param string $className Name of class to apply to template.
     * @param array  $context   Context for rendering template
     *
     * @return string
     */
    protected function renderClassTemplate($template, $className, $context = [])
    {
        // Set up the needed context in the view:
        $contextHelper = $this->getView()->plugin('context');
        $oldContext = $contextHelper($this->getView())->apply($context);

        // Render the template for the current class:
        $html = $this->resolveClassTemplate($template, $className);

        // Restore the original context before returning the result:
        $contextHelper($this->getView())->restore($oldContext);
        return $html;
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
}
