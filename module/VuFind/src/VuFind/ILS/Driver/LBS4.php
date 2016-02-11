<?php
/**
 * LBS4 ILS Driver (LBS4)
 *
 * PHP version 5
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
 * @author   Götz Hatop <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * VuFind Connector for OCLC LBS4
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Götz Hatop <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class LBS4 extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Database connection
     *
     * @var resource
     */
    protected $db;

    /**
     * URL where epn can be appended to
     *
     * @var string
     */
    protected $opcloan;

    /**
     * ILN
     *
     * @var string
     */
    protected $opaciln;

    /**
     * FNO
     *
     * @var string
     */
    protected $opacfno;

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
        if (isset($this->config['Catalog']['opaciln'])) {
            $this->opaciln = $this->config['Catalog']['opaciln'];
        }
        if (isset($this->config['Catalog']['opacfno'])) {
            $this->opacfno = $this->config['Catalog']['opacfno'];
        }
        if (isset($this->config['Catalog']['opcloan'])) {
            $this->opcloan = $this->config['Catalog']['opcloan'];
        }
        if (function_exists("sybase_pconnect")
            && isset($this->config['Catalog']['database'])
        ) {
            putenv("SYBASE=" . $this->config['Catalog']['sybpath']);
            $this->db = sybase_pconnect(
                $this->config['Catalog']['sybase'],
                $this->config['Catalog']['username'],
                $this->config['Catalog']['password']
            );
            sybase_select_db($this->config['Catalog']['database']);
        } else {
            throw new ILSException('No Database.');
        }
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        return isset($this->config[$function]) ? $this->config[$function] : false;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $ppn The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * ppn, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($ppn)
    {
        $sybid = substr($ppn, 0, -1); //strip checksum
        $sql = "select o.loan_indication, o.signature, v.loan_status"
             . " from ous_copy_cache o, volume v"
             . " where o.iln=" . $this->opaciln
             . " and o.ppn=" . $sybid
             . " and o.epn *= v.epn"; //outer join
        try {
            $sqlStmt = sybase_query($sql);
            $number = 0;
            while ($row = sybase_fetch_row($sqlStmt)) {
                $number++;
                $loan_indi  = $row[0];
                $label = substr($row[1], 4);
                $locid = substr($row[1], 0, 3);
                $location = $this->translate($this->opaciln . "/" . $locid);
                $loan_status  = $row[2];

                $reserve = 'N';
                $status = $this->getStatusText($loan_indi, $loan_status);
                $available = true;

                if ($loan_indi == 7) {
                    $available = false; //not for loan
                } else if ($loan_indi == 8) {
                    $available = false; //missed items
                } else if ($loan_indi == 9) {
                    $available = false; //not ready yet
                }
                if ($loan_status == 5) {
                    $available = false;
                } else if ($loan_status == 4) {
                    $available = false;
                }

                $holding[] = [
                    'id'             => $ppn,
                    'availability'   => $available,
                    'status'         => $status,
                    'location'       => $location,
                    'reserve'        => $reserve,
                    'callnumber'     => $label,
                ];
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return $holding;
    }

    /**
     * Get status text
     *
     * @param string $indi   Indicator value
     * @param string $status status as retrieved from db
     *
     * @return string the message to be displayed
     */
    protected function getStatusText($indi, $status)
    {
        if ($indi == 0 && $status == 0) {
            $text = 'Available';
        } else if ($indi == 0 && $status == 4) {
            $text = 'On Reserve';
        } else if ($indi == 0 && $status == 5) {
            $text = 'Checked Out';
        } else if ($indi == 3) {
            $text = 'Presence';
        } else {
            $text = 'Not Available';
        }
        return $text;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $ppn    The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($ppn, array $patron = null)
    {
        $sybid = substr($ppn, 0, -1); //strip checksum
        $sql = "select o.epn, o.loan_indication"
             . ", v.volume_bar, v.loan_status"
             . ", v.volume_number, o.signature"
             . ", o.holding, o.type_of_material_copy"
             . " from ous_copy_cache o, volume v, titles_copy t"
             . " where o.iln=" . $this->opaciln
             . " and o.ppn=" . $sybid
             . " and o.epn *= v.epn"//outer join
             . " and t.epn = o.epn"
             . " and t.iln = o.iln"
             . " and t.fno = o.fno"
             . " order by o.signature";
        try {
            $sqlStmt = sybase_query($sql);
            $holding = [];
            while ($row = sybase_fetch_row($sqlStmt)) {
                $epn   = $row[0];
                $loan_indi  = (string)$row[1];
                $volbar = $row[2];
                $loan_status = (string)$row[3];
                $volnum = $row[4];

                //library location identifier is a callnumber prefix
                $locid = substr($row[5], 0, 3);

                //suppress multiple callnumbers, comma separated items
                $callnumber = current(explode(',', substr($row[5], 4)));

                if ($locid != '') {
                    $location = $this->opaciln . "/" . $locid;
                }
                if ($row[6] != '') {
                    $summary = [$row[6]];
                }
                $material = $row[7];

                $check = false;
                $reserve = 'N';
                $is_holdable = false;

                $storage = $this->getStorage($loan_indi, $locid, $callnumber);
                $status = $this->getStatusText($loan_indi, $loan_status);
                $note = $this->getNote($loan_indi, $locid, $callnumber);

                if (empty($storage)) {
                    $check = $this->checkHold($loan_indi, $material);
                } else if (empty($volbar)) {
                    $volbar = $locid;
                }

                $available = true;
                if ($loan_status == '') {
                    $available = false;
                } else if ($loan_status == 4) {
                    $available = false;
                    $reserve = 'Y';
                } else if ($loan_status == 5) {
                    $available = false;
                    $duedate = $this->getLoanexpire($volnum);
                    $is_holdable = true;
                } else if ($loan_indi > 6) {
                    $available = false;
                }

                $holding[] = [
                    'id'             => $ppn,
                    'availability'   => $available,
                    'status'         => $status,
                    'location'       => $location,
                    'reserve'        => $reserve,
                    'callnumber'     => $callnumber,
                    'duedate'        => $duedate,
                    'number'         => $volbar,
                    'barcode'        => $volbar,
                    'notes'          => [$note],
                    'summary'        => $summary,
                    'is_holdable'    => $is_holdable,
                    'item_id'        => $epn,
                    'check'          => $check,
                    'storageRetrievalRequestLink' => $storage,
                    'checkStorageRetrievalRequest' => !empty($storage),
                    'material'       => $material,
                ];
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return $holding;
    }

    /**
     * Test whether holds needs to be checked
     *
     * @param string $loanindi The loan indicator
     * @param string $material The material code
     *
     * @return bool
     */
    protected function checkHold($loanindi, $material)
    {
        if ($loanindi == 0) {
            if (substr($material, 0, 2) == 'Ab') {
                return true;
            }
        } else if ($loanindi == 3) {
            return true;
        } else if ($loanindi == 6) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get Notes
     *
     * This is responsible for retrieving library specific
     * notes for a record. You may want to override this.
     *
     * @param string $loanind    The library loan indicator
     * @param string $locid      The library location identifier
     * @param array  $callnumber The callnumber of the item
     *
     * @return string On success, a string to be displayed near the item
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getNote($loanind, $locid, $callnumber)
    {
        if ($loanind == 0 && $locid == '000') {
            $note = $this->translate("Textbook Collection");
        } else if ($loanind == 1) {
            $note = $this->translate("Short loan");//Short time loan?
        } else if ($loanind == 2) {
            $note = "Interlibrary Loan";
        } else if ($loanind == 3) {
            $note = $this->translate("Presence");
        } else if ($loanind == 8) {
            $note = $this->translate("Missed");
        } else if ($loanind == 9) {
            $note = $this->translate("In Progress");
        }
        return $note;
    }

    /**
     * Get Storage
     *
     * This is responsible for retrieving library specific
     * storage urls if available, i.e. bibmap links.
     *
     * You may want to override this.
     *
     * @param string $locid      The library location identifier
     * @param array  $callnumber The callnumber of the item
     *
     * @return string On success, a url string to be displayed as
     * storage link.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getStorage($locid, $callnumber)
    {
        return false;
    }

    /**
     * Get Loanexpire
     *
     * This is responsible for retrieving loan expiration dates
     * for an item.
     *
     * @param string $vol The volume number
     *
     * @return string On success, a string to be displayed as
     *                loan expiration date.
     */
    protected function getLoanexpire($vol)
    {
        $sql = "select expiry_date_loan from loans_requests"
             . " where volume_number=" . $vol . "";
        try {
            $sqlStmt = sybase_query($sql);
            $result = false;
            if ($row = sybase_fetch_row($sqlStmt)) {
                $result = $row[0];
            }
            if ($result) {
                return substr($result, 0, 12);
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return false;
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
     */
    public function getHoldLink($id, $details)
    {
        if (isset($details['item_id'])) {
            $epn = $details['item_id'];
            $hold = $this->opcloan . "?MTR=mon"
                        . "&BES=" . $this->opacfno
                        . "&EPN=" . $this->prfz($epn);
            return $hold;
        }
        return $this->opcloan . "?MTR=mon" . "&BES=" . $this->opacfno
               . "&EPN=" . $this->prfz($id);
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
     * @return array       An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getStatus($id);
        }
        return $items;
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode The patron username
     * @param string $pin     The patron's password
     *
     * @throws ILSException
     * @return mixed Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $pin)
    {
        $sql = "select b.borrower_bar "
             . ",b.first_name_initials_prefix"
             . ",b.name"
             . ",b.email_address"
             . ",b.registration_number"
             . ",b.borrower_type"
             . ",b.institution_code"
             . ",b.address_id_nr"
             . ",b.iln"
             . ",b.language_code"
             . " from borrower b, pincode p"
             . " where b.borrower_bar='" . $barcode . "'"
             . " and b.address_id_nr=p.address_id_nr"
             . " and b.iln=" . $this->opaciln
             . " and p.hashnumber = "
             . "     ascii(substring(convert(char(12),'" . $pin . "',104),1,1))"
             . " + 2*ascii(substring(convert(char(12),'" . $pin . "',104),2,1))"
             . " + 3*ascii(substring(convert(char(12),'" . $pin . "',104),3,1))"
             . " + 4*ascii(substring(convert(char(12),'" . $pin . "',104),4,1))"
             . " + 5*ascii(substring(convert(char(12),'" . $pin . "',104),5,1))"
             . " + 6*ascii(substring(convert(char(12),'" . $pin . "',104),6,1))"
             . " + 7*ascii(substring(convert(char(12),'" . $pin . "',104),7,1))"
             . " + 8*ascii(substring(convert(char(12),'" . $pin . "',104),8,1))";
        try {
            $result = [];
            $sqlStmt = sybase_query($sql);
            $row = sybase_fetch_row($sqlStmt);
            if ($row) {
                $result = ['id' => $barcode,
                              'firstname' => $row[1],
                              'lastname' => $row[2],
                              'cat_username' => $barcode,
                              'cat_password' => $pin,
                              'email' => $row[3],
                              'major' => $row[4],    // registration_number
                              'college' => $row[5],  // borrower_type
                              'address_id_nr' => $row[7],
                              'iln' => $row[8],
                              'lang' => $row[9]];
                return $result;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @throws ILSException
     * @return array      Array of the patron's profile data on success.
     */
    public function getMyProfile($user)
    {
        $sql = "select b.borrower_bar "
             . ",b.first_name_initials_prefix"
             . ",b.name"
             . ",b.email_address"
             . ",b.free_text"
             . ",b.free_text_block" //5
             . ",b.borrower_type"
             . ",b.person_titles"
             . ",b.reminder_address"
             . ",a.sub_postal_code"
             . ",a.address_pob" //10
             . ",a.town"
             . ",a.telephone_number"
             . ",a.address_code"
             . " from borrower b, address a"
             . " where b.address_id_nr=a.address_id_nr"
             . "   and b.borrower_bar='" . $user['id'] . "'"
             . "   order by a.address_code asc";
        try {
            $result = [];
            $sqlStmt = sybase_query($sql);
            $row = sybase_fetch_row($sqlStmt);
            if ($row) {
                $result = [
                          'firstname' => $row[1],
                          'lastname'  => $row[2],
                          'address1'  => $row[10] . ', ' . $row[9] . ' ' . $row[11],
                          //'zip'     => $row[14],
                          'email'     => $row[3],
                          'phone'     => $row[12],
                          'group'     => $row[6],
                          ];
                if ($row[6] == '81') {
                    $result['group'] = $this->translate('Staff');
                } else if ($row[6] == '1') {
                    $result['group'] = $this->translate('Student');
                } else if ($row[6] == '30') {
                    $result['group'] = $this->translate('Residents');
                }
                $row = sybase_fetch_row($sqlStmt);
                if ($row) {
                    if ($row[8] == $row[13]) { //reminder address first
                        $result['address2'] = $result['address1'];
                        $result['address1']
                            = $row[10] . ', ' . $row[9] . ' ' . $row[11];
                    } else {
                        $result['address2']
                            = $row[10] . ', ' . $row[9] . ' ' . $row[11];
                    }
                }
                return $result;
            } else {
                return $user;
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $aid = $patron['address_id_nr'];
        $iln = $patron['iln'];
        $lang = $patron['lang'];
        $sql = "exec loans_requests_rm_003 " . $aid . ", " . $iln . ", " . $lang;
        try {
            $result = [];
            $count = 0;
            $sqlStmt = sybase_query($sql);
            while ($row = sybase_fetch_row($sqlStmt)) {
                $result[$count] = [
                    'id'      => $row[0]
                   ,'duedate' => substr($row[13], 0, 12)
                   ,'barcode' => $row[31]
                   ,'renew'   => $row[7]
                   ,'publication_year' => $row[45]
                   ,'renewable' => $row[61]
                   ,'message' => $row[60]
                   ,'title'   => $this->picaRecode($row[44])
                   ,'item_id' => $row[7]
                ];
                $count++;
            }
            return $result;
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return [];
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return array Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $aid = $patron['address_id_nr'];
        $iln = $patron['iln'];
        //$lang = $patron['lang'];
        $sql = "select o.ppn"
            . ", o.shorttitle"
            . ", rtrim(convert(char(20),r.reservation_date_time,104))"
            . ", rtrim(convert(char(20),l.expiry_date_reminder,104))"
            . ", r.counter_nr_destination"
            . ", l.no_reminders"
            . ", l.period_of_loan"
            . " from reservation r, loans_requests l, ous_copy_cache o, volume v"
            . " where r.address_id_nr=" . $aid . ""
            . " and l.volume_number=r.volume_number"
            . " and v.volume_number=l.volume_number"
            . " and v.epn=o.epn"
            . " and l.iln=o.iln"
            . " and l.iln=" . $iln
            . "";
        try {
            $result = [];
            $sqlStmt = sybase_query($sql);
            $expire = $row[3]; // empty ?
            while ($row = sybase_fetch_row($sqlStmt)) {
                $title = $this->picaRecode($row[1]);
                $result[] = [
                    'id'       => $this->prfz($row[0]),
                    'create'   => $row[2],
                    'expire'   => $expire,
                    //'location' => $row[4],
                    'title'    => $title
                ];
            }
            return $result;
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return [];
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws \VuFind\Exception\Date
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $aid = $patron['address_id_nr'];
        $iln = $patron['iln'];
        //$lang = $patron['lang'];
        $sql = "select o.ppn"
            . ", r.costs_code"
            . ", r.costs"
            . ", rtrim(convert(char(20),r.date_of_issue,104))"
            . ", rtrim(convert(char(20),r.date_of_creation,104))"
            . ", 'Overdue' as fines"
            . ", o.shorttitle"
            . " from requisition r, ous_copy_cache o, volume v"
            . " where r.address_id_nr=" . $aid . ""
            . " and r.iln=" . $iln
            . " and r.id_number=v.volume_number"
            . " and v.epn=o.epn"
            . " and r.iln=o.iln"
            . " and r.costs_code in (1, 2, 3, 4, 8)"
            . " union select id_number"
            . ", r.costs_code"
            . ", r.costs"
            . ", rtrim(convert(char(20),r.date_of_issue,104))"
            . ", rtrim(convert(char(20),r.date_of_creation,104))"
            . ", r.extra_information"
            . ", '' as zero"
            . " from requisition r"
            . " where r.address_id_nr=" . $aid . ""
            . " and r.costs_code not in (1, 2, 3, 4, 8)"
            . "";
        try {
            $result = [];
            $sqlStmt = sybase_query($sql);
            while ($row = sybase_fetch_row($sqlStmt)) {
                //$fine = $this->translate(('3'==$row[1])?'Overdue':'Dues');
                $fine = $this->picaRecode($row[5]);
                $amount = (null == $row[2]) ? 0 : $row[2] * 100;
                //$balance = (null==$row[3])?0:$row[3]*100;
                $checkout = substr($row[3], 0,  12);
                $duedate = substr($row[4], 0, 12);
                $title = $this->picaRecode(substr($row[6], 0, 12));
                $result[] = [
                    'id'      => $this->prfz($row[0]),
                    'amount'  => $amount,
                    'balance' => $amount, //wtf
                    'checkout' => $checkout,
                    'duedate' => $duedate,
                    'fine'    => $fine,
                    'title'   => $title,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return [];
    }

    /**
     * Helper function to clean up bad characters
     *
     * @param string $str input
     *
     * @return string
     */
    protected function picaRecode($str)
    {
        $clean = preg_replace('/[^(\x20-\x7F)]*/', '', $str);
        return $clean;
    }

    /**
     * Helper function to compute the modulo 11 based
     * ppn control number
     *
     * @param string $str input
     *
     * @return string
     */
    protected function prfz($str)
    {
        $x = 0; $y = 0; $w = 2;
        $stra = str_split($str);
        for ($i = strlen($str); $i > 0; $i--) {
            $c = $stra[$i - 1];
            $x = ord($c) - 48;
            $y += $x * $w;
            $w++;
        }
        $p = 11 - $y % 11;
        if ($p == 11) {
            $p = 0;
        }
        if ($p == 10) {
            $ret = $str . "X";
        } else {
            $ret = $str . $p;
        }
        return $ret;
    }

}
