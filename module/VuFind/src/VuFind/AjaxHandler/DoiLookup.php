<?php
/**
 * AJAX handler to look up DOI data.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\AjaxHandler;

use VuFind\DoiLinker\PluginManager;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler to look up DOI data.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DoiLookup extends AbstractBase
{
    /**
     * DOI Linker Plugin Manager
     *
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * DOI resolver configuration value
     *
     * @var string
     */
    protected $resolver;

    /**
     * Constructor
     *
     * @param PluginManager $pluginManager DOI Linker Plugin Manager
     * @param string        $resolver      DOI resolver configuration value
     */
    public function __construct(PluginManager $pluginManager, $resolver)
    {
        $this->pluginManager = $pluginManager;
        $this->resolver = $resolver;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $response = [];
        if ($this->pluginManager->has($this->resolver)) {
            $dois = (array)$params->fromQuery('doi', []);
            $response = $this->pluginManager->get($this->resolver)->getLinks($dois);
        }
        return $this->formatResponse($response);
    }
}
