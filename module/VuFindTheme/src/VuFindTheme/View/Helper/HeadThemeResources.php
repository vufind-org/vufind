<?php
/**
 * View helper for loading theme-related resources in the header.
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
namespace VuFindTheme\View\Helper;

/**
 * View helper for loading theme-related resources in the header.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HeadThemeResources extends \Zend\View\Helper\AbstractHelper
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
     * Set up header items based on contents of theme resource container.
     *
     * @return void
     */
    public function __invoke()
    {
        // Set up encoding:
        $headMeta = $this->getView()->plugin('headmeta');
        $headMeta()->appendHttpEquiv(
            'Content-Type', 'text/html; charset=' . $this->container->getEncoding()
        );

        // Set up generator:
        $generator = $this->container->getGenerator();
        if (!empty($generator)) {
            $headMeta()->appendName('Generator', $generator);
        }

        // Load CSS (make sure we prepend them in the appropriate order; theme
        // resources should load before extras added by individual templates):
        $headLink = $this->getView()->plugin('headlink');
        foreach (array_reverse($this->container->getCss()) as $current) {
            $parts = explode(':', $current);
            $headLink()->prependStylesheet(
                trim($parts[0]),
                isset($parts[1]) ? trim($parts[1]) : 'all',
                isset($parts[2]) ? trim($parts[2]) : false
            );
        }

        // Load Javascript (same ordering considerations as CSS, above):
        $headScript = $this->getView()->plugin('headscript');
        foreach (array_reverse($this->container->getJs()) as $current) {
            $parts =  explode(':', $current);
            $headScript()->prependFile(
                trim($parts[0]),
                'text/javascript',
                isset($parts[1])
                ? array('conditional' => trim($parts[1])) : array()
            );
        }

        // If we have a favicon, load it now:
        $favicon = $this->container->getFavicon();
        if (!empty($favicon)) {
            $imageLink = $this->getView()->plugin('imagelink');
            $headLink(array(
                'href' => $imageLink($favicon),
                'type' => 'image/x-icon', 'rel' => 'shortcut icon'
            ));
        }
    }
}