<?php

/**
 * Abstract base class for cart actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Cart;

use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Cart;
use VuFind\Export;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;

/**
 * Abstract base class for cart actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCartAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param Export         $export         Export handler
     * @param Cart           $cart           Cart handler
     * @param FollowupHelper $followupHelper Followup helper
     */
    #[Autowire]
    public function __construct(
        protected Export $export,
        protected Cart $cart,
        protected FollowupHelper $followupHelper,
    ) {
        parent::__construct();
    }

    /**
     * Figure out an action from the request.
     *
     * @param string $default Default action if none can be determined.
     *
     * @return string
     */
    protected function getCartActionFromRequest(string $default = 'Home'): string
    {
        if ('' !== $this->getPostParam('email', '')) {
            return 'Email';
        } elseif ('' !== $this->getPostParam('print', '')) {
            return 'PrintCart';
        } elseif ('' !== $this->getPostParam('saveCart', '')) {
            return 'Save';
        } elseif ('' !== $this->getPostParam('cite', '')) {
            return 'Cite';
        } elseif ('' !== $this->getPostParam('export', '')) {
            return 'Export';
        }
        // Check if the user is in the midst of a login process; if not,
        // use the provided default.
        return $this->followupHelper->retrieveAndClear('cartAction', $default);
    }
}
