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

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\DoiLinker\PluginManager;

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
     * DOI resolver configuration value, exploded into an array of options
     *
     * @var string[]
     */
    protected $resolvers;

    /**
     * Behavior to use when multiple resolvers find results for the same DOI (may
     * be 'first' -- use first match, or 'merge' -- use all results)
     *
     * @var string
     */
    protected $multiMode;

    /**
     * Constructor
     *
     * @param PluginManager $pluginManager DOI Linker Plugin Manager
     * @param string        $resolvers     DOI resolver configuration value
     * @param string        $multiMode     Behavior to use when multiple resolvers
     * find results for the same DOI (may be 'first' -- use first match, or 'merge'
     * -- use all results)
     */
    public function __construct(
        PluginManager $pluginManager,
        $resolvers,
        $multiMode = 'first'
    ) {
        $this->pluginManager = $pluginManager;
        $this->resolvers = array_map('trim', explode(',', $resolvers));
        $this->multiMode = trim(strtolower($multiMode));
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
        $dois = (array)$params->fromQuery('doi', []);
        foreach ($this->resolvers as $resolver) {
            if ($this->pluginManager->has($resolver)) {
                $next = $this->pluginManager->get($resolver)->getLinks($dois);
                if (empty($response)) {
                    $response = $next;
                } else {
                    foreach ($next as $doi => $data) {
                        if (!isset($response[$doi])) {
                            $response[$doi] = $data;
                        } elseif ($this->multiMode == 'merge') {
                            $response[$doi] = array_merge($response[$doi], $data);
                        }
                    }
                }
                // If all DOIs have been found and we're not in merge mode, we
                // can short circuit out of here.
                if ($this->multiMode !== 'merge'
                    && count(array_diff($dois, array_keys($response))) == 0
                ) {
                    break;
                }
            }
        }
        return $this->formatResponse($response);
    }
}
