<?php

/**
 * Hierarchy Tree HTML Renderer
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  HierarchyTree_Renderer
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\TreeRenderer;

use Laminas\Mvc\Controller\Plugin\Url as UrlPlugin;
use Laminas\View\Renderer\RendererInterface;

use function in_array;

/**
 * Hierarchy Tree HTML Renderer
 *
 * This is a helper class for producing hierarchy trees.
 *
 * @category VuFind
 * @package  HierarchyTree_Renderer
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class HTMLTree extends AbstractBase implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Router plugin
     *
     * @var UrlPlugin
     */
    protected $router = null;

    /**
     * Whether the collections functionality is enabled
     *
     * @var bool
     */
    protected $collectionsEnabled;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $viewRenderer;

    /**
     * Constructor
     *
     * @param UrlPlugin         $router             Router plugin for urls
     * @param bool              $collectionsEnabled Whether the collections functionality is enabled
     * @param RendererInterface $renderer           View renderer
     */
    public function __construct(UrlPlugin $router, bool $collectionsEnabled, RendererInterface $renderer)
    {
        $this->router = $router;
        $this->collectionsEnabled = $collectionsEnabled;
        $this->viewRenderer = $renderer;
    }

    /**
     * Get a list of trees containing the item represented by the stored record
     * driver.
     *
     * @param string $hierarchyID Optional filter: specific hierarchy ID to retrieve
     *
     * @return mixed An array of hierarchy IDS if a hierarchy tree exists,
     * false if it does not
     */
    public function getTreeList($hierarchyID = false)
    {
        $record = $this->getRecordDriver();
        $inHierarchies = $record->getHierarchyTopID();
        $inHierarchiesTitle = $record->getHierarchyTopTitle();

        if ($hierarchyID) {
            // Specific Hierarchy Supplied
            if (
                in_array($hierarchyID, $inHierarchies)
                && $this->getDataSource()->supports($hierarchyID)
            ) {
                return [
                    $hierarchyID => $this->getHierarchyName(
                        $hierarchyID,
                        $inHierarchies,
                        $inHierarchiesTitle
                    ),
                ];
            }
        } else {
            // Return All Hierarchies
            $hierarchies = [];
            foreach ($inHierarchies as $i => $hierarchyTopID) {
                if ($this->getDataSource()->supports($hierarchyTopID)) {
                    $hierarchies[$hierarchyTopID] = $inHierarchiesTitle[$i] ?? '';
                }
            }
            if (!empty($hierarchies)) {
                return $hierarchies;
            }
        }

        // If we got this far, we couldn't find valid match(es).
        return false;
    }

    /**
     * Render the Hierarchy Tree
     *
     * @param string  $context     The context from which the call has been made
     * @param string  $mode        The mode in which the tree should be generated
     * @param string  $hierarchyID The hierarchy ID of the tree to fetch (optional)
     * @param ?string $selectedID  The current record ID (optional)
     * @param array   $options     Additional options
     *
     * @return mixed The desired hierarchy tree output (or false on error)
     */
    public function render(
        string $context,
        string $mode,
        string $hierarchyID,
        ?string $selectedID = null,
        array $options = []
    ) {
        if (empty($context)) {
            return false;
        }
        if ($json = $this->getDataSource()->getJSON($hierarchyID)) {
            $driver = $this->recordDriver;
            $nodes = [json_decode($json)];
            $this->augmentNodeData($nodes, $context, $selectedID);
            return $this->viewRenderer->render(
                'hierarchy/tree.phtml',
                compact('nodes', 'context', 'hierarchyID', 'driver', 'selectedID', 'options')
            );
        }
        return false;
    }

    /**
     * Augment all nodes with 'hasSelectedChild' and 'href' for rendering.
     *
     * @param array   $nodes      Node list
     * @param string  $context    Context
     * @param ?string $selectedID Selected record ID
     *
     * @return bool Whether any items are applied (for recursive calls)
     */
    protected function augmentNodeData(array $nodes, string $context, ?string $selectedID): bool
    {
        $result = false;
        foreach ($nodes as &$node) {
            $node->hasSelectedChild = !empty($node->children)
                && $this->augmentNodeData($node->children, $context, $selectedID);
            if ($node->id === $selectedID || $node->hasSelectedChild) {
                $result = true;
            }
            $node->href = $this->getContextualUrl($node, $context);
        }
        unset($node);
        return $result;
    }

    /**
     * Use the router to build the appropriate URL based on context
     *
     * @param object $node    JSON object of a node/top node
     * @param string $context Record or Collection
     *
     * @return string
     */
    protected function getContextualUrl($node, $context)
    {
        $type = $node->type;
        if ('collection' === $type && !$this->collectionsEnabled) {
            $type = 'record';
        }
        $url = $this->getUrlFromRouteCache($type, $node->id);
        return $type === 'collection'
            ? $url . '#tabnav'
            : $url;
    }

    /**
     * Get the URL for a record and cache it to avoid the relatively slow routing
     * calls.
     *
     * @param string $route Route
     * @param string $id    Record ID
     *
     * @return string URL
     */
    protected function getUrlFromRouteCache($route, $id)
    {
        static $cache = [];
        if (!isset($cache[$route])) {
            $params = [
                'id' => '__record_id__',
                'tab' => 'HierarchyTree',
            ];
            $options = [
                'query' => [
                    'recordID' => '__record_id__',
                ],
            ];
            $cache[$route] = $this->router->fromRoute(
                $this->getRouteNameFromDataSource($route),
                $params,
                $options
            );
        }
        return str_replace('__record_id__', urlencode($id), $cache[$route]);
    }

    /**
     * Get route name from data source.
     *
     * @param string $route Route
     *
     * @return string
     */
    protected function getRouteNameFromDataSource($route)
    {
        if ($route === 'collection') {
            return $this->getDataSource()->getCollectionRoute();
        } elseif ($route === 'record') {
            return $this->getDataSource()->getRecordRoute();
        }
        return $route;
    }
}
