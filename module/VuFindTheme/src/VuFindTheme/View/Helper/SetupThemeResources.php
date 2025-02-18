<?php

/**
 * View helper for loading theme-related resources.
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

namespace VuFindTheme\View\Helper;

use function in_array;
use function is_array;

/**
 * View helper for loading theme-related resources.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SetupThemeResources extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Theme resource container
     *
     * @var \VuFindTheme\ResourceContainer
     */
    protected $container;

    /**
     * Constructor
     *
     * @param \VuFindTheme\ResourceContainer $container Theme resource container
     */
    public function __construct(\VuFindTheme\ResourceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Set up items based on contents of theme resource container.
     *
     * @param bool $partial Whether rendering an HTML snippet instead of a full page
     *
     * @return void
     */
    public function __invoke(bool $partial = false)
    {
        // meta tags are illegal outside of <head>, so we don't want to render them
        // in partial mode:
        if (!$partial) {
            $this->addMetaTags();
        }
        $this->addLinks($partial);
        $this->addScripts();
    }

    /**
     * Add meta tags to header.
     *
     * @return void
     */
    protected function addMetaTags()
    {
        // Set up encoding:
        $headMeta = $this->getView()->plugin('headMeta');
        $headMeta()->prependHttpEquiv(
            'Content-Type',
            'text/html; charset=' . $this->container->getEncoding()
        );

        // Set up generator:
        $generator = $this->container->getGenerator();
        if (!empty($generator)) {
            $headMeta()->appendName('Generator', $generator);
        }
    }

    /**
     * Add links to header.
     *
     * @param bool $partial Whether rendering an HTML snippet instead of a full page
     *
     * @return void
     */
    protected function addLinks(bool $partial = false)
    {
        // Convenient shortcut to view helper:
        $headLink = $this->getView()->plugin('headLink');

        // Load CSS (make sure we prepend them in the appropriate order; theme
        // resources should load before extras added by individual templates):
        foreach (array_reverse($this->container->getCss()) as $current) {
            $headLink()->forcePrependStylesheet(
                $current['file'],
                empty($current['media']) ? 'all' : $current['media'],
                $current['conditional'] ?? '',
                $current['extras'] ?? []
            );
        }

        // Insert link elements for favicons specified in the `favicons` property of theme.config.php.
        // If `favicon` is a string then treat it as a single file path to an .ico icon.
        // If `favicon` is an array then treat each item as an assoc array of html attributes and render
        // a link element for each.
        // Skip favicons in partial mode because they are illegal outside of <head>.
        if (!$partial && ($favicon = $this->container->getFavicon())) {
            $imageLink = $this->getView()->plugin('imageLink');
            if (is_array($favicon)) {
                foreach ($favicon as $attrs) {
                    if (isset($attrs['href'])) {
                        $attrs['href'] = $imageLink($attrs['href']);
                    }
                    $attrs['rel'] ??= 'icon';
                    $headLink($attrs);
                }
            } else {
                $headLink(
                    [
                        'href' => $imageLink($favicon),
                        'type' => 'image/x-icon',
                        'rel' => 'icon',
                    ]
                );
            }
        }
    }

    /**
     * Add scripts to header or footer.
     *
     * @return void
     */
    protected function addScripts()
    {
        $legalHelpers = ['footScript', 'headScript'];

        // Load Javascript (same ordering considerations as CSS, above):
        $js = array_reverse($this->container->getJs());

        foreach ($js as $current) {
            $position = $current['position'] ?? 'header';
            $helper = substr($position, 0, 4) . 'Script';
            if (!in_array($helper, $legalHelpers)) {
                throw new \Exception(
                    'Invalid script position for '
                    . $current['file'] . ': ' . $position . '.'
                );
            }

            $this->getView()
                ->plugin($helper)
                ->forcePrependFile(
                    $current['file'],
                    'text/javascript',
                    $current['attributes'] ?? []
                );
        }
    }
}
