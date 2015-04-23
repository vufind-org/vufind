<?php
/**
 * Hierarchy Tree Renderer for the JS_Tree plugin
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
 * @package  HierarchyTree_Renderer
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeRenderer;

/**
 * Hierarchy Tree Renderer
 *
 * This is a helper class for producing hierarchy trees.
 *
 * @category VuFind2
 * @package  HierarchyTree_Renderer
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class JSTree extends AbstractBase
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Router plugin
     *
     * @var \Zend\Mvc\Controller\Plugin\Url
     */
    protected $router = null;

    /**
     * Constructor
     *
     * @param \Zend\Mvc\Controller\Plugin\Url $router Router plugin for urls
     */
    public function __construct(\Zend\Mvc\Controller\Plugin\Url $router)
    {
        $this->router = $router;
    }

    /**
     * Get a list of trees containing the item represented by the stored record
     * driver.
     *
     * @param string $hierarchyID Optional filter: specific hierarchy ID to retrieve
     *
     * @return mixed An array of hierarchy IDS if an archive tree exists,
     * false if it does not
     */
    public function getTreeList($hierarchyID = false)
    {
        $record = $this->getRecordDriver();
        $inHierarchies = $record->getHierarchyTopID();
        $inHierarchiesTitle = $record->getHierarchyTopTitle();

        if ($hierarchyID) {
            // Specific Hierarchy Supplied
            if (in_array($hierarchyID, $inHierarchies)
                && $this->getDataSource()->supports($hierarchyID)
            ) {
                return [
                    $hierarchyID => $this->getHierarchyName(
                        $hierarchyID, $inHierarchies, $inHierarchiesTitle
                    )
                ];
            }
        } else {
            // Return All Hierarchies
            $i = 0;
            $hierarchies = [];
            foreach ($inHierarchies as $hierarchyTopID) {
                if ($this->getDataSource()->supports($hierarchyTopID)) {
                    $hierarchies[$hierarchyTopID] = isset($inHierarchiesTitle[$i])
                        ? $inHierarchiesTitle[$i] : '';
                }
                $i++;
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
     * @param string $context     The context from which the call has been made
     * @param string $mode        The mode in which the tree should be generated
     * @param string $hierarchyID The hierarchy ID of the tree to fetch (optional)
     * @param string $recordID    The current record ID (optional)
     *
     * @return mixed The desired hierarchy tree output (or false on error)
     */
    public function render($context, $mode, $hierarchyID, $recordID = false)
    {
        if (!empty($context) && !empty($mode)) {
            if ($mode == 'List') {
                $json = $this->getDataSource()->getJSON($hierarchyID);
                if (!empty($json)) {
                    return $this->jsonToHTML(
                        json_decode($json),
                        $context,
                        $hierarchyID,
                        $this->recordDriver->getUniqueId()
                    );
                }
            } else {
                return $this->transformCollectionXML(
                    $context, $mode, $hierarchyID, $recordID
                );
            }
        }
        return false;
    }

    /**
     * Render the Hierarchy Tree
     *
     * @param string $hierarchyID The hierarchy ID of the tree to fetch
     * @param string $context     Record or Collection
     *
     * @return mixed The desired hierarchy tree output (or false on error)
     */
    public function getJSON($hierarchyID, $context = 'Record')
    {
        $json = $this->getDataSource()->getJSON($hierarchyID);
        if ($json == null) {
            return false;
        }
        return json_encode(
            $this->buildNodeArray(json_decode($json), $context, $hierarchyID)
        );
    }

    /**
     * Recursive function to convert the json to the right format
     *
     * @param object $node        JSON object of a node/top node
     * @param string $context     Record or Collection
     * @param string $hierarchyID Collection ID
     *
     * @return array
     */
    protected function buildNodeArray($node, $context, $hierarchyID)
    {
        $escaper = new \Zend\Escaper\Escaper('utf-8');
        $ret = [
            'id' => preg_replace('/\W/', '-', $node->id),
            'text' => $escaper->escapeHtml($node->title),
            'li_attr' => [
                'recordid' => $node->id
            ],
            'a_attr' => [
                'href' => $this->getContextualUrl($node, $context, $hierarchyID),
                'title' => $node->title
            ],
            'type' => $node->type
        ];
        if (isset($node->children)) {
            $ret['children'] = [];
            for ($i = 0;$i<count($node->children);$i++) {
                $ret['children'][$i] = $this
                    ->buildNodeArray($node->children[$i], $context, $hierarchyID);
            }
        }
        return $ret;
    }

    /**
     * Use the router to build the appropriate URL based on context
     *
     * @param object $node         JSON object of a node/top node
     * @param string $context      Record or Collection
     * @param string $collectionID Collection ID
     *
     * @return string
     */
    protected function getContextualUrl($node, $context, $collectionID)
    {
        $params = [
            'id' => $node->id,
            'tab' => 'HierarchyTree'
        ];
        $options = [
            'query' => [
                'recordID' => $node->id
            ]
        ];
        if ($context == 'Collection') {
            return $this->router->fromRoute('collection', $params, $options)
                . '#tabnav';
        } else {
            $options['query']['hierarchy'] = $collectionID;
            $url = $this->router->fromRoute($node->type, $params, $options);
            return $node->type == 'collection'
                ? $url . '#tabnav'
                : $url . '#tree-' . preg_replace('/\W/', '-', $node->id);
        }
    }

    /**
     * Convert JSTree JSON structure to HTML
     *
     * @param object $node        JSON object of a the JSTree
     * @param string $context     Record or Collection
     * @param string $hierarchyID Collection ID
     * @param string $recordID    The currently active record
     *
     * @return string
     */
    protected function jsonToHTML($node, $context, $hierarchyID, $recordID = false)
    {
        $escaper = new \Zend\Escaper\Escaper('utf-8');

        $name = strlen($node->title) > 100
            ? substr($node->title, 0, 100) . '...'
            : $node->title;
        $href = $this->getContextualUrl($node, $context, $hierarchyID);
        $icon = $node->type == 'record' ? 'file-o' : 'folder-open';

        $html = '<li';
        if ($node->type == 'collection') {
            $html .= ' class="hierarchy';
            if ($recordID && $recordID == $node->id) {
                $html .= ' currentHierarchy';
            }
            $html .= '"';
        } elseif ($recordID && $recordID == $node->id) {
            $html .= ' class="currentRecord"';
        }
        $html .= '><i class="fa fa-li fa-' . $icon . '"></i> '
            . '<a name="tree-' . $escaper->escapeHtmlAttr($node->id) . '" href="'
            . $escaper->escapeHtmlAttr($href) . '" title="'
            . $escaper->escapeHtml($node->title) . '">'
            . $escaper->escapeHtml($name) . '</a>';
        if (isset($node->children)) {
            $html .= '<ul class="fa-ul">';
            foreach ($node->children as $child) {
                $html .= $this->jsonToHTML(
                    $child, $context, $hierarchyID, $recordID
                );
            }
            $html .= '</ul>';
        }
        return $html . '</li>';
    }

    /**
     * Transforms Collection XML to Desired Format
     *
     * @param string $context     The Context in which the tree is being displayed
     * @param string $mode        The Mode in which the tree is being displayed
     * @param string $hierarchyID The hierarchy to get the tree for
     * @param string $recordID    The currently selected Record (false for none)
     *
     * @return string A HTML List
     */
    protected function transformCollectionXML(
        $context, $mode, $hierarchyID, $recordID
    ) {
        $record = $this->getRecordDriver();
        $inHierarchies = $record->getHierarchyTopID();
        $inHierarchiesTitle = $record->getHierarchyTopTitle();

        $hierarchyTitle = $this->getHierarchyName(
            $hierarchyID, $inHierarchies, $inHierarchiesTitle
        );

        // Set up parameters for XSL transformation
        $params = [
            'titleText' => $this->translate('collection_view_record'),
            'collectionID' => $hierarchyID,
            'collectionTitle' => $hierarchyTitle,
            'baseURL' => rtrim($this->router->fromRoute('home'), '/'),
            'context' => $context,
            'recordID' => $recordID
        ];

        // Transform the XML
        $xmlFile = $this->getDataSource()->getXML($hierarchyID);
        $transformation = ucfirst($context) . ucfirst($mode);
        $xslFile = "Hierarchy/{$transformation}.xsl";
        return \VuFind\XSLT\Processor::process($xslFile, $xmlFile, $params);
    }
}