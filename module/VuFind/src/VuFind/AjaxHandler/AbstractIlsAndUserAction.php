<?php
/**
 * Abstract base class for handlers depending on the ILS and a logged-in user.
 *
 * PHP version 7
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Row\User;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;

/**
 * Abstract base class for handlers depending on the ILS and a logged-in user.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractIlsAndUserAction extends AbstractBase
implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * ILS authenticator
     *
     * @var ILSAuthenticator
     */
    protected $ilsAuthenticator;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * Constructor
     *
     * @param SessionSettings  $ss               Session settings
     * @param Connection       $ils              ILS connection
     * @param ILSAuthenticator $ilsAuthenticator ILS authenticator
     * @param User|bool        $user             Logged in user (or false)
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        ILSAuthenticator $ilsAuthenticator,
        $user
    ) {
        $this->sessionSettings = $ss;
        $this->ils = $ils;
        $this->ilsAuthenticator = $ilsAuthenticator;
        $this->user = $user;
    }
}
