<?php
/**
 * Record tab abstract base class
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;

/**
 * Record tab abstract base class
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
abstract class AbstractBase implements TabInterface
{
    /**
     * Record driver associated with the tab
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $driver = null;

    /**
     * User request associated with the tab (false for none)
     *
     * @var \Zend\Http\Request|bool
     */
    protected $request = false;

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        // Assume active by default; subclasses may add rules.
        return true;
    }

    /**
     * Is this tab initially visible?
     *
     * @return bool
     */
    public function isVisible()
    {
        // Assume visible by default; subclasses may add rules.
        return true;
    }

    /**
     * Set the record driver
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return AbstractBase
     */
    public function setRecordDriver(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Get the record driver
     *
     * @return \VuFind\RecordDriver\AbstractBase
     * @throws \Exception
     */
    protected function getRecordDriver()
    {
        if (null === $this->driver) {
            throw new \Exception('Record driver not set.');
        }
        return $this->driver;
    }

    /**
     * Set the user request
     *
     * @param \Zend\Http\Request $request Request
     *
     * @return AbstractBase
     */
    public function setRequest(\Zend\Http\Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get the user request (or false if unavailable)
     *
     * @return \Zend\Http\Request|bool
     */
    protected function getRequest()
    {
        return $this->request;
    }
}
