<?php

/**
 * Trait for sending email with retry support.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020-2021.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

/**
 * Trait for sending email with retry support.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait EmailWithRetryTrait
{
    /**
     * Mailer
     *
     * @var \VuFind\Mailer\Mailer
     */
    protected $mailer = null;

    /**
     * Send an email message with retry on error
     *
     * @param string|Address|AddressList $to      Recipient email address (or
     * delimited list)
     * @param string|Address             $from    Sender name and email address
     * @param string                     $subject Subject line for message
     * @param string|MimeMessage         $body    Message body
     * @param string                     $cc      CC recipient (null for none)
     * @param string|Address|AddressList $replyTo Reply-To address (or delimited
     * list, null for none)
     *
     * @throws MailException
     * @return void
     */
    protected function sendEmailWithRetry(
        $to,
        $from,
        $subject,
        $body,
        $cc = null,
        $replyTo = null
    ) {
        try {
            $this->mailer->send($to, $from, $subject, $body, $cc, $replyTo);
            return;
        } catch (\Exception $e) {
            $this->warn("First SMTP send attempt to $to failed, resetting");
        }

        $this->mailer->resetConnection();

        try {
            $this->mailer->send($to, $from, $subject, $body, $cc, $replyTo);
        } catch (\RuntimeException $e) {
            // throw as en exception
            throw new \Exception($e->getMessage());
        }
        $this->warn("SMTP send to $to succeeded on second attempt");
    }
}
