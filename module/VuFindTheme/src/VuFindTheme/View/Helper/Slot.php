<?php
/**
 * Slot view helper
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
class Slot extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Storage for strings to be concatinated to the front of a block
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
     * Storage for strings to be concatinated to the end of a block
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
     * @param string $name Name of target block for action
     *
     * @return Slot
     */
    public function __invoke($name)
    {
        $this->stack[] = $name;
        return $this;
    }

    /**
     * Helper function to return blocks with prepends and appends.
     *
     * Non-string data can be stored in a slot but prepend and append
     * will cause it to be concatinated into a string.
     *
     * @param string $name Name of target block for action
     *
     * @return string|any
     */
    protected function build($name)
    {
        $pre = $this->blockPrepends[$name] ?? [];
        $block = $this->blocks[$name] ?? '';
        $post = $this->blockAppends[$name] ?? [];
        if (!empty($pre) || !empty($post)) {
            return trim(
                implode(' ', $pre) . ' ' . $block . ' ' . implode(' ', $post)
            );
        }
        if (empty($block)) {
            return null;
        }
        return $block;
    }

    /**
     * Get current value of slot. Returns null if unset.
     *
     * @return string|null
     */
    public function get()
    {
        $name = array_pop($this->stack);
        return $this->build($name);
    }

    /**
     * Set current value of slot but only if unset.
     *
     * @param any $value Value to override if unset
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
     * @return string|any
     */
    public function end($method = 'SET')
    {
        $ret;
        if ($method == 'SET') {
            $ret = $this->set(ob_get_contents());
        } elseif ($method == 'PREPEND') {
            $ret = $this->prepend(ob_get_contents());
        } elseif ($method == 'APPEND') {
            $ret = $this->append(ob_get_contents());
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
        unset($this->blockPrepends[$name]);
        unset($this->blocks[$name]);
        unset($this->blockAppends[$name]);
    }
}
