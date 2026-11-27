<?php

/**
 * Helper to get path to a template from another theme (for including).
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
use Laminas\View\Resolver\ResolverInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function is_callable;

/**
 * Helper to get path to a template from another theme (for including).
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TemplatePath
{
    /**
     * Absolute path up to the theme name.
     *
     * @var string
     */
    protected $pathPre;

    /**
     * Absolute path after the theme name.
     *
     * @var string
     */
    protected $pathPost;

    /**
     * Constructor.
     *
     * @param ResolverInterface $templatePathStack Inheritance stack of template paths
     */
    #[Autowire]
    public function __construct(protected ResolverInterface $templatePathStack)
    {
        if (!is_callable([$this->templatePathStack, 'getPaths'])) {
            throw new Exception('Template path stack must implement the getPaths method');
        }
        // get current theme path
        $paths = $this->templatePathStack->getPaths();
        // split for easy replacement later
        preg_match('/\/themes\/([^\/]+)/', $paths->current(), $matches);
        [$this->pathPre, $this->pathPost]
            = explode($matches[1], $paths->current());
    }

    /**
     * Returns an template path according the configured theme.
     *
     * @param string $template    template name like 'footer.phtml'
     * @param string $targetTheme template to pull the template from
     *
     * @return string path, null if image not found
     * @throws \Exception if no file exists at path
     */
    public function __invoke($template, $targetTheme)
    {
        $path = $this->pathPre . $targetTheme . $this->pathPost . $template;
        if (!file_exists($path)) {
            throw new \Exception(
                'Template not found in ' . $targetTheme . ': ' . $template
            );
        }
        return $path;
    }
}
