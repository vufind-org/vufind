<?php

/**
 * Email handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\ReservationList\Handler;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Symfony\Component\Mime\Address;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Mail as MailException;

/**
 * Email handler
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Email extends AbstractBase
{
    /**
     * Places an order
     *
     * @param array               $formValues Values gathered from submitted form
     * @param UserEntityInterface $user       User entity
     *
     * @return array [
     *  external_id: Id in external service or null,
     *  success: true or false,
     *  pickup_date: date for preferred pickup,
     *  connection Type of the connection
     * ]
     */
    public function placeOrder(array $formValues, UserEntityInterface $user): array
    {
        $form = $this->getPlaceOrderForm($formValues);
        $fields = $form->mapRequestParamsToFieldValues($formValues);
        $viewRenderer = $this->getService('ViewRenderer');
        $emailMessage = $viewRenderer->render(
            'Email/form.phtml',
            compact('fields')
        );
        $cardInfo = $this->getPreferredCardInfo($user);

        $replyToName = $formValues['full_name'] ?: $cardInfo['full_name'];
        $replyToEmail = $formValues['email'] ?: $cardInfo['email'];

        $result = true;
        foreach ($this->getRecipient() as $recipient) {
            if ($recipient['email']) {
                $success = $this->sendEmail(
                    $recipient['name'] ?? '',
                    $recipient['email'],
                    $this->getSenderName(),
                    $this->getSenderEmail(),
                    $replyToName,
                    $replyToEmail,
                    $this->getEmailSubject(),
                    $emailMessage
                );
            } else {
                $this->logError('ReservationList: Recipient missing email.');
                $success = false;
            }

            $result = $result && $success;
        }
        return [
            'success' => $result,
            'external_id' => null,
            'pickup_date' => $formValues['pickup_date'],
            'connection' => 'email',
        ];
    }

    /**
     * Check list status. Used for external services.
     *
     * @param FinnaResourceListEntityInterface $list List to check for status
     *
     * @return string
     */
    public function getListStatus(FinnaResourceListEntityInterface $list): string
    {
        return '';
    }

    /**
     * Send form data as email.
     *
     * @param ?string $recipientName  Recipient name
     * @param string  $recipientEmail Recipient email
     * @param string  $senderName     Sender name
     * @param string  $senderEmail    Sender email
     * @param string  $replyToName    Reply-to name
     * @param string  $replyToEmail   Reply-to email
     * @param string  $emailSubject   Email subject
     * @param string  $emailMessage   Email message
     *
     * @return bool
     */
    protected function sendEmail(
        ?string $recipientName,
        string $recipientEmail,
        string $senderName,
        string $senderEmail,
        string $replyToName,
        string $replyToEmail,
        string $emailSubject,
        string $emailMessage
    ): bool {
        $mailer = $this->getService(\VuFind\Mailer\Mailer::class);
        try {
            $mailer->send(
                new Address($recipientEmail, $recipientName ?? ''),
                new Address($senderEmail, $senderName),
                $emailSubject,
                $emailMessage,
                null,
                !empty($replyToEmail)
                    ? new Address($replyToEmail, $replyToName) : null
            );
            return true;
        } catch (MailException $e) {
            $this->logError(
                "Failed to send email to '$recipientEmail': " . $e->getMessage()
            );
            return false;
        }
    }
}
