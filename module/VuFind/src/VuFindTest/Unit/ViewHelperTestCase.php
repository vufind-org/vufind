<?php

/**
 * Abstract base class for PHPUnit database test cases.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Unit;

/**
 * Abstract base class for PHPUnit database test cases.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
abstract class ViewHelperTestCase extends TestCase
{
    /**
     * Get a working renderer.
     *
     * @param array  $plugins Custom VuFind plug-ins to register
     * @param string $theme   Theme directory to load from
     *
     * @return \Zend\View\Renderer\PhpRenderer
     */
    protected function getPhpRenderer($plugins = [], $theme = 'bootstrap3')
    {
        $resolver = new \Zend\View\Resolver\TemplatePathStack();

        // This assumes that all themes will be testing inherit directly
        // from root with no intermediate themes.  Probably safe for most
        // test situations, though other scenarios are possible.
        $resolver->setPaths(
            [
                $this->getPathForTheme('root'),
                $this->getPathForTheme($theme)
            ]
        );
        $renderer = new \Zend\View\Renderer\PhpRenderer();
        $renderer->setResolver($resolver);
        if (!empty($plugins)) {
            $pluginManager = $renderer->getHelperPluginManager();
            foreach ($plugins as $key => $value) {
                $pluginManager->setService($key, $value);
            }
        }
        return $renderer;
    }

    /**
     * Get the directory for a given theme.
     *
     * @param string $theme Theme directory name
     *
     * @return string
     */
    protected function getPathForTheme($theme)
    {
        return APPLICATION_PATH . '/themes/' . $theme . '/templates';
    }
}