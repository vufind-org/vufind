<?php

/**
 * Checklist class (used for checking off a list of values)
 *
 * PHP version 8
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record;

use function count;

/**
 * Checklist class (used for checking off a list of values)
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Checklist
{
    /**
     * Unchecked values
     *
     * @var array
     */
    protected $unchecked;

    /**
     * Checked values
     *
     * @var array
     */
    protected $checked = [];

    /**
     * Constructor
     *
     * @param array $values Values for list (all begin unchecked)
     */
    public function __construct($values)
    {
        $this->unchecked = array_unique($values);
    }

    /**
     * Get list of checked values.
     *
     * @return array
     */
    public function getChecked()
    {
        return array_values($this->checked);
    }

    /**
     * Get list of unchecked values.
     *
     * @return array
     */
    public function getUnchecked()
    {
        return array_values($this->unchecked);
    }

    /**
     * Are there checked items?
     *
     * @return bool
     */
    public function hasChecked()
    {
        return count($this->checked) > 0;
    }

    /**
     * Are there unchecked items?
     *
     * @return bool
     */
    public function hasUnchecked()
    {
        return count($this->unchecked) > 0;
    }

    /**
     * Check off a value, returning true if the value was found in the unchecked
     * list and false if it was not.
     *
     * @param mixed $value Value to check
     *
     * @return bool
     */
    public function check($value)
    {
        $key = array_search($value, $this->unchecked);
        if ($key !== false) {
            unset($this->unchecked[$key]);
            $this->checked[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * Uncheck a value, returning true if the value was found in the checked
     * list and false if it was not.
     *
     * @param mixed $value Value to uncheck
     *
     * @return bool
     */
    public function uncheck($value)
    {
        $key = array_search($value, $this->checked);
        if ($key !== false) {
            unset($this->checked[$key]);
            $this->unchecked[$key] = $value;
            return true;
        }
        return false;
    }
}
