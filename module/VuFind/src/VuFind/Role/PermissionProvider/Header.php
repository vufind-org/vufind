<?php
/**
 * Header permission provider for VuFind.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role\PermissionProvider;
use Zend\Http\PhpEnvironment\Request;

/**
 * Header permission provider for VuFind.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Header implements PermissionProviderInterface
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    protected $aliases = array();

    protected $headerDelimiter = null;
    protected $headerEscape = null;

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
        // user only gets the permission if all rules match (AND)
        foreach((array)$options as $rule) {
            if(!$this->checkHeaderRule($rule)) {
                return [];
            }
        }
        return ['loggedin'];
    }


    protected function checkHeaderRule($rule) {
        // split rule on spaces unless escaped with backslash
        $ruleParts = preg_split('/(?<!\\\)\ +/', $rule);
        
        // first part is the header name
        $headerName = str_replace('\\ ', ' ', array_pop($ruleParts));
        if(isset($this->aliases[$headerName])) {
            $headerName = $this->aliases[$headerName];
        }

        // header values to check
        $headerString = $this->request->getServer()->get($headerName);
        if($headerString === false) {
            // header is missing, check failed
            return false;
        }
        $headers = $this->splitHeader($headerString);

        // optional modifier follows header name
        $modifierMatch = in_array($ruleParts[0], ['~', '!~']);
        $modifierNot = in_array($ruleParts[0], ['!', '!~']);
        if($modifierNot || $modifierMatch) {
            array_pop($ruleParts);
        }

        // remaining parts are the rules for checking the headers
        $rules = $ruleParts;

        $result = false;
        // check for each header ...
        foreach($headers as $header) {
            // ... if it matches one of the rules (OR)
            foreach($rules as $rule) {
                if($modifierMatch) {
                    $pattern = '/' . preg_quote($rule, '/') . '/';
                    $result |= preg_match($pattern, $header);
                } else {
                    $result |= ($rule === $header);
                }
            }
        }
        if($modifierNot) {
            $result = !$result;
        }

        return $result;
    }

    protected function splitHeader($headerString) {
        if($this->headerDelimiter === null) {
            return [ $headerString ];
        }

        if($this->headerEscape === null) {
            throw new InvalidConfigurationException(); // TODO
        }

        if($this->headerDelimiter === ' ') {
            $delimiter = ' +';
        } else {
            $delimiter = preg_quote($this->headerDelimiter, '/');
        }
        $escape = preg_quote($this->headerEscape, '/');
        $pattern = "/(?<!{$escape}){$delimiter}/";

        return str_replace("{$this->headerEscape}{$this->headerDelimiter}",
                           preg_split($pattern, $headerString));
    }
}
