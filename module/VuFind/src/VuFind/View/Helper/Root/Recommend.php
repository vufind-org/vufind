<?php
/**
 * Recommendation module view helper
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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Exception\RuntimeException, Zend\View\Helper\AbstractHelper;

/**
 * Recommendation module view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Recommend extends AbstractHelper
{
    /**
     * Render the output of a recommendation module.
     *
     * @param \VuFind\Recommend\RecommendInterface $recommend The recommendation
     * object to render
     *
     * @return string
     */
    public function __invoke($recommend)
    {
        // Set up the rendering context:
        $contextHelper = $this->getView()->plugin('context');
        $oldContext = $contextHelper($this->getView())->apply(
            ['recommend' => $recommend]
        );

        // Get the current recommendation module's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // template.
        $className = get_class($recommend);
        $resolver = $this->getView()->resolver();
        while (true) {
            // Guess the template name for the current class:
            $classParts = explode('\\', $className);
            $template = 'Recommend/' . array_pop($classParts) . '.phtml';
            if ($resolver->resolve($template)) {
                // Try to render the template....
                $html = $this->getView()->render($template);
                $contextHelper($this->getView())->restore($oldContext);
                return $html;
            } else {
                // If the template doesn't exist, let's see if we can inherit a
                // template from a parent recommendation class:
                $className = get_parent_class($className);
                if (empty($className)) {
                    // No more parent classes left to try?  Throw an exception!
                    throw new RuntimeException(
                        'Cannot find template for recommendation class: ' .
                        get_class($recommend)
                    );
                }
            }
        }
    }
}