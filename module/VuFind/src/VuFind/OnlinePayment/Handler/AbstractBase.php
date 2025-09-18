<?php

/**
 * Abstract payment handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2025.
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
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment\Handler;

use Laminas\Http\PhpEnvironment\Response;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFindHttp\HttpService;

use function count;
use function is_array;
use function is_object;

/**
 * Abstract payment handler
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
abstract class AbstractBase implements
    HandlerInterface,
    LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\OnlinePayment\OnlinePaymentEventTrait;

    /**
     * Result codes for processPaymentResponse
     *
     * @var int
     */
    public const PAYMENT_SUCCESS = 0; // Payment successful
    public const PAYMENT_CANCEL = 1;  // Payment canceled
    public const PAYMENT_FAILURE = 2; // Payment failed
    public const PAYMENT_PENDING = 3; // Payment still in progress (local status won't be updated)

    /**
     * Payment Configuration.
     *
     * @var array
     */
    protected array $paymentConfig = [];

    /**
     * Basic mappings from fine types to product codes
     *
     * @var array
     */
    protected array $productCodeMappings = [];

    /**
     * Fine organization-specific mappings from fine types to product codes
     *
     * @var array
     */
    protected array $organizationProductCodePrefixMappings = [];

    /**
     * Constructor
     *
     * @param array                      $config               VuFind configuration
     * @param HttpService                $httpService          HTTP service
     * @param LocaleSettings             $localeSettings       Locale settings
     * @param OnlinePaymentManager       $onlinePaymentManager Online payment manager
     * @param AuditEventServiceInterface $auditEventService    Audit event log database service
     */
    public function __construct(
        protected array $config,
        protected HttpService $httpService,
        protected LocaleSettings $localeSettings,
        protected OnlinePaymentManager $onlinePaymentManager,
        AuditEventServiceInterface $auditEventService
    ) {
        $this->auditEventService = $auditEventService;
    }

    /**
     * Initialize the handler
     *
     * @param array $paymentConfig Online payment configuration
     *
     * @return void
     */
    public function init(array $paymentConfig): void
    {
        $this->paymentConfig = $paymentConfig;

        $this->productCodeMappings = $this->parseMappings($this->paymentConfig['productCodeMappings'] ?? '');
        $this->organizationProductCodePrefixMappings
            = $this->parseMappings($this->paymentConfig['organizationProductCodePrefixMappings'] ?? '');
    }

    /**
     * Return name of handler.
     *
     * @return string name
     */
    public function getName(): string
    {
        return $this->paymentConfig['handler'];
    }

    /**
     * Generate the internal payment transaction identifier.
     *
     * @param array $patron Patron
     *
     * @return string
     */
    protected function generateLocalIdentifier(array $patron): string
    {
        return md5($patron['cat_username'] . '_' . microtime(true));
    }

    /**
     * Add query parameters to an url
     *
     * @param string $url    URL
     * @param array  $params Parameters to add
     *
     * @return string
     */
    protected function addQueryParams(string $url, array $params): string
    {
        $url .= !str_contains($url, '?') ? '?' : '&';
        $url .= http_build_query($params);
        return $url;
    }

    /**
     * Store payment to database.
     *
     * @param string              $localIdentifier  Local payment identifier
     * @param ?string             $remoteIdentifier Handler's payment identifier
     * @param UserEntityInterface $user             User
     * @param array               $patron           Patron
     * @param int                 $amount           Amount (excluding service fee)
     * @param array               $fines            Fines data
     *
     * @return PaymentEntityInterface
     */
    protected function createPaymentEntity(
        string $localIdentifier,
        ?string $remoteIdentifier,
        UserEntityInterface $user,
        array $patron,
        int $amount,
        array $fines
    ): PaymentEntityInterface {
        return $this->onlinePaymentManager->createPaymentEntity(
            $localIdentifier,
            $remoteIdentifier,
            $user,
            $patron,
            $amount,
            $this->getCurrencyCode(),
            $this->getServiceFee(),
            $fines
        );
    }

    /**
     * Redirect to payment handler.
     *
     * @param string                 $url     URL
     * @param PaymentEntityInterface $payment Payment
     *
     * @return Response
     */
    protected function redirectToPayment(string $url, PaymentEntityInterface $payment): Response
    {
        $response = new Response();
        $response->getHeaders()->addHeaderLine('Location', $url);
        $response->setStatusCode(302);
        $this->addPaymentEvent($payment, AuditEventSubtype::Payment, 'Redirected to payment gateway');
        return $response;
    }

    /**
     * Parse a mappings configuration to an array
     *
     * @param string $mappings Mappings
     *
     * @return array
     */
    protected function parseMappings(string $mappings): array
    {
        if (!$mappings) {
            return [];
        }
        $result = [];
        foreach (explode(':', $mappings) as $item) {
            $parts = explode('=', $item, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ('' !== $key && '' !== $value) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Log an error
     *
     * @param string $msg  Error message
     * @param array  $data Additional data to log
     *
     * @return void
     */
    protected function logPaymentError(string $msg, array $data = []): void
    {
        $msg = "Online payment: $msg";
        if ($data) {
            $msg .= ". Additional data:\n" . $this->dumpData($data);
        }
        $this->logError($msg);
    }

    /**
     * Dump a data array with mixed content
     *
     * @param array $data  Data array
     * @param int   $level Indentation level
     *
     * @return string
     */
    protected function dumpData(array $data, int $level = 0): string
    {
        // Don't go too deep:
        if ($level > 3) {
            return '';
        }

        $results = [];
        $indent = str_repeat('  ', $level);

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $value = $value->toArray();
                } else {
                    $key = "$key: " . $value::class;
                    $value = get_object_vars($value);
                }
            }
            if (is_array($value)) {
                $results[] = "$key: {\n"
                    . $this->dumpData($value, $level + 1)
                    . "\n$indent}";
            } else {
                $results[] = "$key: " . var_export($value, true);
            }
        }

        return $indent . implode(",\n$indent", $results);
    }

    /**
     * Get user's locale string (e.g. 'en' or 'en-GB')
     *
     * @return string
     */
    protected function getCurrentLocale(): string
    {
        $parts = explode('-', $this->localeSettings->getUserLocale(), 2);
        return isset($parts[1]) ? ($parts[0] . '-' . mb_strtoupper($parts[1], 'UTF-8')) : $parts[0];
    }

    /**
     * Get two character language code from user's current locale
     *
     * @return string
     */
    protected function getCurrentLanguageCode(): string
    {
        [$lang] = explode('-', $this->getCurrentLocale(), 2);
        return $lang;
    }

    /**
     * Get the currency code
     *
     * @return string
     */
    protected function getCurrencyCode(): string
    {
        return $this->paymentConfig['currency'] ?? 'USD';
    }

    /**
     * Get service fee
     *
     * @return int
     */
    protected function getServiceFee(): int
    {
        return (int)($this->paymentConfig['serviceFee'] ?? 0);
    }

    /**
     * Get the default product code
     *
     * @return ?string
     */
    protected function getDefaultProductCode(): ?string
    {
        return $this->paymentConfig['productCode'] ?? null;
    }

    /**
     * Get the service fee product code
     *
     * @return ?string
     */
    protected function getServiceFeeProductCode(): ?string
    {
        return $this->paymentConfig['serviceFeeProductCode'] ?? null;
    }

    /**
     * Get the service fee tax rate
     *
     * @return ?int Tax rate percent (1/100ths of a percent) or null if not defined
     */
    protected function getServiceFeeTaxRate(): ?int
    {
        return $this->paymentConfig['serviceFeeTaxRate'] ?? null;
    }

    /**
     * Get source ILS for patron
     *
     * @param array $patron Patron
     *
     * @return string
     */
    protected function getSourceIls(array $patron): string
    {
        return $patron['__source'] ?? 'default';
    }

    /**
     * Get a product code for a fine
     *
     * @param array $fine Fine
     *
     * @return ?string
     */
    protected function getFineProductCode(array $fine): ?string
    {
        // If we don't have any mappings, assume no products:
        if (
            !$this->productCodeMappings
            && !$this->organizationProductCodePrefixMappings
            && !$this->getDefaultProductCode()
            && !isset($fine['productCode'])
        ) {
            return null;
        }

        $fineType = $fine['fine'] ?? '';

        // Determine product code:
        $code = $fine['productCode'] ?? null;
        if (null === $code) {
            $code = $this->productCodeMappings[$fineType] ?? null;
        }
        if (null === $code) {
            $code = $this->getDefaultProductCode();
        }
        if (null === $code) {
            $code = $fineType;
        }

        // Add any organization prefix:
        $fineOrg = $fine['organization'] ?? '';
        if (null !== ($orgProductCode = $this->organizationProductCodePrefixMappings[$fineOrg] ?? null)) {
            $code = $orgProductCode . $code;
        }

        return $code;
    }

    /**
     * Get fine description
     *
     * Description includes fine type and record title
     *
     * @param array $fine      Fine
     * @param int   $maxLength Maximum length of the description
     *
     * @return string
     */
    protected function getFineDescription(array $fine, int $maxLength): string
    {
        if ('' !== ($fineDesc = $fine['description'] ?? '')) {
            return mb_substr($fineDesc, 0, $maxLength, 'UTF-8');
        }

        $fineType = $fine['fine'] ?? '';
        if ('' !== $fineType) {
            $fineDesc = mb_substr($this->translator->translate($fineType), 0, $maxLength, 'UTF-8');
        }
        if ('' !== ($title = $fine['title'] ?? '')) {
            $title = mb_substr(
                $title,
                0,
                $maxLength - 4 - mb_strlen($fineDesc, 'UTF-8'),
                'UTF-8'
            );
            $fineDesc .= " ($title)";
        }
        return $fineDesc;
    }
}
