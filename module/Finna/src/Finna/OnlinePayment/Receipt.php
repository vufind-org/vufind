<?php

/**
 * Online payment receipt
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\OnlinePayment;

use Finna\Db\Entity\FinnaFeeEntityInterface;
use Finna\Db\Entity\FinnaTransactionEntityInterface;
use Finna\Db\Service\FinnaTransactionServiceInterface;
use Laminas\Router\RouteInterface;
use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use TCPDF;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Mailer\Mailer;
use VuFind\Service\CurrencyFormatter;

use function count;

/**
 * Online payment service
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Receipt implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Left margin of PDF (millimeters)
     *
     * @var int
     */
    protected $left = 10;

    /**
     * Max x position of PDF (millimeters)
     *
     * @var int
     */
    protected $right = 200;

    /**
     * Max y position of PDF (millimeters)
     */
    protected $bottom = 280;

    /**
     * Constructor.
     *
     * @param array                            $config             Main configuration
     * @param array                            $dataSourceConfig   Data source configuration
     * @param DateConverter                    $dateConverter      Date converter
     * @param CurrencyFormatter                $currencyFormatter  Currency formatter
     * @param RouteInterface                   $router             Router
     * @param Mailer                           $mailer             Mailer
     * @param PhpRenderer                      $renderer           View renderer
     * @param FinnaTransactionServiceInterface $transactionService Transaction database service
     */
    public function __construct(
        protected array $config,
        protected array $dataSourceConfig,
        protected DateConverter $dateConverter,
        protected CurrencyFormatter $currencyFormatter,
        protected RouteInterface $router,
        protected Mailer $mailer,
        protected PhpRenderer $renderer,
        protected FinnaTransactionServiceInterface $transactionService
    ) {
    }

    /**
     * Create a receipt PDF
     *
     * @param FinnaTransactionEntityInterface $transaction Transaction
     *
     * @return array
     */
    public function createReceiptPDF(FinnaTransactionEntityInterface $transaction): array
    {
        $source = $this->getSource($transaction);
        $sourceName = $this->getSourceName($transaction);
        $contactInfo = $this->getContactInfo($source);

        $paidDate = $this->dateConverter->convertToDisplayDateAndTime(
            'U',
            $transaction->getPaidDate()->getTimestamp()
        );

        $dsConfig = $this->dataSourceConfig[$source] ?? [];
        $businessId = $dsConfig['onlinePayment']['businessId'] ?? '';
        $organizationBusinessIdMappings = [];
        if ($map = $dsConfig['onlinePayment']['organizationBusinessIdMappings'] ?? '') {
            foreach (explode(':', $map) as $item) {
                $parts = explode('=', $item, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $organizationBusinessIdMappings[trim($parts[0])] = trim($parts[1]);
            }
        }
        // Check if we have recipient organizations:
        $hasFineOrgs = false;
        $fines = $this->transactionService->getFines($transaction);
        foreach ($fines as $fine) {
            $fineOrg = $fine->getOrganization();
            if ($fineOrg && ($organizationBusinessIdMappings[$fineOrg] ?? false)) {
                $hasFineOrgs = true;
                break;
            }
        }

        $heading = $this->translate('Payment::breakdown_title') . " - $sourceName";
        [$language] = explode('-', $this->getTranslatorLocale(), 2);
        $languageConfig = [
            'a_meta_charset' => 'utf-8',
            'a_meta_dir' => 'ltr',
            'a_meta_language' => $language,
            'w_page' => 'page',
        ];
        $pdf = new TCPDF();
        $pdf->setLanguageArray($languageConfig);
        $pdf->SetCreator('Finna');
        $pdf->SetLanguageArray(
            [
                'a_meta_charset' => 'UTF-8',
                'a_meta_dir' => 'ltr',
                'a_meta_language' => $this->getTranslatorLocale(),
                'w_page' => $this->translate('page_num', ['%%page%%' => '']),
            ]
        );
        $pdf->SetTitle($heading . ' - ' . $paidDate);
        $pdf->SetMargins($this->left, 18);
        $pdf->SetHeaderMargin(10);
        $pdf->SetHeaderData('', 0, $heading);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Print information array:
        $pdf->setY(25);
        if (!$hasFineOrgs) {
            $this->addInfo($pdf, 'Payment::Recipient', $sourceName . ($businessId ? " ($businessId)" : ''));
        }
        $this->addInfo($pdf, 'Payment::Date', $paidDate);
        $this->addInfo($pdf, 'Payment::Identifier', $transaction->getTransactionIdentifier());
        if ($contactInfo) {
            $this->addInfo($pdf, 'Payment::Contact Information', $contactInfo);
        }

        // Print lines:
        $pdf->SetY($pdf->GetY() + 10);
        $this->addHeaders($pdf, $hasFineOrgs);
        // Account for the "Total" line:
        $linesBottom = $this->bottom - 7;
        foreach ($fines as $fine) {
            $savePDF = clone $pdf;

            $fineOrg = $fine->getOrganization();
            $lineBusinessId = $fineOrg ? ($organizationBusinessIdMappings[$fineOrg] ?? '') : '';
            $this->addLine($pdf, $fine, $source, $sourceName, $businessId, $lineBusinessId, $hasFineOrgs);
            // If we exceed bottom, revert and add a new page:
            if ($pdf->GetY() > $linesBottom) {
                $pdf = $savePDF;
                $pdf->AddPage();
                $pdf->SetY(25);
                $this->addHeaders($pdf, $hasFineOrgs);
                $this->addLine($pdf, $fine, $source, $sourceName, $businessId, $lineBusinessId, $hasFineOrgs);
            }
        }
        $pdf->SetY($pdf->GetY() + 1);
        $pdf->SetFont('helvetica', 'B', 10);
        $amount = $this->currencyFormatter->convertToDisplayFormat(
            $transaction->getAmount() / 100.00,
            $transaction->getCurrency()
        );
        $pdf->Cell(
            190,
            0,
            $this->translate('Payment::Total') . " $amount",
            0,
            1,
            'R'
        );

        // Print VAT summary:
        $savePDF = clone $pdf;
        $this->addVATSummary($pdf, $transaction);
        if ($pdf->GetY() > $this->bottom) {
            $pdf = $savePDF;
            $pdf->AddPage();
            $this->addVATSummary($pdf, $transaction);
        }

        return [
            'pdf' => $pdf->getPDFData(),
            'filename' => $heading . ' - ' . $transaction->getPaidDate()->format('Y-m-d H-i'),
        ];
    }

    /**
     * Send receipt by email
     *
     * @param UserEntityInterface             $user          User
     * @param array                           $patronProfile Patron information
     * @param FinnaTransactionEntityInterface $transaction   Transaction
     *
     * @return bool
     *
     * @todo Add attachment support to Mailer's send method
     */
    public function sendEmail(
        UserEntityInterface $user,
        array $patronProfile,
        FinnaTransactionEntityInterface $transaction
    ): bool {
        $recipients = array_unique(
            array_filter(
                [
                    trim($patronProfile['email'] ?? ''),
                    trim($user->getEmail()),
                ]
            )
        );
        if (!$recipients) {
            return false;
        }

        $data = $this->createReceiptPDF($transaction);

        $this->mailer->setMaxRecipients(2);
        $from = $this->config['Mail']['default_from'] ?? $this->config['Site']['email'];
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

        $source = $this->getSource($transaction);
        $sourceName = $this->getSourceName($transaction);
        $contactInfo = $this->getContactInfo($source);
        $messageContent = $this->renderer->partial(
            'Email/receipt.phtml',
            compact('user', 'patronProfile', 'transaction', 'source', 'sourceName', 'contactInfo')
        );
        $pdf = (new DataPart($data['pdf'], $data['filename'], 'application/pdf'))->asInline();

        $message = $this->mailer->getNewMessage()
            ->addFrom($from)
            ->addTo(...$recipients)
            ->subject($this->translate('Payment::breakdown_title') . ' - ' . $this->getSourceName($transaction))
            ->text($messageContent)
            ->addPart($pdf);
        if ($replyTo) {
            $message->addReplyTo($replyTo);
        }

        $this->mailer->getTransport()->send($message);
        return true;
    }

    /**
     * Add info row
     *
     * @param TCPDF  $pdf     PDF
     * @param string $heading Heading
     * @param string $value   Value
     *
     * @return void
     */
    protected function addInfo($pdf, $heading, $value): void
    {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 0, $this->translate($heading));
        $pdf->SetFont('helvetica', '', 10);
        if (preg_match('/^https?:\/\/([^\s]+)$/', $value, $matches)) {
            // Create link:
            $pdf->Write(0, $matches[1], $value);
        } else {
            $pdf->Cell(120, 0, $value);
        }
        $pdf->Ln();
    }

    /**
     * Add item table headers
     *
     * @param TCPDF $pdf       PDF
     * @param bool  $recipient Whether to add recipient column
     *
     * @return void
     */
    protected function addHeaders(TCPDF $pdf, bool $recipient): void
    {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 0, $this->translate('Payment::Identifier'), 0, 0);
        $pdf->Cell(40, 0, $this->translate('Payment::Type'), 0, 0);
        $pdf->Cell($recipient ? 50 : 100, 0, $this->translate('Payment::Details'), 0, 0);
        if ($recipient) {
            $pdf->Cell(50, 0, $this->translate('Payment::Recipient'), 0, 0);
        }
        $pdf->Cell(20, 0, $this->translate('Payment::Fee'), 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);
        $y = $pdf->GetY() + 1;
        $pdf->Line($this->left, $y, $this->right, $y);
        $pdf->SetY($y + 1);
    }

    /**
     * Add item table line
     *
     * @param TCPDF  $pdf            PDF
     * @param Fee    $fine           Fee or fine
     * @param string $source         Source ID
     * @param string $sourceName     Source name
     * @param string $businessId     Source business ID
     * @param string $lineBusinessId Line business ID
     * @param bool   $recipient      Whether to add recipient column
     *
     * @return void
     */
    protected function addLine(
        TCPDF $pdf,
        FinnaFeeEntityInterface $fine,
        string $source,
        string $sourceName,
        string $businessId,
        string $lineBusinessId,
        bool $recipient
    ): void {
        $type = $fine->getType();
        $type = $this->translate("fine_status_$type", [], $this->translate("status_$type", [], $type));

        $descriptions = [];
        if ($desc = $fine->getDescription()) {
            $descriptions[] = $desc;
        }
        if ($title = $fine->getTitle()) {
            $descriptions[] = $title;
        }

        $curY = $pdf->GetY();

        $pdf->MultiCell(28, 0, $fine->getFineId(), 0, 'L');
        $nextY = $pdf->GetY();

        $pdf->SetXY($this->left + 30, $curY);
        $pdf->MultiCell(38, 0, $type, 0, 'L');
        $nextY = max($nextY, $pdf->GetY());

        $pdf->SetXY($this->left + 70, $curY);
        $pdf->MultiCell($recipient ? 48 : 98, 0, implode(' - ', $descriptions), 0, 'L');
        $nextY = max($nextY, $pdf->GetY());

        if ($recipient) {
            if (($fineOrg = $fine->getOrganization()) && $lineBusinessId) {
                $recipient = $this->translate("Payment::organisation_{$source}_{$fineOrg}", [], $fineOrg)
                    . " ($lineBusinessId)";
            } else {
                $recipient = $sourceName . ($businessId ? " ($businessId)" : '');
            }
            $pdf->SetXY($this->left + 120, $curY);
            $pdf->MultiCell(48, 0, $recipient, 0, 'L');
            $nextY = max($nextY, $pdf->GetY());
        }

        $pdf->SetXY($this->left + 170, $curY);
        $pdf->Cell(
            20,
            0,
            $this->currencyFormatter->convertToDisplayFormat($fine->getAmount() / 100.00, $fine->getCurrency()),
            0,
            0,
            'R'
        );
        $pdf->setY($nextY + 2);
    }

    /**
     * Add VAT summary
     *
     * @param TCPDF       $pdf         PDF
     * @param Transaction $transaction Transaction
     *
     * @return void
     */
    protected function addVATSummary(TCPDF $pdf, FinnaTransactionEntityInterface $transaction): void
    {
        $amount = $this->currencyFormatter->convertToDisplayFormat(
            $transaction->getAmount() / 100.00,
            $transaction->getCurrency()
        );

        $pdf->SetY($pdf->GetY() + 15);
        $pdf->SetFont('helvetica', 'B', 10);
        $vatLeft = $this->left + 50;
        $pdf->SetX($vatLeft);
        $pdf->Cell(30, 0, $this->translate('Payment::VAT Breakdown'), 0, 0, 'L');
        $pdf->Cell(20, 0, $this->translate('Payment::VAT Percent'));
        $pdf->Cell(30, 0, $this->translate('Payment::Excluding VAT'), 0, 0, 'R');
        $pdf->Cell(30, 0, $this->translate('Payment::VAT'), 0, 0, 'R');
        $pdf->Cell(30, 0, $this->translate('Payment::Including VAT'), 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetX($vatLeft);
        $y = $pdf->GetY() + 1;
        $pdf->Line($vatLeft, $y, $vatLeft + 30, $y, ['dash' => '1,2']);
        $pdf->Line($vatLeft + 30, $y, $this->right, $y, ['dash' => 0]);
        $pdf->SetXY($vatLeft + 30, $y + 1);
        $pdf->Cell(20, 0, '0 %');
        $pdf->Cell(30, 0, $amount, 0, 0, 'R');
        $pdf->Cell(30, 0, $this->currencyFormatter->convertToDisplayFormat(0, $transaction->getCurrency()), 0, 0, 'R');
        $pdf->Cell(30, 0, $amount, 0, 1, 'R');
    }

    /**
     * Get source identifier from transaction
     *
     * @param Transaction $transaction Transaction
     *
     * @return string
     */
    protected function getSource(FinnaTransactionEntityInterface $transaction): string
    {
        [$source] = explode('.', $transaction->getCatUsername());
        return $source;
    }

    /**
     * Get source name from transaction
     *
     * @param FinnaTransactionEntityInterface $transaction Transaction
     *
     * @return string
     */
    protected function getSourceName(FinnaTransactionEntityInterface $transaction): string
    {
        $source = $this->getSource($transaction);
        return $this->translate('source_' . $source, [], $source);
    }

    /**
     * Get contact information URL or such
     *
     * @param string $source Source ID
     *
     * @return string
     */
    protected function getContactInfo(string $source): string
    {
        $dsConfig = $this->dataSourceConfig[$source] ?? [];
        if ($orgId = $dsConfig['onlinePayment']['organisationInfoId'] ?? '') {
            return $this->router->assemble(
                [],
                [
                    'name' => 'organisationinfo-home',
                    'query' => [
                        'id' => $orgId,
                    ],
                    'force_canonical' => true,
                ]
            );
        }
        return $dsConfig['onlinePayment']['contactInfo'] ?? '';
    }
}
