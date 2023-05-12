<?php

/**
 * Linkify helper factory
 *
 * PHP version 8
 *
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
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use VStelmakh\UrlHighlight\Encoder\HtmlSpecialcharsEncoder;
use VStelmakh\UrlHighlight\UrlHighlight;
use VuFind\UrlHighlight\VuFindHighlighter;

/**
 * Linkify helper factory
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Volodymyr Stelmakh <2980619+vstelmakh@users.noreply.github.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LinkifyFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service Manager
     * @param string             $requestedName Service being created
     * @param array|null         $options       Extra options (optional)
     *
     * @return object|Linkify
     *
     * @throws \Exception
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }

        $proxyUrl = $container->get('ViewHelperManager')->get('proxyUrl');

        $highlighter = new VuFindHighlighter($proxyUrl);
        $encoder = new HtmlSpecialcharsEncoder();
        $urlHighlight = new UrlHighlight(null, $highlighter, $encoder);

        return new Linkify($urlHighlight);
    }
}
