<?php
/**
 * IpRange permission provider for VuFind.
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
 * IpRange permission provider for VuFind.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class IpRange implements PermissionProviderInterface
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

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
        // Check if any regex matches....
        $ip = $this->request->getServer()->get('REMOTE_ADDR');
        if ($this->checkIP($ip, $options)) {
            // Match? Grant to all users (guest or logged in).
            return ['guest', 'loggedin'];
        }

        //  No match? No permissions.
        return [];
    }

    /**
     * Check if $remoteIP is within $rangeIP
     *
     * @param string $remoteIP ip address of the user
     * @param array  $rangeIP  single ip or range of addresses
     *
     * @return bool
     *
     * @todo Implement IPv6 check
     */
    protected function checkIP($remoteIP, $rangeIP)
    {
        $mylist = [];
        $count = 0;
        $inList = false;
        foreach ((array)$rangeIP as $range) {
            if (preg_match('/-/', $range)) {
                $tmp = preg_split('/-/', $range);
                $mylist[$count]['start'] = $tmp[0];
                $mylist[$count]['end'] = $tmp[1];
            } else {
                $mylist[$count]['start'] = $range;
                $mylist[$count]['end'] = $range;
            }
            $count++;
        }
        foreach ($mylist as $check) {
            if (ip2long($remoteIP) >= ip2long($check['start'])
                && ip2long($remoteIP) <= ip2long($check['end'])
            ) {
                $inList = true;
            }
        }
        return $inList;
    }
}