<?php

/**
 * GetCheckoutHistoryFile AJAX handler
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Exception;
use Laminas\Mvc\Controller\Plugin\Params;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * GetCheckoutHistoryFile AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetCheckoutHistoryFile extends GetCheckoutHistory
{
    /**
     * Options for the file format to be requested.
     *
     * @var array
     */
    protected $exportFormats = [
        'xlsx' => [
            'mediaType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'writer' => Xlsx::class,
        ],
        'ods' => [
            'mediaType' => 'application/vnd.oasis.opendocument.spreadsheet',
            'writer' => Ods::class,
        ],
        'csv' => [
            'mediaType' => 'text/csv',
            'writer' => Csv::class,
        ],
    ];

    /**
     * Helper boolean for checking if this returns stream as a response
     *
     * @var bool
     */
    public bool $supportsStream = true;

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $result = $this->getCheckoutHistoryResult();
        if ($result['success'] === false) {
            return $this->formatResponse($result['message'], $result['status']);
        }
        try {
            // Get requested history part as a file to be downloaded
            $part = $params->fromQuery('part', 1);
            $fileFormat = $params->fromQuery('format', 'csv');
            $calculatedResults = $this->calculateLimitsFromResult($result);
            return $this->getHistoryAsFile(
                $part,
                $calculatedResults['pageLimit'],
                $calculatedResults['pageCount'],
                $fileFormat
            );
        } catch (Exception $e) {
            return $this->formatResponse(
                $this->translate('An error has occurred'),
                self::STATUS_HTTP_ERROR
            );
        }
    }

    /**
     * Create a file for transaction history
     *
     * @param int    $part       Part of the transaction history to download
     * @param int    $limit      Limit for how many transactions one fetch from ils fetches
     * @param int    $pagesCount Total amount of pages the user has in history
     * @param string $fileFormat Format of the file to generate
     *
     * @return array [fileName => name of the file, mediaType => media type, filePointer => pointer for the resource]
     */
    private function getHistoryAsFile(
        int $part = 1,
        int $limit = 50,
        int $pagesCount = 1,
        string $fileFormat = 'csv'
    ): array {
        // Calculate how many times required to fetch from ILS to achieve the $batchLimit
        $pagesToFetch = 1;
        $firstPageToFetch = 1;
        $lastPageToFetch = 1;
        if ($pagesCount > 1) {
            $pagesToFetch = ceil($this->batchLimit / $limit);
            $firstPageToFetch += ($pagesToFetch * ($part - 1));
            $lastPageToFetch += min(($pagesToFetch * $part) - 1, $pagesCount);
        }
        $tmpPath = 'php://temp/maxmemory:' . (5 * 1024 * 1024);
        $tmp = fopen($tmpPath, 'r+');

        $transactions = [];
        for ($i = $firstPageToFetch; $i <= $lastPageToFetch; $i++) {
            $result = $this->getCheckoutHistoryResult($i, $limit);
            if ($result['success'] === false) {
                fclose($tmp);
                return $this->formatResponse($result['message'], $result['status']);
            }
            // Break if no transactions found
            if (empty($result['function_result']['transactions'])) {
                break;
            }
            $transactions = [...$transactions, ...$result['function_result']['transactions']];
        }
        $ids = [];
        foreach ($transactions as $current) {
            $id = $current['id'] ?? '';
            $source = $current['source'] ?? DEFAULT_SEARCH_BACKEND;
            $ids[] = compact('id', 'source');
        }
        $records = $this->recordLoader->loadBatch($ids, true);
        $header = [
            $this->translate('Title'),
            $this->translate('Format'),
            $this->translate('Author'),
            $this->translate('Publication Year'),
            $this->translate('Institution'),
            $this->translate('Borrowing Location'),
            $this->translate('Checkout Date'),
            $this->translate('Return Date'),
            $this->translate('Due Date'),
        ];
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray($header);

        if ('xlsx' === $fileFormat) {
            Cell::setValueBinder(new AdvancedValueBinder());
        }

        foreach ($transactions as $i => $current) {
            $driver = $records[$i];
            $format = $driver->getFormats();
            $format = end($format);
            $author = $driver->tryMethod('getNonPresenterAuthors');

            $loan = [];
            $loan[] = $current['title'] ?? $driver->getTitle() ?? '';
            $loan[] = $this->translate($format);
            $loan[] = $author[0]['name'] ?? '';
            $loan[] = $current['publication_year'] ?? '';
            $loan[] = empty($current['institution_name'])
                ? ''
                : $this->translateWithPrefix('location_', $current['institution_name']);
            $loan[] = empty($current['borrowingLocation'])
                ? ''
                : $this->translateWithPrefix('location_', $current['borrowingLocation']);
            $loan[] = $current['checkoutDate'] ?? '';
            $loan[] = $current['returnDate'] ?? '';
            $loan[] = $current['dueDate'] ?? '';

            $nextRow = $worksheet->getHighestRow() + 1;
            $worksheet->fromArray($loan, null, 'A' . (string)$nextRow);
        }
        if ('xlsx' === $fileFormat) {
            $worksheet->getStyle('G2:I' . $worksheet->getHighestRow())
                ->getNumberFormat()
                ->setFormatCode('dd.mm.yyyy');
            foreach (['G', 'H', 'I'] as $col) {
                $worksheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $writer = new $this->exportFormats[$fileFormat]['writer']($spreadsheet);
        $writer->save($tmp);
        $fileName = 'finna-loan-history-parts-' . $firstPageToFetch;
        if ($firstPageToFetch !== $lastPageToFetch) {
            $fileName .= '-' . $lastPageToFetch;
        }
        $fileName .= ".$fileFormat";

        rewind($tmp);

        return $this->formatResponse([
            'fileName' => $fileName,
            'mediaType' => $this->exportFormats[$fileFormat]['mediaType'],
            'filePointer' => $tmp,
        ], 200);
    }
}
