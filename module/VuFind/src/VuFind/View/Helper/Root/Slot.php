<?php
/**
 * Slot/block view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * Slot/block view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Slot extends \Zend\View\Helper\AbstractHelper
{
    protected $blocks = [];

    protected $stack = [];

    /**
     * Get the Slot instance. Create if instance doesn't exist.
     *
     * @return Slot
     */
    public function __invoke($name)
    {
        $this->stack[] = $name;
        return $this;
    }

    /**
     * Get current value of slot. Returns null if unset.
     *
     * @param string $name Name of desired block
     *
     * @return string|null
     */
    public function get() {
        $name = array_pop($this->stack);
        return isset($this->blocks[$name]) ?: null;
    }

    /**
     * Set current value of slot but only if unset.
     *
     * @param string $name  Name of desired block
     * @param string $value Value to override if unset
     *
     * @return string|null
     */
    public function set($value) {
        $name = array_pop($this->stack);
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = trim($value);
        }
        return $this->blocks[$name];
    }

    /**
     * Starts a buffer capture to override the value of a block.
     *
     * @param string $name Name of desired block
     *
     * @return void
     */
    public function start() {
        array_pop($this->stack);
        ob_start();
    }

    /**
     * End a buffer capture to override the value of a block. Returns slot value.
     *
     * @param string $name Name of desired block
     *
     * @return string
     */
    public function end() {
        $ret = $this->set(ob_get_contents());
        ob_end_clean();
        return $ret;
    }

    /**
     * Unset any values stored in a slot.
     *
     * @param string $name Name of target block
     *
     * @return void
     */
    public function clear() {
        $name = array_pop($this->stack);
        unset($this->blocks[$name]);
    }
}
