<?php

/**
 * ServerParam permission provider for VuFind.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Role\PermissionProvider;

use Laminas\Http\PhpEnvironment\Request;

use function count;
use function in_array;

/**
 * ServerParam permission provider for VuFind.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ServerParam implements
    PermissionProviderInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Aliases for server param names (default: none)
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * Delimiter for multi-valued server params (default: none)
     *
     * @var string
     */
    protected $serverParamDelimiter = '';

    /**
     * Escape character for delimiter in server param strings (default: none)
     *
     * @var string
     */
    protected $serverParamEscape = '';

    /**
     * Constructor
     *
     * @param Request $request Request object
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Return an array of roles which may be granted the permission based on
     * the options.
     *
     * @param mixed $options Options provided from configuration.
     *
     * @return array
     */
    public function getPermissions($options)
    {
        // user only gets the permission if all options match (AND)
        foreach ((array)$options as $option) {
            $this->debug("getPermissions: option '{$option}'");
            if (!$this->checkServerParam($option)) {
                $this->debug('getPermissions: result = false');
                return [];
            }
            $this->debug('getPermissions: result = true');
        }
        return ['guest', 'loggedin'];
    }

    /**
     * Check if a server param matches the option.
     *
     * @param string $option Option
     *
     * @return bool true if a server param matches, false if not
     */
    protected function checkServerParam($option)
    {
        // split option on spaces unless escaped with backslash
        $optionParts = $this->splitString($option, ' ', '\\');
        if (count($optionParts) < 2) {
            $this->logError("configuration option '{$option}' invalid");
            return false;
        }

        // first part is the server param name
        $serverParamName = array_shift($optionParts);
        if (isset($this->aliases[$serverParamName])) {
            $serverParamName = $this->aliases[$serverParamName];
        }

        // optional modifier follow server param name
        $modifierMatch = in_array($optionParts[0], ['~', '!~']);
        $modifierNot = in_array($optionParts[0], ['!', '!~']);
        if ($modifierNot || $modifierMatch) {
            array_shift($optionParts);
        }

        // remaining parts are the templates for checking the server params
        $templates = $optionParts;
        if (empty($templates)) {
            $this->logError("configuration option '{$option}' invalid");
            return false;
        }

        // server param values to check
        $serverParamString = $this->request->getServer()->get($serverParamName);
        if ($serverParamString === null) {
            // check fails if server param is missing
            return false;
        }
        $serverParams = $this->splitString(
            $serverParamString,
            $this->serverParamDelimiter,
            $this->serverParamEscape
        );

        $result = false;
        // check for each server param ...
        foreach ($serverParams as $serverParam) {
            // ... if it matches one of the templates (OR)
            foreach ($templates as $template) {
                if ($modifierMatch) {
                    $result |= preg_match('/' . $template . '/', $serverParam);
                } else {
                    $result |= ($template === $serverParam);
                }
            }
        }
        if ($modifierNot) {
            $result = !$result;
        }

        return $result;
    }

    /**
     * Split string on delimiter unless dequalified with escape
     *
     * @param string $string    String to split
     * @param string $delimiter Delimiter character
     * @param string $escape    Escape character
     *
     * @return array split string parts
     */
    protected function splitString($string, $delimiter, $escape)
    {
        if ($delimiter === '') {
            return [$string];
        }

        if ($delimiter === ' ') {
            $pattern = ' +';
        } else {
            $pattern = preg_quote($delimiter, '/');
        }

        if ($escape === '') {
            $pattern = '(?<!' . preg_quote($escape, '/') . ')' . $pattern;
        }

        return str_replace(
            $escape . $delimiter,
            $delimiter,
            preg_split('/' . $pattern . '/', $string)
        );
    }
}
