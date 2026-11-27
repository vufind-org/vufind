<?php

/**
 * Helper to get path to a parent template (for including).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindTheme\View\Helper;

use Exception;
use IteratorAggregate;
use Laminas\Stdlib\SplStack;
use Laminas\View\Resolver\ResolverInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function is_callable;

/**
 * Helper to get path to a parent template (for including).
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParentTemplate
{
    /**
     * Constructor.
     *
     * @param ResolverInterface $templatePathStack Inheritance stack of template paths
     */
    #[Autowire]
    public function __construct(protected ResolverInterface $templatePathStack)
    {
    }

    /**
     * Returns an template path according the configured theme.
     *
     * @param string $template    template name like 'footer.phtml'
     * @param string $targetTheme specific parent to inherit from
     *
     * @return string path, null if image not found
     * @throws Exception if no file exists at path
     */
    public function __invoke($template, $targetTheme = null)
    {
        $paths = new SplStack();
        if ($this->templatePathStack instanceof IteratorAggregate) {
            foreach ($this->templatePathStack as $current) {
                if (is_callable([$current, 'getPaths'])) {
                    $paths = [
                        ...$paths,
                        ...$current->getPaths()->toArray(),
                    ];
                }
            }
        } else {
            if (!is_callable([$this->templatePathStack, 'getPaths'])) {
                throw new Exception('Template path stack must implement the getPaths method');
            }
            $paths = $this->templatePathStack->getPaths()->toArray();
        }

        // skip current theme
        array_shift($paths);

        foreach ($paths as $path) {
            if (
                (!file_exists($path . $template) ||
                (!empty($targetTheme) && !strstr($path, $targetTheme)))
            ) {
                continue;
            }
            return $path . $template;
        }
        throw new \Exception('not found in parent themes: ' . $template);
    }
}
