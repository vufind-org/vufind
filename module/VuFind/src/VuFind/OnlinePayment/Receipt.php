<?php

/**
 * Online payment receipt handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2025.
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment;

use Laminas\Router\RouteInterface;
use Laminas\View\Renderer\PhpRenderer;
use Mpdf\Mpdf;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Mailer\Mailer;
use VuFind\Service\CurrencyFormatter;

use function count;

/**
 * Online payment receipt handler
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Receipt implements TranslatorAwareInterface
{
    use EmailSettingsTrait;
    use TranslatorAwareTrait;

    /**
     * Left margin of PDF (millimeters)
     *
     * @var int
     */
    protected int $left = 10;

    /**
     * Max x position of PDF (millimeters)
     *
     * @var int
     */
    protected int $right = 200;

    /**
     * Max y position of PDF (millimeters)
     */
    protected int $bottom = 280;

    /**
     * Payment configuration
     *
     * @var array
     */
    protected array $paymentConfig = [];

    /**
     * Constructor.
     *
     * @param array                      $config            Main configuration
     * @param DateConverter              $dateConverter     Date converter
     * @param LocaleSettings             $localeSettings    Locale settings
     * @param CurrencyFormatter          $currencyFormatter Currency formatter
     * @param RouteInterface             $router            Router
     * @param Mailer                     $mailer            Mailer
     * @param PhpRenderer                $renderer          View renderer
     * @param PaymentFeeServiceInterface $paymentFeeService Payment fee database service
     * @param string                     $cacheDir          Cache directory for temporary files
     */
    public function __construct(
        protected array $config,
        protected DateConverter $dateConverter,
        protected LocaleSettings $localeSettings,
        protected CurrencyFormatter $currencyFormatter,
        protected RouteInterface $router,
        protected Mailer $mailer,
        protected PhpRenderer $renderer,
        protected PaymentFeeServiceInterface $paymentFeeService,
        protected string $cacheDir
    ) {
    }

    /**
     * Create a receipt PDF.
     *
     * @param PaymentEntityInterface $payment       Payment
     * @param array                  $paymentConfig Payment configuration
     *
     * @return array
     */
    public function createReceiptPDF(PaymentEntityInterface $payment, array $paymentConfig): array
    {
        $this->paymentConfig = $paymentConfig;

        $sourceIls = $payment->getSourceIls();
        $contactInfo = $this->getContactInfo($sourceIls);

        $paidDate = $this->dateConverter->convertToDisplayDateAndTime(
            'U',
            $payment->getPaidDate()->getTimestamp()
        );

        $businessId = $paymentConfig['businessId'] ?? '';
        $organizationBusinessIdMappings = [];
        if ($map = $paymentConfig['organizationBusinessIdMappings'] ?? '') {
            foreach (explode(':', $map) as $item) {
                $parts = explode('=', $item, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $organizationBusinessIdMappings[trim($parts[0])] = trim($parts[1]);
            }
        }
        // Check if we have recipient organizations:
        $feeSpecificOrganizations = false;
        $fees = $this->paymentFeeService->getFeesForPayment($payment);
        foreach ($fees as $fee) {
            $feeOrg = $fee->getOrganization();
            if ($feeOrg && ($organizationBusinessIdMappings[$feeOrg] ?? false)) {
                $feeSpecificOrganizations = true;
                break;
            }
        }
        $creator = $this->config['Site']['generator'] ?? 'VuFind';

        $pdfHtml = $this->renderer->partial(
            'OnlinePayment/receipt.phtml',
            compact(
                'payment',
                'paidDate',
                'sourceIls',
                'businessId',
                'contactInfo',
                'creator',
                'fees',
                'paymentConfig',
                'feeSpecificOrganizations',
                'organizationBusinessIdMappings'
            )
        );

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $this->paymentConfig['receiptFormat'] ?? 'A4',
            'tempDir' => $this->cacheDir,
        ]);
        $mpdf->setCreator($creator);
        $mpdf->WriteHTML($pdfHtml);

        if (!($filename = $this->extractFileName($pdfHtml))) {
            $filename = $this->translate('Payment::breakdown_title') . ' - '
                . $payment->getPaidDate()->format('Y-m-d H-i') . '.pdf';
        }
        return [
            'pdf' => $mpdf->OutputBinaryData(),
            'html' => $pdfHtml,
            'filename' => $filename,
        ];
    }

    /**
     * Send receipt by email.
     *
     * @param UserEntityInterface    $user          User
     * @param array                  $patronProfile Patron information
     * @param PaymentEntityInterface $payment       Payment
     * @param array                  $paymentConfig Payment configuration
     *
     * @return bool
     *
     * @todo Add attachment support to Mailer's send method
     */
    public function sendEmail(
        UserEntityInterface $user,
        array $patronProfile,
        PaymentEntityInterface $payment,
        array $paymentConfig
    ): bool {
        $recipients = array_values(
            array_unique(
                array_filter(
                    [
                        trim($patronProfile['email'] ?? ''),
                        trim($user->getEmail()),
                    ]
                )
            )
        );
        if (!$recipients) {
            return false;
        }

        $data = $this->createReceiptPDF($payment, $paymentConfig);

        $this->mailer->setMaxRecipients(2);
        $from = $this->getEmailSenderAddress($this->config);
        $fromOverride = $this->mailer->getFromAddressOverride();

        $replyTo = null;
        if ($fromOverride && $fromOverride !== $from) {
            // Add the original from address as the reply-to address
            $replyTo = $from;
            $from = new Address($from);
            $name = $from->getName();
            if (!$name) {
                [$fromPre] = explode('@', $from->getAddress());
                $name = $fromPre ? $fromPre : null;
            }
            $from = new Address($fromOverride, $name);
        }

        $sourceIls = $payment->getSourceIls();
        $sourceName = $this->getSourceName($payment);
        $contactInfo = $this->getContactInfo($sourceIls);
        $messageContent = $this->renderer->partial(
            'Email/receipt.phtml',
            compact('user', 'patronProfile', 'payment', 'sourceIls', 'sourceName', 'contactInfo')
        );
        $pdf = (new DataPart($data['pdf'], $data['filename'], 'application/pdf'))->asInline();

        $message = $this->mailer->getNewMessage()
            ->text($messageContent)
            ->addPart($pdf);

        $this->mailer->send(
            $recipients,
            $from,
            $this->translate('Payment::breakdown_title') . ' - ' . $this->getSourceName($payment),
            $message,
            replyTo: $replyTo
        );

        return true;
    }

    /**
     * Get source name from payment.
     *
     * @param PaymentEntityInterface $payment Payment
     *
     * @return string
     */
    protected function getSourceName(PaymentEntityInterface $payment): string
    {
        $sourceIls = $payment->getSourceIls();
        return $this->translate('source_' . $sourceIls, [], '');
    }

    /**
     * Get contact information URL or such.
     *
     * @param string $source Source ID
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getContactInfo(string $source): string
    {
        return $this->paymentConfig['contactInfo'] ?? '';
    }

    /**
     * Extract filename from a meta tag.
     *
     * @param string $html HTML page
     *
     * @return string
     */
    protected function extractFileName(string $html): string
    {
        return preg_match('/<meta name="filename" content="([^"]+)">/', $html, $matches)
            ? $matches[1]
            : '';
    }
}
