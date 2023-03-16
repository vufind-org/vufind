<?php

/**
 * Hierarchy Tree Data Source (XML File)
 *
 * PHP version 7
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
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Hierarchy\TreeDataSource;

/**
 * Hierarchy Tree Data Source (XML File)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class XMLFile extends AbstractBase
{
    /**
     * Base path to XML files
     *
     * @var string
     */
    protected $basePath = null;

    /**
     * Get the base path for XML files.
     *
     * @return string
     */
    protected function getBasePath()
    {
        if (null === $this->basePath) {
            $settings = $this->getHierarchyDriver()->getTreeSettings();
            $this->basePath = $settings['XMLFileDir'] ?? '';
        }
        return $this->basePath;
    }

    /**
     * Get the full filename for the XML file for a specific ID.
     *
     * @param string $id Hierarchy ID.
     *
     * @return string
     */
    protected function getFilename($id)
    {
        return $this->getBasePath() . '/' . urlencode($id) . '.xml';
    }

    /**
     * Get XML for the specified hierarchy ID.
     *
     * @param string $id      Hierarchy ID.
     * @param array  $options Additional options for XML generation (unused here).
     *
     * @return string
     */
    public function getXML($id, $options = [])
    {
        return file_get_contents($this->getFilename($id));
    }

    /**
     * Does this data source support the specified hierarchy ID?
     *
     * @param string $id Hierarchy ID.
     *
     * @return bool
     */
    public function supports($id)
    {
        return file_exists($this->getFilename($id));
    }
}
