<?php

/**
 * Class Email
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2022.
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
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Form\Handler;

use Laminas\Config\Config;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Mail\Address;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Exception\Mail as MailException;
use VuFind\Form\Form;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Mailer\Mailer;

/**
 * Class Email
 *
 * @category VuFind
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Email implements HandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $viewRenderer;

    /**
     * Main config
     *
     * @var Config
     */
    protected $mainConfig;

    /**
     * Mailer
     *
     * @var Mailer
     */
    protected $mailer;

    /**
     * Contructor
     *
     * @param RendererInterface $viewRenderer View renderer
     * @param Config            $config       Main config
     * @param Mailer            $mailer       Mailer
     */
    public function __construct(
        RendererInterface $viewRenderer,
        Config $config,
        Mailer $mailer
    ) {
        $this->viewRenderer = $viewRenderer;
        $this->mainConfig = $config;
        $this->mailer = $mailer;
    }

    /**
     * Get data from submitted form and process them.
     *
     * @param \VuFind\Form\Form                     $form   Submitted form
     * @param \Laminas\Mvc\Controller\Plugin\Params $params Request params
     * @param ?\VuFind\Db\Row\User                  $user   Authenticated user
     *
     * @return bool
     */
    public function handle(
        \VuFind\Form\Form $form,
        \Laminas\Mvc\Controller\Plugin\Params $params,
        ?\VuFind\Db\Row\User $user = null
    ): bool {
        $fields = $form->mapRequestParamsToFieldValues($params->fromPost());
        $emailMessage = $this->viewRenderer->partial(
            'Email/form.phtml',
            compact('fields')
        );

        [$senderName, $senderEmail] = $this->getSender($form);

        $replyToName = $params->fromPost(
            'name',
            $user ? trim($user->firstname . ' ' . $user->lastname) : null
        );
        $replyToEmail = $params->fromPost(
            'email',
            $user ? $user->email : null
        );
        $recipients = $form->getRecipient($params->fromPost());
        $emailSubject = $form->getEmailSubject($params->fromPost());

        $result = true;
        foreach ($recipients as $recipient) {
            $success = $this->sendEmail(
                $recipient['name'],
                $recipient['email'],
                $senderName,
                $senderEmail,
                $replyToName,
                $replyToEmail,
                $emailSubject,
                $emailMessage
            );

            $result = $result && $success;
        }
        return $result;
    }

    /**
     * Return email sender from configuration.
     *
     * @param Form $form Form
     *
     * @return array with name, email
     */
    protected function getSender(Form $form)
    {
        $config = $this->mainConfig;
        $email = $form->getEmailFromAddress()
            ?: $config->Feedback->sender_email ?? 'noreply@vufind.org';
        $name = $form->getEmailFromName()
            ?: $config->Feedback->sender_name ?? 'VuFind Feedback';

        return [$name, $email];
    }

    /**
     * Send form data as email.
     *
     * @param string $recipientName  Recipient name
     * @param string $recipientEmail Recipient email
     * @param string $senderName     Sender name
     * @param string $senderEmail    Sender email
     * @param string $replyToName    Reply-to name
     * @param string $replyToEmail   Reply-to email
     * @param string $emailSubject   Email subject
     * @param string $emailMessage   Email message
     *
     * @return bool
     */
    protected function sendEmail(
        $recipientName,
        $recipientEmail,
        $senderName,
        $senderEmail,
        $replyToName,
        $replyToEmail,
        $emailSubject,
        $emailMessage
    ): bool {
        try {
            $this->mailer->send(
                new Address($recipientEmail, $recipientName),
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
