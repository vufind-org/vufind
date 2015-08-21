<?php
/**
 * Shibboleth permission provider for VuFind.
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
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role\PermissionProvider;
use Zend\Http\PhpEnvironment\Request;
use VuFind\Auth\Shibboleth as ShibbolethAuth;

/**
 * Shibboleth permission provider for VuFind.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @author   Bernd Oberknapp <bo@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Shibboleth extends ServerParam
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Server param with the identity provider entityID
     *
     * @var string
     */
    protected $idpServerParam;

    /**
     * Constructor
     *
     * @param Request             $request Request object
     * @param \Zend\Config\Config $config  VuFind configuration
     */
    public function __construct(Request $request, $config)
    {
        parent::__construct($request);

        $this->idpServerParam = isset($config->Shibboleth->idpserverparam)
            ? $config->Shibboleth->idpserverparam
            : ShibbolethAuth::DEFAULT_IDPSERVERPARAM;

        $this->aliases = ['idpentityid' => $this->idpServerParam];
        $this->serverParamDelimiter = ';';
        $this->serverParamEscape = '\\';
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
        $this->debug('getPermissions: idpServerParam = ' . $this->idpServerParam);
        if ($this->request->getServer()->get($this->idpServerParam) === null) {
            $this->logWarning('getPermissions: Shibboleth server params missing');

            return [];
        }

        return parent::getPermissions($options);
    }
}
