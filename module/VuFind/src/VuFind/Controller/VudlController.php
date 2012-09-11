<?php
/**
 * VuDLController Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   David Lacy <david.lacy@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

/**
 * This controller is for the viewing of the digital library files.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VudlController extends AbstractBase
{
    /**
     * Get the information from the XML
     *
     * Image srcs, document groups
     *
     * Data is later loaded and rendered with JS
     *
     * @return mixed
     */
    public function recordAction()
    {
        // TARGET ID
        $id = $this->params()->fromQuery('id');
        $view = $this->createViewModel();
        $view->id = $id;

        // GET XML FILE NAME
        $driver = $this->getSearchManager()->setSearchClassId('Solr')
            ->getResults()->getRecord($id);
        if (!method_exists($driver, 'getFullRecord')) {
            throw new \Exception('Cannot obtain VuDL record');
        }
        $result = $driver->getFullRecord();
        $url = isset($result->url) ? trim($result->url) : false;
        if (empty($url)) {
            throw new \Exception('Not a VuDL Record: '.$id);
        }

        // LOAD FILE (this is the only time we pull the whole XML file
        $xml = simplexml_load_file($url);
        if (!$xml) {
            // DOUBLE ENCODING MADNESS - some of the records need to be encoded
            $split = explode('/', $url);
            // uri encode everything in url after 'VuDL'
            for ($i=8;$i<count($split);$i++) {
                $split[$i] = rawurlencode($split[$i]);
            }
            $url = implode($split, '/');
            $xml = simplexml_load_file($url);
        }

        // FILE INFORMATION / DESCRIPTION
        $fileDetails = $this->getDocSummary($xml);
        $view->details = $fileDetails;
        $view->file = urlencode($url);

        // GET IDS FOR ALL FILES
        $files = $this->getAllFiles($xml);

        // GET PAGE AND DOCUMENT STRUCTURE
        $pages = array();
        $docs = array();
        foreach ($xml->xpath('//METS:structMap/METS:div/METS:div') as $div) {
            foreach ($div->xpath('METS:div') as $item) {
                $index = intval($item['ORDER']) - 1;
                // Only the first five pages, the rest are loaded thru AJAX
                if ($div['TYPE'] == 'page_level') {
                    $pages[$index] = array(
                        'label'=>(string) $item['LABEL'],
                        'original' => ''
                    );
                    // Store image srcs under their use
                    foreach ($item->xpath('METS:fptr') as $id) {
                        $file = $files[(string) $id['FILEID']];
                        $pages[$index][$file['use']] = $file['src'];
                    }
                } elseif ($div['TYPE'] == 'document_level') {
                    $id = $item->xpath('METS:fptr');
                    $id = $id[0];
                    // Assuming only one document per... document
                    $file = $files[(string) $id['FILEID']];
                    $docs[$index] = array(
                        'label' =>(string) $item['LABEL'],
                        'src'   => $file['src']
                    );
                    // Set the right thumbnail
                    if ($file['type'] == 'application/pdf') {
                        $docs[$index]['img'] = 'pdf';
                    } elseif ($file['type'] == 'application/msword') {
                        $docs[$index]['img'] = 'doc';
                    }
                }
            }
        }

        // SEND THE DATA FOR THE FIRST PAGES
        // (Original, Large, Medium, Thumbnail srcs) and THE DOCUMENTS
        $view->pages = $pages;
        $view->docs = $docs;
        return $view;
    }

    /**
     * Parses the xml file for the section detailing the files
     *
     * @param SimpleXMLElement $xml XML to be parsed
     *
     * @return array                An array of file objects, string-indexed by ID;
     * Object contains 'src','type','use'(size)
     */
    protected function getAllFiles($xml)
    {
        $files = array();
        // really only one:
        foreach ($xml->xpath('//METS:fileSec') as $fileSec) {
            // for each group:
            foreach ($fileSec->xpath('METS:fileGrp') as $i=>$group) {
                // store by id:
                foreach ($group->xpath('METS:file') as $j=>$file) {
                    $src = $file->xpath('METS:FLocat');
                    $src = $src[0]->attributes('xlink', true);
                    $files[ $file['ID'].'' ] = array(
                        'src'  => (string) $src['href'],
                        'type' => (string) $file['MIMETYPE'],
                        'use'  => strtolower((string) $group['USE'])
                    );
                }
            }
        }
        return $files;
    }

    /**
     * Pull the file details (title, date, etc) from the XML
     *
     * Data is string indexed by what type of information it is
     *
     * @param SimpleXMLElement $xml XML to be parsed
     *
     * @return array                title-indexed array of basic document data
     * ('author' => 'Kevin Bacon', etc.)
     */
    protected function getDocSummary($xml)
    {
        $data = array();
        foreach ($xml->xpath('//METS:xmlData') as $xmlData) {
            if (count($xmlData->children('oai_dc', true)) > 0) {
                foreach ($xmlData->children('oai_dc', true) as $doc_data) {
                    foreach ($doc_data->children('dc', true) as $detail) {
                        $index = $detail->getName();
                        $data[$index] =(string) $detail;
                    }
                }
                return $data;
            }
        }
    }
}
