<?php

/**
 * Slot view helper
 *
 * PHP version 8
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

namespace VuFindTheme\View\Helper;

/**
 * Slot view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Slot extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * End saving methods
     *
     * @const string
     */
    public const SET   = 'SET';
    public const PREPEND = 'PREPEND';
    public const APPEND = 'APPEND';

    /**
     * Storage for strings to be concatenated to the front of a block
     *
     * @var array of arrays
     */
    protected $blockPrepends = [];

    /**
     * Storage for strings saved to slots
     *
     * @var array
     */
    protected $blocks = [];

    /**
     * Storage for strings to be concatenated to the end of a block
     *
     * @var array of arrays
     */
    protected $blockAppends = [];

    /**
     * Call stack to handle nested slots
     *
     * @var array
     */
    protected $stack = [];

    /**
     * Get the Slot instance. Create if instance doesn't exist.
     *
     * @param string $name  Name of target block for action
     * @param mixed  $value Optional shortcut parameter to set a value
     *
     * @return Slot|string|mixed
     */
    public function __invoke($name, $value = null)
    {
        $this->stack[] = $name;
        if ($value != null) {
            return $this->set($value);
        }
        return $this;
    }

    /**
     * Shortcut to get if no methods are called on invoke.
     *
     * @return string|mixed
     */
    public function __toString()
    {
        return $this->get();
    }

    /**
     * Checks for content to provide isset functionality.
     *
     * @return boolean
     */
    public function isset()
    {
        $name = array_pop($this->stack);
        return isset($this->blockPrepends[$name]) ||
            isset($this->blocks[$name]) ||
            isset($this->blockAppends[$name]);
    }

    /**
     * Helper function to return blocks with prepends and appends.
     * Prepends, blocks, and appends are separated byspacestopreventthisfromhappening
     *
     * Non-string data can be stored in a slot but prepend and append
     * will cause it to be concatenated into a string.
     *
     * @param string $name Name of target block for action
     *
     * @return string|mixed
     */
    protected function build($name)
    {
        $pre = $this->blockPrepends[$name] ?? [];
        $post = $this->blockAppends[$name] ?? [];
        if (!empty($pre) || !empty($post)) {
            $block = $this->blocks[$name] ?? '';
            $ret = implode(' ', $pre) . ' ' . $block . ' ' . implode(' ', $post);
            return trim($ret);
        }
        if (!isset($this->blocks[$name])) {
            return null;
        }
        return $this->blocks[$name];
    }

    /**
     * Get current value of slot. Returns null if unset.
     *
     * @param mixed $default Value to return if no value is set
     *
     * @return string|null
     */
    public function get($default = null)
    {
        $name = array_pop($this->stack);
        $ret = $this->build($name);
        return $ret ?? $default;
    }

    /**
     * Set current value of slot but only if unset.
     *
     * @param mixed $value Value to override if unset
     *
     * @return string|null
     */
    public function set($value)
    {
        $name = array_pop($this->stack);
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = $value;
        }
        return $this->build($name);
    }

    /**
     * Add string to list of block prepends.
     *
     * @param string $value Value to override if unset
     *
     * @return string
     */
    public function prepend($value)
    {
        $name = array_pop($this->stack);
        if (!isset($this->blockPrepends[$name])) {
            $this->blockPrepends[$name] = [$value];
        } else {
            array_unshift($this->blockPrepends[$name], $value);
        }
        return $this->build($name);
    }

    /**
     * Add string to list of block appends.
     *
     * @param string $value Value to override if unset
     *
     * @return string
     */
    public function append($value)
    {
        $name = array_pop($this->stack);
        if (!isset($this->blockAppends[$name])) {
            $this->blockAppends[$name] = [$value];
        } else {
            array_push($this->blockAppends[$name], $value);
        }
        return $this->build($name);
    }

    /**
     * Starts a buffer capture to override the value of a block.
     *
     * @return void
     */
    public function start()
    {
        array_pop($this->stack);
        ob_start();
    }

    /**
     * End a buffer capture to override the value of a block. Returns slot value.
     *
     * @param string $method SET/PREPEND/APPEND for where this buffer should be saved
     *
     * @return string|mixed
     */
    public function end($method = self::SET)
    {
        $method = strtoupper($method);
        if ($method == self::SET) {
            $ret = $this->set(ob_get_contents());
        } elseif ($method == self::PREPEND) {
            $ret = $this->prepend(ob_get_contents());
        } elseif ($method == self::APPEND) {
            $ret = $this->append(ob_get_contents());
        } else {
            throw new \Exception("Undefined Slot method: $method");
        }
        ob_end_clean();
        return $ret;
    }

    /**
     * Unset any values stored in a slot.
     *
     * @return void
     */
    public function clear()
    {
        $name = array_pop($this->stack);
        $ret = $this->build($name);
        unset($this->blockPrepends[$name]);
        unset($this->blocks[$name]);
        unset($this->blockAppends[$name]);
        return $ret;
    }
}
