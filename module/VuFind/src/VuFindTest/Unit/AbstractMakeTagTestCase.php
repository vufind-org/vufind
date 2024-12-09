<?php

/**
 * Abstract Test Class for element making helpers
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Unit;

/**
 * Abstract Test Class for element making helpers
 *
 * @category VuFind
 * @package  Tests
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class AbstractMakeTagTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Get makeTag helper with mock view
     *
     * @return \Laminas\View\Helper\EscapeHtml
     */
    protected function getViewWithHelpers()
    {
        $helpers = [
            'escapehtml' => new \Laminas\View\Helper\EscapeHtml(),
            'escapehtmlattr' => new \Laminas\View\Helper\EscapeHtmlAttr(),
            'htmlattributes' => new \Laminas\View\Helper\HtmlAttributes(),
            'maketag' => new \VuFind\View\Helper\Root\MakeTag(),
        ];

        $view = $this->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        $view
            ->expects($this->atLeastOnce())
            ->method('plugin')
            ->with(
                $this->callback(
                    function ($helper) use ($helpers) {
                        return isset($helpers[strtolower($helper)]);
                    }
                )
            )
            ->willReturnCallback(
                function ($helper) use ($helpers) {
                    return $helpers[strtolower($helper)];
                }
            );

        foreach ($helpers as $helper) {
            $helper->setView($view);
        }

        return $view;
    }
}
