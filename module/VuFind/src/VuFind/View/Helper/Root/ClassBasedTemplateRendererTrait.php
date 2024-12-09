<?php

/**
 * Trait for view helpers that render a template based on a class name.
 *
 * Note: This trait is for view helpers only. It expects $this->getView() method to
 * be available.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Exception\RuntimeException;
use Laminas\View\Resolver\ResolverInterface;

use function sprintf;

/**
 * Trait for view helpers that render a template based on a class name.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait ClassBasedTemplateRendererTrait
{
    /**
     * Cache for found templates
     *
     * @var array
     */
    protected $templateCache = [];

    /**
     * Recursively locate a template that matches the provided class name
     * (or one of its parent classes); throw an exception if no match is found.
     *
     * @param string            $template     Template path (with %s as class name
     * placeholder)
     * @param string            $className    Name of class to apply to template.
     * @param ResolverInterface $resolver     Resolver to use
     * @param string            $topClassName Top-level parent class of $className
     * (or null if $className is already the top level; used for recursion only).
     *
     * @return string
     */
    protected function resolveClassTemplate(
        $template,
        $className,
        ResolverInterface $resolver,
        $topClassName = null
    ) {
        // If the template resolves, return it:
        $templateWithClass = $this->getTemplateWithClass($template, $className);
        if ($resolver->resolve($templateWithClass)) {
            return $templateWithClass;
        }

        // If the template doesn't resolve, let's see if we can inherit a
        // template from a parent class:
        $parentClass = get_parent_class($className);
        if (empty($parentClass)) {
            return '';
        }

        // Recurse until we find a template or run out of parents...
        return $this->resolveClassTemplate(
            $template,
            $parentClass,
            $resolver,
            $topClassName ?? $className
        );
    }

    /**
     * Render a template associated with the provided class name, applying to
     * specified context variables.
     *
     * @param string $template  Template path (with %s as class name placeholder)
     * @param string $className Name of class to apply to template.
     * @param array  $context   Context for rendering template
     * @param bool   $throw     If true (default), an exception is thrown if the
     * template is not found. Otherwise an empty string is returned.
     *
     * @return string
     * @throws RuntimeException
     */
    protected function renderClassTemplate(
        $template,
        $className,
        $context = [],
        $throw = true
    ) {
        // Set up the needed context in the view:
        $view = $this->getView();
        $contextHelper = $view->plugin('context');
        $oldContext = $contextHelper($view)->apply($context);

        // Find and render the template:
        $classTemplate = $this->getCachedClassTemplate($template, $className);
        if (!$classTemplate && $throw) {
            throw new RuntimeException(
                'Cannot find '
                . $this->getTemplateWithClass($template, '[brief class name]')
                . " for class $className or any of its parent classes"
            );
        }

        $html = $classTemplate ? $view->render($classTemplate) : '';

        // Restore the original context before returning the result:
        $contextHelper($view)->restore($oldContext);
        return $html;
    }

    /**
     * Resolve the class template file unless already cached and return the file
     * name.
     *
     * @param string $template  Template path (with %s as class name placeholder)
     * @param string $className Name of class to apply to template.
     *
     * @return string
     */
    protected function getCachedClassTemplate($template, $className)
    {
        if (!isset($this->templateCache[$className][$template])) {
            $this->templateCache[$className][$template]
                = $this->resolveClassTemplate(
                    $template,
                    $className,
                    $this->getView()->resolver()
                );
        }
        return $this->templateCache[$className][$template];
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
     * Helper to put the template path and class name together
     *
     * @param string $template  Template path (with %s as class name placeholder)
     * @param string $className Class name to abbreviate
     *
     * @return string
     */
    protected function getTemplateWithClass(
        string $template,
        string $className
    ): string {
        return sprintf($template, $this->getBriefClass($className));
    }
}
