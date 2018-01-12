<?php
/**
 * Proxy Controller Module
 *
 * @category    TueFind
 * @author      Johannes Riedl <johannes.riedl@uni-tuebingen.de>
 * @copyright   2015-2017 Universtitätsbibliothek Tübingen
 */
namespace TueFind\Controller;

use VuFind\Exception\Forbidden as ForbiddenException;
use \Exception as Exception;
use SimpleXMLElement;
use Zend\Log\Logger as Logger;

/**
 * This controller is a proxy for requests to BSZ based GVI
 * (= Gemeinsame Verbünde Index) to determine whether a monograph
 * can be obtained by interlibrary loan
 * @package  Controller
 */

class PDAProxyController extends \VuFind\Controller\AbstractBase
{

    protected $base_url = 'http://gvi.bsz-bw.de/solr/GVI/select';
    protected $base_query = 'rows=10&wt=json&facet=true&facet.field=ill_region&facet.field=ill_flag&fl=id,fullrecord&q=isbn:';


    protected function isAvailableForILL($isbn)
    {
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $client->setUri($this->base_url . '?' . $this->base_query . $isbn);
        $response = $client->send();

        if (!$response->isSuccess())
            throw new Exception("HTTP ERROR");

        // Abort, if general JSON decoding fails
        $json = json_decode($response->getBody(), true);
        if ($json == null)
           throw new Exception("JSON PARSE ERROR");

        // We use a several way scheme
        // 1.) If we have a match with ILL -> OK
        // 2.) Parse the fullrecord

        if ((!isset($json['facet_counts']['facet_fields']['ill_flag'])) || (!isset($json['facet_counts']['facet_fields']['ill_region'])))
           throw new Exception("JSON FACET FIELDs Missing");

        // Case 1
        $ill_facet = $json['facet_counts']['facet_fields']['ill_flag'];
        foreach ($ill_facet as $ill_flag => $count) {
              if (($ill_flag == 'IllFlag.Loan' || $ill_flag == 'IllFlag.Copy' || $ill_flag == 'IllFlag.Ecopy') && $count != 0)
                 return true;
        }

        // Case 2
        if (!isset($json['response']['docs']))
           throw new Exception("JSON DOCS Missing");

        $docs = $json['response']['docs'];
        foreach ($docs as $doc) {
            if (!isset($doc['fullrecord']))
               continue;

            // Remove escaped quotation marks
            $fullrecord = preg_replace('/[\]["]/','"', $doc['fullrecord']);
            // The "Fernleihindikator" field is 924$d. According to
            // https://wiki.dnb.de/download/attachments/83788495/2013-10-13_MARC-Feld_924.pdf?version=1&modificationDate=1381758363000 (17/03/06)
            // we have at least 'b' (="nur Papierkopie"), 'c' (="uneingeschränkte Fernleihe), and 'd' (="keine Fernleihe")
            $xml_record = new SimpleXMLElement($fullrecord);
            foreach ($xml_record->record->children() as $datafield) {
               if ($datafield['tag'] == "924") {
                   foreach ($datafield->children() as $subfield) {
                       if ($subfield['code'] == 'd') {
                           if ($subfield == 'b' || $subfield == 'c')
                              return true;
                       }
                   }
               }
            }
        }
        return false;
    }


    public function loadAction()
    {

        $NO_ISBN = "0000000";
        $query = $this->getRequest()->getUri()->getQuery();
        $parameters = [];
        parse_str($query, $parameters);
        $isbn = !empty($parameters['isbn']) ? $parameters['isbn'] : $NO_ISBN;

        try {
            $pda_available = ($isbn != $NO_ISBN) ? (!$this->isAvailableForILL($isbn)) : false;
        } catch (Exception $e) {
          echo 'We got exception' . $e->getMessage() . "\n";
        }

        $pda_status = $pda_available ? "OFFER_PDA" : "NO_OFFER_PDA";
        $json = json_encode(['isbn' => $isbn,
                             'pda_status' => $pda_status]);

        $this->serviceLocator->get('VuFind\Logger')->log(Logger::NOTICE, 'PDALOG for ' . $isbn . ': ' . $pda_status);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->setContent($json);
        return $response;
    }

}
