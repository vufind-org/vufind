<?php

/**
 * VuFind Mailer Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Mailer;

use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Mime\Address;
use VuFind\Exception\Mail as MailException;
use VuFind\RecordDriver\AbstractBase as AbstractRecord;

/**
 * VuFind Mailer Class
 *
 * @category VuFind
 * @package  Mailer
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Mailer extends \VuFind\Mailer\Mailer
{
    /**
     * Send an email message representing a list of records.
     *
     * @param string|Address|Address[]      $to      Recipient email address(es) (or delimited list)
     * @param string|Address                $from    Sender name and email address
     * @param string                        $msg     User notes to include in message
     * @param AbstractRecord[]              $records Records being emailed
     * @param PhpRenderer                   $view    View object (used to render email templates)
     * @param ?string                       $subject Subject for email (optional)
     * @param string|Address|Address[]|null $cc      CC recipient(s) (null for none)
     * @param string|Address|Address[]|null $replyTo Reply-To address(es) (or delimited list, null for none)
     *
     * @throws MailException
     * @return void
     */
    public function sendRecords(
        string|Address|array $to,
        string|Address $from,
        string $msg,
        array $records,
        PhpRenderer $view,
        ?string $subject = null,
        string|Address|array|null $cc = null,
        string|Address|array|null $replyTo = null
    ) {
        if (null === $subject) {
            $subject = $this->getDefaultRecordSubject($records);
        }
        $body = $view->partial(
            'Email/records.phtml',
            [
                'drivers' => $records, 'to' => $to, 'from' => $from,
                'message' => $msg,
            ]
        );
        $this->send($to, $from, $subject, $body, $cc, $replyTo);
    }
}
