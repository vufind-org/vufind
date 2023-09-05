<?php

/**
 * HTTP Request class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  HTTP
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Http\PhpEnvironment;

use function is_array;
use function is_string;

/**
 * HTTP Request class
 *
 * @category VuFind
 * @package  HTTP
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Request extends \Laminas\Http\PhpEnvironment\Request
{
    /**
     * Return the parameter container responsible for query parameters or a single
     * query parameter
     *
     * @param string|null $name    Parameter name to retrieve, or null to get the
     * whole container.
     * @param mixed|null  $default Default value to use when the parameter is
     * missing.
     *
     * @return \Laminas\Stdlib\ParametersInterface|mixed
     */
    public function getQuery($name = null, $default = null)
    {
        return $this->cleanup(parent::getQuery($name, $default));
    }

    /**
     * Return the parameter container responsible for post parameters or a single
     * post parameter.
     *
     * @param string|null $name    Parameter name to retrieve, or null to get the
     * whole container.
     * @param mixed|null  $default Default value to use when the parameter is
     * missing.
     *
     * @return \Laminas\Stdlib\ParametersInterface|mixed
     */
    public function getPost($name = null, $default = null)
    {
        return $this->cleanup(parent::getPost($name, $default));
    }

    /**
     * Return the parameter container responsible for server parameters or a single
     * parameter value.
     *
     * @param string|null $name    Parameter name to retrieve, or null to get the
     * whole container.
     * @param mixed|null  $default Default value to use when the parameter is
     * missing.
     *
     * @see    http://www.faqs.org/rfcs/rfc3875.html
     * @return \Laminas\Stdlib\ParametersInterface|mixed
     */
    public function getServer($name = null, $default = null)
    {
        return $this->cleanup(parent::getServer($name, $default));
    }

    /**
     * Clean up a parameter
     *
     * @param \Laminas\Stdlib\ParametersInterface|mixed $param Parameter
     *
     * @return \Laminas\Stdlib\ParametersInterface|mixed
     */
    protected function cleanup($param)
    {
        if (
            is_array($param)
            || $param instanceof \Laminas\Stdlib\ParametersInterface
        ) {
            foreach ($param as $key => &$value) {
                if (is_array($value)) {
                    $value = $this->cleanup($value);
                } elseif (!$this->isValid($key) || !$this->isValid($value)) {
                    unset($param[$key]);
                }
            }
            return $param;
        }

        if (is_string($param) && !$this->isValid($param)) {
            return '';
        }

        return $param;
    }

    /**
     * Check if a parameter is valid
     *
     * @param mixed $param Parameter to check
     *
     * @return bool
     */
    protected function isValid($param)
    {
        if (!is_string($param)) {
            return true;
        }
        // Check if the string is UTF-8:
        if ($param !== '' && !preg_match('/^./su', $param)) {
            return false;
        }
        // Check for null in string:
        if (str_contains($param, "\x00")) {
            return false;
        }
        return true;
    }
}
