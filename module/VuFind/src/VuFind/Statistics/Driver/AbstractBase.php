<?php
/**
 * Abstract Base for Statistics Drivers
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
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Statistics\Driver;

/**
 * Base driver for statistics
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
abstract class AbstractBase
{
    /**
     * Statistics source
     *
     * @var string
     */
    protected $source = null;

    /**
     * Get the source that is using the statistics.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set the source that is using the statistics.
     *
     * @param string $source Name of source.
     *
     * @return void
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * Write a message to the log.
     *
     * @param array $data     Data specific to what we're saving
     * @param array $userData Browser, IP, urls, etc
     *
     * @return void
     */
    abstract public function write($data, $userData);

    /**
     * Get all the instances of a field.
     *
     * @param string $field What field of data are we researching?
     * @param array  $value Extra options for search. Value => match this value
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getFullList($field, $value = [])
    {
        // Assume no statistics
        return [];
    }

    /**
     * Returns browser usage statistics
     *
     * @param bool    $version Include the version numbers in the list
     * @param integer $limit   How many items to return
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getBrowserStats($version, $limit)
    {
        // Assume no statistics
        return [];
    }
}
