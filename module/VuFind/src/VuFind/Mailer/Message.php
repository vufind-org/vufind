<?php

/**
 * Tweaked Laminas Message class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Mailer
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Mailer;

use Laminas\Mail\AddressList;

/**
 * Tweaked Laminas Message class
 *
 * @category VuFind
 * @package  Mailer
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Message extends \Laminas\Mail\Message
{
    /**
     * Retrieve list of From senders
     *
     * Returns our local "From" class
     *
     * @return AddressList
     */
    public function getFrom()
    {
        return $this->getAddressListFromHeader('from', From::class);
    }

    /**
     * Access the address list of the To header
     *
     * @return AddressList
     */
    public function getTo()
    {
        return $this->getAddressListFromHeader('to', To::class);
    }

    /**
     * Retrieve list of CC recipients
     *
     * @return AddressList
     */
    public function getCc()
    {
        return $this->getAddressListFromHeader('cc', Cc::class);
    }

    /**
     * Retrieve list of BCC recipients
     *
     * @return AddressList
     */
    public function getBcc()
    {
        return $this->getAddressListFromHeader('bcc', Bcc::class);
    }

    /**
     * Access the address list of the Reply-To header
     *
     * @return AddressList
     */
    public function getReplyTo()
    {
        return $this->getAddressListFromHeader('reply-to', ReplyTo::class);
    }
}
