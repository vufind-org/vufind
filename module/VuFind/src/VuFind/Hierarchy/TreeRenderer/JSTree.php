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
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
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
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */

class JSTree extends AbstractBase
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    /**
     * Translator (or null if unavailable)
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = null;

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return AbstractBase
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Translate a string if a translator is available.
     *
     * @param string $msg Message to translate
     *
     * @return string
     */
    public function translate($msg)
    {
        return null !== $this->translator
            ? $this->translator->translate($msg) : $msg;
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
        $id = $record->getUniqueID();
        $inHierarchies = $record->getHierarchyTopID();
        $inHierarchiesTitle = $record->getHierarchyTopTitle();

        if ($hierarchyID) {
            // Specific Hierarchy Supplied
            if (in_array($hierarchyID, $inHierarchies)
                && $this->getDataSource()->supports($hierarchyID)
            ) {
                return array(
                    $hierarchyID => $this->getHierarchyName(
                        $hierarchyID, $inHierarchies, $inHierarchiesTitle
                    )
                );
            }
        } else {
            // Return All Hierarchies
            $i = 0;
            $hierarchies = array();
            foreach ($inHierarchies as $hierarchyTopID) {
                if ($this->getDataSource()->supports($hierarchyTopID)) {
                    $hierarchies[$hierarchyTopID] = $inHierarchiesTitle[$i];
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
            return $this->transformCollectionXML(
                $context, $mode, $hierarchyID, $recordID
            );
        }
        return false;
    }

    /**
     * transformCollectionXML
     *
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
        $params = array(
            'titleText' => $this->translate('collection_view_record'),
            'collectionID' => $hierarchyID,
            'collectionTitle' => $hierarchyTitle,
            'baseURL' => '%%%%VUFIND-BASE-URL%%%%',
            'context' => $context,
            'recordID' => $recordID
        );

        // Transform the XML
        $xmlFile = $this->getDataSource()->getXML($hierarchyID);
        $transformation = ucfirst($context) . ucfirst($mode);
        $xslFile = "Hierarchy/{$transformation}.xsl";
        return \VuFind\XSLT\Processor::process($xslFile, $xmlFile, $params);
    }
}