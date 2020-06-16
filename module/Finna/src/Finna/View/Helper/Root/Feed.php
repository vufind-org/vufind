<?php
/**
 * Feed component view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Feed component view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Feed extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Feed configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Laminas\Config\Config $config Feed configuration
     * custom variables
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns HTML for embedding a feed component.
     *
     * @param string $id Feed id.
     *
     * @return mixed null|string
     */
    public function __invoke($id)
    {
        if (isset($this->config[$id]) && $this->config[$id]['active']) {
            return $this->getView()->render('Helpers/feed.phtml', ['id' => $id]);
        }
    }
}
