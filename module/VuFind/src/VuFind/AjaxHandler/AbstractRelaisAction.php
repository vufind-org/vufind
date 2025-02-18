<?php

/**
 * Abstract Relais Ajax Action
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use VuFind\Connection\Relais;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Session\Settings as SessionSettings;

/**
 * Abstract Relais Ajax Action
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractRelaisAction extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param SessionSettings      $ss     Session settings
     * @param Relais               $relais Relais connector
     * @param ?UserEntityInterface $user   Logged in user (or null if none)
     */
    public function __construct(
        SessionSettings $ss,
        protected Relais $relais,
        protected ?UserEntityInterface $user
    ) {
        $this->sessionSettings = $ss;
    }
}
