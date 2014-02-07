<?php
/**
 * LBS4 ILS Driver (LBS4)
 *
 * PHP version 5
 *
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
 * @author   Goetz Hatop <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
namespace VuFind\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindHttp\HttpService as Service;
use VuFind\ILS\Driver\AbstractBase as AbstractBase;

/**
 * VuFind Connector for OCLC LBS4
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Goetz Hatop <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class LBS4 extends AbstractBase implements TranslatorAwareInterface
{

    /**
     * Database connection
     *
     * @var resource
     */
    private $db;

    /**
     * URL where bik/ppn/epn can be appended to
     *
     * @var string
     */
    private $opacweb;
    private $opclink;
    private $opcloan;
    private $opaciln;
    private $opacfno;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init() {
        if (isset($this->config['Catalog']['opacweb'])) 
             $this->opacweb = $this->config['Catalog']['opacweb'];
        if (isset($this->config['Catalog']['opclink'])) 
             $this->opclink = $this->config['Catalog']['opclink'];
        else $this->opclink = FALSE;
        if (isset($this->config['Catalog']['opcloan'])) 
             $this->opcloan = $this->config['Catalog']['opcloan'];
        if (isset($this->config['Catalog']['opaciln'])) 
             $this->opaciln = $this->config['Catalog']['opaciln'];
        if (isset($this->config['Catalog']['opacfno'])) 
             $this->opacfno = $this->config['Catalog']['opacfno'];
        if (function_exists("sybase_pconnect") 
             && isset($this->config['Catalog']['database'])) {
             putenv ("SYBASE=".$this->config['Catalog']['sybpath']);
             error_log("Opus init ". $this->config['Catalog']['database']);
             $this->db = sybase_pconnect($this->config['Catalog']['sybase'], 
                                    $this->config['Catalog']['username'],
                                    $this->config['Catalog']['password']);
             throw new ILSException('No Database. Sybase extension installed ?');
        } else sybase_select_db($this->config['Catalog']['database']);
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
    public function getStatus($id) {
        if ($this->db == FALSE) {
            return array();
        } else {
            return $this->getSybStatus($id,$this->opacfno,$this->opaciln);
        }
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
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, $patron = false) {
        if ($this->db == FALSE) {
            return array();
        } else { 
            $holding = $this->getSybHolding($id,$this->opacfno,$this->opaciln);
            return $holding;
        }
    }

    private function getSybStatus($ppn, $fno, $iln) {
        $sybid = substr($ppn,0,-1); //no checksum
        $sql = "select o.loan_indication, o.signature, v.loan_status"
             . " from ous_copy_cache o, volume v"
             . " where o.iln=".$iln
             . " and o.ppn=".$sybid
             . " and o.epn *= v.epn"; //outer join
        try {
            $sqlStmt = sybase_query($sql);
            $number = 0;
            while($row = sybase_fetch_row($sqlStmt)) {
                $number++;
                $status = 'Not Available';
                $loan_indi  = $row[0];
                $label = substr($row[1],4);
                $locid = substr($row[1],0,3);
                $location = $this->translate($iln."/". $locid);
                $loan_status  = $row[2];

                $available = true;
                if ($loan_indi==7) {
                    $available = false; //not for loan
                } else if ($loan_indi==8) {
                    $available = false; //missed items
                } else if ($loan_indi==9) {
                    $available = false; //not ready
                }

                $reserve = 'N';
                if ($available) {
                    if ($loan_status==0) {
                        $status = 'available'; //must be small caps
                    } else if ($loan_status==5) {
                        $available = false;
                        $status = 'Checked Out';
                    } else if ($loan_status==4) {
                        $available = false;
                        $status = 'On Reserve';
                        $reserve = 'Y';
                    }
                } else {
                        $status = 'Not Available';
                }

                $holding[] = array(
                    'id'             => $ppn,
                    'availability'   => $available?'1':'0',
                    'status'         => $status,
                    'location'       => $location,
                    'reserve'        => $reserve,
                    'callnumber'     => $label,
                );
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return $holding;
    }

    private function getSybHolding($ppn, $fno, $iln) {
        $sybid = substr($ppn,0,-1); //no checksum
        $sql = "select o.epn, o.loan_indication, o.signature"
             . ", o.date_availability" //. ", v.date_of_availability"
             . ", o.type_of_material_copy"
             . ", v.volume_bar, v.loan_status, v.loan_indication"
             . ", v.volume_number"
             . " from ous_copy_cache o, volume v"
             . " where o.iln=".$iln
             . " and o.ppn=".$sybid
             . " and o.epn *= v.epn"; //outer join
        try {
            $sqlStmt = sybase_query($sql);
            $number = 0;
            while($row = sybase_fetch_row($sqlStmt)) {
                $number++;
                $status = 'Not Available';
                $epn   = $row[0]; 
                $loan_indi  = $row[1];
                $loan_status  = $row[6];
                $notes = array();
                $material = $row[4];
                $volbar = $row[5];

                $available = true;
                if ($loan_indi==0) {
                    $notes[] = $this->translate("Lending Collection");
                    $hold  = $this->opcloan.$this->prfz($epn);
                } else if ($loan_indi==1) {
                    $notes[] = $this->translate("Short time lending");
                    $hold  = $this->opcloan.$this->prfz($epn);
                } else if ($loan_indi==2) {
                    $notes[] = "Fernleihe";
                } else if ($loan_indi==3) {
                    $notes[] = $this->translate("Presentation");
                } else if ($loan_indi==4) {
                    $notes[] = $this->translate("No Media");
                } else if ($loan_indi==5) {
                    $notes[] = $this->translate("Reading Room");
                } else if ($loan_indi==6) {
                    $notes[] = $this->translate("Short time lending");
                } else if ($loan_indi==7) {
                    $notes[] = $this->translate("Interlibrary Loan");
                    $available = false;
                } else if ($loan_indi==8) {
                    $notes[] = $this->translate("Missed");
                    $available = false;
                } else if ($loan_indi==9) {
                    $notes[] = $this->translate("Not for Loan");
                    $available = false;
                }

                $reserve = 'N';
                if ($available) {
                    if ($loan_status==0) {
                        $status = 'available'; //must be small caps
                    } else if ($loan_status==5) {
                        $available = false;
                        $status = 'Checked Out';
                        $duedate = $this->getLoanexpire($row[8]);
                    } else if ($loan_status==4) {
                        $available = false;
                        $status = 'On Reserve';
                        $reserve = 'Y';
                    }
                } else {
                        $status = 'Not Available';
                }
                if ($duedate==null)
                    $duedate='';

                $label = substr($row[2],4);
                $locid = substr($row[2],0,3);
                $location = $this->translate($iln."/". $locid);
                $locref = $this->opacweb.$locid;

                $holding[] = array(
                    'id'             => $ppn,
                    'availability'   => $available?'1':'0',
                    'status'         => $status,
                    'location'       => $location,
                    'reserve'        => $reserve,
                    'callnumber'     => $label,
                    'duedate'        => $duedate,
                    'barcode'        => $volbar,
                    'number'         => $number,
                    'notes'          => $notes,
                    'loan_availability' => $available?'1':'0',
                    'hold' => $hold,
                );
            }
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return $holding;
    }

    private function getLoanexpire($vol) {
        $sql = "select expiry_date_loan from loans_requests"
             . " where volume_number=".$vol;
        if (strlen($vol)<5)
            return false;
        try {
            $sqlStmt = sybase_query($sql);
            $result = false;
            while($row = sybase_fetch_row($sqlStmt)) {
                $result = $row[0];
            }
            return substr($result,0,12);
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return false;
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
     */
    public function getPurchaseHistory($id)
    {
        return array(); //may be later
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
    public function getHoldLink($id, $details) {
        if (isset($details['hold'])) {
            return $details['hold'];
        }
        return false;
    }

    public function getPickUpLocations($patron = false, $holdDetails = null)
    {
        return array(); //may be later
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idLst The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array       An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        $items = array();
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
     * @param string $username The patron username
     * @param string $password The patron's password
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
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
             . " where b.borrower_bar='".$barcode."'"
             . " and b.address_id_nr=p.address_id_nr"
             . " and p.hashnumber = "
             . "     ascii(substring(convert(char(12),'".$pin."',104),1,1))"
             . " + 2*ascii(substring(convert(char(12),'".$pin."',104),2,1))"
             . " + 3*ascii(substring(convert(char(12),'".$pin."',104),3,1))"
             . " + 4*ascii(substring(convert(char(12),'".$pin."',104),4,1))"
             . " + 5*ascii(substring(convert(char(12),'".$pin."',104),5,1))"
             . " + 6*ascii(substring(convert(char(12),'".$pin."',104),6,1))"
             . " + 7*ascii(substring(convert(char(12),'".$pin."',104),7,1))"
             . " + 8*ascii(substring(convert(char(12),'".$pin."',104),8,1))";
        try {
            $result = array();
            $sqlStmt = sybase_query($sql);
            $row = sybase_fetch_row($sqlStmt);
            if ($row) {
                $result = array('id' => $barcode,
                              'firstname' => $row[1],
                              'lastname' => $row[2],
                              'cat_username' => $barcode,
                              'cat_password' => $pin,
                              'email' => $row[3],
                              'major' => $row[4],    // registration_number
                              'college' => $row[6],
                              'address_id_nr' => $row[7], 
                              'iln' => $row[8],
                              'lang' => $row[9]); 
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
             . "   and b.borrower_bar='".$user['id']."'"
             . "   order by a.address_code asc";
        try {
            $result = array();
            $sqlStmt = sybase_query($sql);
            $row = sybase_fetch_row($sqlStmt);
            if ($row) {
                $result = array(
                          'firstname' => $row[1],
                          'lastname'  => $row[2],
                          'address1'  => $row[10].', '.$row[9].' '.$row[11],
                          //'zip'       => $row[14],
                          'email'     => $row[3],
                          'phone'     => $row[12],
                          'group'     => $row[6],
                          ); 
                if ($row[6]=='81')
                    $result['group'] = 'Mitarbeiter';
                else if ($row[6]=='1')
                    $result['group'] = 'Student';
                else if ($row[6]=='30')
                    $result['group'] = 'Umland';
                $row = sybase_fetch_row($sqlStmt);
                if ($row) {
                    if ($row[8]==$row[13]) { //reminder address first
                      $result['address2'] = $result['address1'];
                      $result['address1'] = $row[10].', '.$row[9].' '.$row[11];
                    } else {
                      $result['address2'] = $row[10].', '.$row[9].' '.$row[11];
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
        $sql = "exec loans_requests_rm_003 ".$aid.", ".$iln.", ".$lang;
        try {
            $result = array();
            $count = 0;
            $sqlStmt = sybase_query($sql);
            while($row = sybase_fetch_row($sqlStmt)) {
                $result[$count] = array(
                    'id'      => $row[0]
                   ,'duedate' => substr($row[13],0,12)
                   ,'barcode' => $row[31]
                   ,'renew'   => $row[7]
                   ,'publication_year' => $row[45]
				   ,'renewable' => $row[61] 
				   ,'message' => $row[60]
				   ,'title'   => $this->picaRecode($row[44])
				   ,'item_id' => $row[7]
                );
                $count++;
            }
            return $result;
        } catch (\Exception $e) {
            throw new ILSException($e->getMessage());
        }
        return array();
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
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        return array(); //may be later
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
        return array(); //may be later
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return Opus
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Translate a string if a translator is available.
     *
     * @param string $msg Message to translate
     *
     * @return string
     */
    protected function translate($msg)
    {
        return null !== $this->translator
            ? $this->translator->translate($msg) : $msg;
    }

    private function picaRecode($str) {
        $clean = preg_replace('/[^(\x20-\x7F)]*/','', $str);
	    return $clean;
    }

    private function prfz($str) {
        $x = 0; $y = 0; $w = 2;
        $stra = str_split($str);
        for ($i=strlen($str); $i>0; $i--) {
             $c = $stra[$i-1];
             $x = ord($c) - 48;
             $y += $x*$w;
             $w++;
         }
         $p = 11-$y%11;
         if ($p==11) $p=0;
         if ($p==10) $ret = $str."X";
         else $ret = $str.$p;
         return $ret;
    }

}
