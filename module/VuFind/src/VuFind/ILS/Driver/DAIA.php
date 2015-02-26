<?php
/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 *
 * PHP version 5
 *
 * Copyright (C) Oliver Goldschmidt 2010.
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
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use DOMDocument, VuFind\Exception\ILS as ILSException;

/**
 * ILS Driver for VuFind to query availability information via DAIA.
 *
 * Based on the proof-of-concept-driver by Till Kinstler, GBV.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class DAIA extends AbstractBase implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Base URL
     *
     * @var string
     */
    protected $baseURL;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (!isset($this->config['Global']['baseUrl'])) {
            throw new ILSException('Global/baseUrl configuration needs to be set.');
        }

        $this->baseURL = $this->config['Global']['baseUrl'];
    }

    /**
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $id      The id of the bib record
     * @param array  $details Item details from getHoldings return array
     *
     * @return string         URL to ILS's OPAC's place hold screen.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHoldLink($id, $details)
    {
        return ($details['ilslink'] != '') ? $details['ilslink'] : null;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        $holding = $this->daiaToHolding($id);
        return $holding;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getShortStatus($id);
        }
        return $items;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        return isset($this->config[$function]) ? $this->config[$function] : false;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        return $this->getStatus($id);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Query a DAIA server and return the result as DOMDocument object.
     * The returned object is an XML document containing
     * content as described in the DAIA format specification.
     *
     * @param string $id Document to look up.
     *
     * @return DOMDocument Object representation of an XML document containing
     * content as described in the DAIA format specification.
     */
    protected function queryDAIA($id)
    {
        $daia = new DOMDocument();
        $daia->load($this->baseURL . $id);

        return $daia;
    }

    /**
     * Flatten a DAIA response to an array of holding information.
     *
     * @param string $id Document to look up.
     *
     * @return array
     */
    protected function daiaToHolding($id)
    {
        $daia = $this->queryDAIA($id);
        // get Availability information from DAIA
        $documentlist = $daia->getElementsByTagName('document');
        $status = [];
        for ($b = 0; $documentlist->item($b) !== null; $b++) {
            $itemlist = $documentlist->item($b)->getElementsByTagName('item');
            $ilslink = '';
            if ($documentlist->item($b)->attributes->getNamedItem('href') !== null) {
                $ilslink = $documentlist->item($b)->attributes
                    ->getNamedItem('href')->nodeValue;
            }
            $emptyResult = [
                    'callnumber' => '-',
                    'availability' => '0',
                    'number' => 1,
                    'reserve' => 'No',
                    'duedate' => '',
                    'queue'   => '',
                    'delay'   => '',
                    'barcode' => 'No samples',
                    'status' => '',
                    'id' => $id,
                    'location' => '',
                    'ilslink' => $ilslink,
                    'label' => 'No samples'
            ];
            for ($c = 0; $itemlist->item($c) !== null; $c++) {
                $result = [
                    'callnumber' => '',
                    'availability' => '0',
                    'number' => ($c+1),
                    'reserve' => 'No',
                    'duedate' => '',
                    'queue'   => '',
                    'delay'   => '',
                    'barcode' => 1,
                    'status' => '',
                    'id' => $id,
                    'item_id' => '',
                    'recallhref' => '',
                    'location' => '',
                    'location.id' => '',
                    'location.href' => '',
                    'label' => '',
                    'notes' => []
                ];
                $result['item_id'] = $itemlist->item($c)->attributes
                    ->getNamedItem('id')->nodeValue;
                if ($itemlist->item($c)->attributes->getNamedItem('href') !== null) {
                    $result['recallhref'] = $itemlist->item($c)->attributes
                        ->getNamedItem('href')->nodeValue;
                }
                $departmentElements = $itemlist->item($c)
                    ->getElementsByTagName('department');
                if ($departmentElements->length > 0) {
                    if ($departmentElements->item(0)->nodeValue) {
                        $result['location']
                            = $departmentElements->item(0)->nodeValue;
                        $result['location.id'] = $departmentElements
                            ->item(0)->attributes->getNamedItem('id')->nodeValue;
                        $result['location.href'] = $departmentElements
                            ->item(0)->attributes->getNamedItem('href')->nodeValue;
                    }
                }
                $storageElements
                    = $itemlist->item($c)->getElementsByTagName('storage');
                if ($storageElements->length > 0) {
                    if ($storageElements->item(0)->nodeValue) {
                        $result['location'] = $storageElements->item(0)->nodeValue;
                        //$result['location.id'] = $storageElements->item(0)
                        //  ->attributes->getNamedItem('id')->nodeValue;
                        $result['location.href'] = $storageElements->item(0)
                            ->attributes->getNamedItem('href')->nodeValue;
                        //$result['barcode'] = $result['location.id'];
                    }
                }
                $barcodeElements
                    = $itemlist->item($c)->getElementsByTagName('identifier');
                if ($barcodeElements->length > 0) {
                    if ($barcodeElements->item(0)->nodeValue) {
                        $result['barcode'] = $barcodeElements->item(0)->nodeValue;
                    }
                }
                $labelElements = $itemlist->item($c)->getElementsByTagName('label');
                if ($labelElements->length > 0) {
                    if ($labelElements->item(0)->nodeValue) {
                        $result['label'] = $labelElements->item(0)->nodeValue;
                        $result['callnumber']
                            = urldecode($labelElements->item(0)->nodeValue);
                    }
                }
                $messageElements
                    = $itemlist->item($c)->getElementsByTagName('message');
                if ($messageElements->length > 0) {
                    for ($m = 0; $messageElements->item($m) !== null; $m++) {
                        $errno = $messageElements->item($m)->attributes
                            ->getNamedItem('errno')->nodeValue;
                        if ($errno === '404') {
                            $result['status'] = 'missing';
                        } else if ($this->logger) {
                            $lang = $messageElements->item($m)->attributes
                                ->getNamedItem('lang')->nodeValue;
                            $logString = "[DAIA] message for {$lang}: "
                                . $messageElements->item($m)->nodeValue;
                            $this->debug($logString);
                        }
                    }
                }

                //$loanAvail = 0;
                //$loanExp = 0;
                //$presAvail = 0;
                //$presExp = 0;

                $unavailableElements = $itemlist->item($c)
                    ->getElementsByTagName('unavailable');
                if ($unavailableElements->item(0) !== null) {
                    for ($n = 0; $unavailableElements->item($n) !== null; $n++) {
                        $service = $unavailableElements->item($n)->attributes
                            ->getNamedItem('service')->nodeValue;
                        $expectedNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('expected');
                        $queueNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('queue');
                        if ($service === 'presentation') {
                            $result['presentation.availability'] = '0';
                            $result['presentation_availability'] = '0';
                            if ($expectedNode !== null) {
                                $result['presentation.duedate']
                                    = $expectedNode->nodeValue;
                            }
                            if ($queueNode !== null) {
                                $result['presentation.queue']
                                    = $queueNode->nodeValue;
                            }
                            $result['availability'] = '0';
                        } elseif ($service === 'loan') {
                            $result['loan.availability'] = '0';
                            $result['loan_availability'] = '0';
                            if ($expectedNode !== null) {
                                $result['loan.duedate'] = $expectedNode->nodeValue;
                            }
                            if ($queueNode !== null) {
                                $result['loan.queue'] = $queueNode->nodeValue;
                            }
                            $result['availability'] = '0';
                        } elseif ($service === 'interloan') {
                            $result['interloan.availability'] = '0';
                            if ($expectedNode !== null) {
                                $result['interloan.duedate']
                                    = $expectedNode->nodeValue;
                            }
                            if ($queueNode !== null) {
                                $result['interloan.queue'] = $queueNode->nodeValue;
                            }
                            $result['availability'] = '0';
                        } elseif ($service === 'openaccess') {
                            $result['openaccess.availability'] = '0';
                            if ($expectedNode !== null) {
                                $result['openaccess.duedate']
                                    = $expectedNode->nodeValue;
                            }
                            if ($queueNode !== null) {
                                $result['openaccess.queue'] = $queueNode->nodeValue;
                            }
                            $result['availability'] = '0';
                        }
                        // TODO: message/limitation
                        if ($expectedNode !== null) {
                            $result['duedate'] = $expectedNode->nodeValue;
                        }
                        if ($queueNode !== null) {
                            $result['queue'] = $queueNode->nodeValue;
                        }
                    }
                }

                $availableElements = $itemlist->item($c)
                    ->getElementsByTagName('available');
                if ($availableElements->item(0) !== null) {
                    for ($n = 0; $availableElements->item($n) !== null; $n++) {
                        $service = $availableElements->item($n)->attributes
                            ->getNamedItem('service')->nodeValue;
                        $delayNode = $availableElements->item($n)->attributes
                            ->getNamedItem('delay');
                        if ($service === 'presentation') {
                            $result['presentation.availability'] = '1';
                            $result['presentation_availability'] = '1';
                            if ($delayNode !== null) {
                                $result['presentation.delay']
                                    = $delayNode->nodeValue;
                            }
                            $result['availability'] = '1';
                        } elseif ($service === 'loan') {
                            $result['loan.availability'] = '1';
                            $result['loan_availability'] = '1';
                            if ($delayNode !== null) {
                                $result['loan.delay'] = $delayNode->nodeValue;
                            }
                            $result['availability'] = '1';
                        } elseif ($service === 'interloan') {
                            $result['interloan.availability'] = '1';
                            if ($delayNode !== null) {
                                $result['interloan.delay'] = $delayNode->nodeValue;
                            }
                            $result['availability'] = '1';
                        } elseif ($service === 'openaccess') {
                            $result['openaccess.availability'] = '1';
                            if ($delayNode !== null) {
                                $result['openaccess.delay'] = $delayNode->nodeValue;
                            }
                            $result['availability'] = '1';
                        }
                        // TODO: message/limitation
                        if ($delayNode !== null) {
                            $result['delay'] = $delayNode->nodeValue;
                        }
                    }
                }
                // document has no availability elements, so set availability
                // and barcode to -1
                if ($availableElements->item(0) === null
                    && $unavailableElements->item(0) === null
                ) {
                    $result['availability'] = '-1';
                    $result['barcode'] = '-1';
                }
                $result['ilslink'] = $ilslink;
                $status[] = $result;
                /* $status = "available";
                if (loanAvail) return 0;
                if (presAvail) {
                    if (loanExp) return 1;
                    return 2;
                }
                if (loanExp) return 3;
                if (presExp) return 4;
                return 5;
                */
            }
            if (count($status) === 0) {
                $status[] = $emptyResult;
            }
        }
        return $status;
    }

    /**
     * Return an abbreviated set of status information.
     *
     * @param string $id The record id to retrieve the status for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number
     */
    public function getShortStatus($id)
    {
        $daia = $this->queryDAIA($id);
        // get Availability information from DAIA
        $itemlist = $daia->getElementsByTagName('item');
        $label = "Unknown";
        $storage = "Unknown";
        $presenceOnly = '1';
        $holding = [];
        for ($c = 0; $itemlist->item($c) !== null; $c++) {
            $earliest_href = '';
            $storageElements = $itemlist->item($c)->getElementsByTagName('storage');
            if ($storageElements->item(0)->nodeValue) {
                if ($storageElements->item(0)->nodeValue === 'Internet') {
                    $href = $storageElements->item(0)->attributes
                        ->getNamedItem('href')->nodeValue;
                    $storage = '<a href="' . $href . '">' . $href . '</a>';
                } else {
                    $storage = $storageElements->item(0)->nodeValue;
                }
            }
            $labelElements = $itemlist->item($c)->getElementsByTagName('label');
            if ($labelElements->item(0)->nodeValue) {
                $label = $labelElements->item(0)->nodeValue;
            }
            $availableElements = $itemlist->item($c)
                ->getElementsByTagName('available');
            if ($availableElements->item(0) !== null) {
                $availability = 1;
                $status = 'Available';
                $href = $availableElements->item(0)->attributes
                    ->getNamedItem('href');
                if ($href !== null) {
                    $earliest_href = $href->nodeValue;
                }
                for ($n = 0; $availableElements->item($n) !== null; $n++) {
                    $svc = $availableElements->item($n)->getAttribute('service');
                    if ($svc === 'loan') {
                        $presenceOnly = '0';
                    }
                    // $status .= ' ' . $svc;
                }
            } else {
                $leanable = 1;
                $unavailableElements = $itemlist->item($c)
                    ->getElementsByTagName('unavailable');
                if ($unavailableElements->item(0) !== null) {
                    $earliest = [];
                    $queue = [];
                    $hrefs = [];
                    for ($n = 0; $unavailableElements->item($n) !== null; $n++) {
                        $unavailHref = $unavailableElements->item($n)->attributes
                            ->getNamedItem('href');
                        if ($unavailHref !== null) {
                            $hrefs['item' . $n] = $unavailHref->nodeValue;
                        }
                        $expectedNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('expected');
                        if ($expectedNode !== null) {
                            //$duedate = $expectedNode->nodeValue;
                            //$duedate_arr = explode('-', $duedate);
                            //$duedate_timestamp = mktime(
                            //    '0', '0', '0', $duedate_arr[1], $duedate_arr[2],
                            //    $duedate_arr[0]
                            //);
                            //array_push($earliest, array(
                            //    'expected' => $expectedNode->nodeValue,
                            //    'recall' => $unavailHref->nodeValue);
                            //array_push($earliest, $expectedNode->nodeValue);
                            $earliest['item' . $n] = $expectedNode->nodeValue;
                        } else {
                            array_push($earliest, "0");
                        }
                        $queueNode = $unavailableElements->item($n)->attributes
                            ->getNamedItem('queue');
                        if ($queueNode !== null) {
                            $queue['item' . $n] = $queueNode->nodeValue;
                        } else {
                            array_push($queue, "0");
                        }
                    }
                }
                if (count($earliest) > 0) {
                    arsort($earliest);
                    $earliest_counter = 0;
                    foreach ($earliest as $earliest_key => $earliest_value) {
                        if ($earliest_counter === 0) {
                            $earliest_duedate = $earliest_value;
                            $earliest_href = $hrefs[$earliest_key];
                            $earliest_queue = $queue[$earliest_key];
                        }
                        $earliest_counter = 1;
                    }
                } else {
                    $leanable = 0;
                }
                $messageElements = $itemlist->item($c)
                    ->getElementsByTagName('message');
                if ($messageElements->length > 0) {
                    $errno = $messageElements->item(0)->attributes
                        ->getNamedItem('errno')->nodeValue;
                    if ($errno === '404') {
                        $status = 'missing';
                    }
                }
                if (!$status) {
                    $status = 'Unavailable';
                }
                $availability = 0;
            }
            $reserve = 'N';
            if ($earliest_queue > 0) {
                $reserve = 'Y';
            }
            $holding[] = ['availability' => $availability,
                   'id' => $id,
                   'status' => "$status",
                   'location' => "$storage",
                   'reserve' => $reserve,
                   'queue' => $earliest_queue,
                   'callnumber' => "$label",
                   'duedate' => $earliest_duedate,
                   'leanable' => $leanable,
                   'recallhref' => $earliest_href,
                   'number' => ($c+1),
                   'presenceOnly' => $presenceOnly];
        }
        return $holding;
    }
}