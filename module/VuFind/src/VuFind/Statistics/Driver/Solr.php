<?php
/**
 * Solr Statistics Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Statistics\Driver;
use VuFind\Connection\Manager as ConnectionManager;

/**
 * Writer to put statistics to the SOLR index
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Solr extends AbstractBase
{
    protected $solr;

    /**
     * Constructor
     *
     * @param string $source Which class this writer belongs to
     *
     * @return void
     */
    public function __construct($source)
    {
        $this->solr = ConnectionManager::connectToIndex('SolrStats');
    }

    /**
     * Write a message to the log.     *
     *
     * @param array $data     Data specific to what we're saving
     * @param array $userData Browser, IP, urls, etc
     *
     * @return void
     */
    public function write($data, $userData)
    {
        if (isset($data['phrase']) && $data['phrase'] == '') {
            $data['phrase'] = '*:*';
        }
        $this->solr->saveRecord(
            $this->solr->getSaveXML(
                array_merge($data, $userData)
            )
        );
    }

    /**
     * Get the most common of a field.
     *
     * @param string  $field      What field of data are we researching?
     * @param integer $listLength How long the top list is
     *
     * @return array
     */
    public function getTopList($field, $listLength = 5)
    {
        // Records saved in SOLR
        $records = $this->solr->search(
            array(
                'facet' => array(
                    'field' => array($field),
                    'sort'  => 'count'
                )
            )
        );
        $top = array();
        foreach ($records['facet_counts']['facet_fields'][$field] as $i=>$record) {
            if ($i < $listLength) {
                $top[] = array(
                    'value' => ($record[0] == '*:*') ? '(empty)' : $record[0],
                    'count' => $record[1]
                );
            }
        }
        return array(
            'total' => $count,
            'top'   => $top
        );
    }

    /**
     * Get the total count of a field.
     *
     * @param string $field What field of data are we researching?
     * @param array  $value Extra options for search. Value => match this value
     *
     * @return array
     */
    public function getFullList($field, $value = array('value' => '[* TO *]'))
    {
        $start = 0;
        $limit = 1000;
        $data = array();
        do {
            $search = $this->solr->search(
                array(
                    'fields' => array($field),
                    'filter' => array($field.':'.$value['value']),
                    'start'  => $start,
                    'limit'  => $limit
                )
            );
            foreach ($search['response']['docs'] as $doc) {
                $data[] = $doc;
            }
            $start += $limit;
        } while (count($search['response']['docs']) > 0);
        return $data;
    }

    /**
     * Returns browser usage statistics
     *
     * @param bool    $version    Include the version numbers in the list
     * @param integer $listLength How many items to return
     *
     * @return array
     */
    public function getBrowserStats($version, $listLength = 5)
    {
        $start = 0;
        $limit = 1000;
        $hashes = array();
        do {
            $result = $this->solr->search(
                array(
                    'field' => 'browser',
                    'group' => array('session'),
                    'start' => $start,
                    'limit' => $limit
                )
            );
            foreach ($result['grouped']['session']['groups'] as $group) {
                if ($version) {
                    // Version specific
                    $browser = $group['doclist']['docs'][0]['browser']
                        .' '.$group['doclist']['docs'][0]['browserVersion'];
                    if (isset($hashes[$browser])) {
                        $hashes[$browser] ++;
                    } elseif (count($hashes) < $limit) {
                        $hashes[$browser] = 1;
                    }
                } else {
                    // Browser name
                    if (isset($hashes[$group['doclist']['docs'][0]['browser']])) {
                        $hashes[$group['doclist']['docs'][0]['browser']] ++;
                    } elseif (count($hashes) < $limit) {
                        $hashes[$group['doclist']['docs'][0]['browser']] = 1;
                    }
                }
            }
            $start += $limit;
        } while (count($result['grouped']['session']['groups']) > 0);
        $solrBrowsers = array();
        foreach ($hashes as $browser=>$count) {
            $newBrowser = array(
                'browserName' => $browser,
                'count' => $count
            );
            // Insert sort (limit to listLength)
            for ($i=0;$i<$listLength-1 && $i<count($solrBrowsers);$i++) {
                if ($count > $solrBrowsers[$i]['count']) {
                    // Insert in order
                    array_splice($solrBrowsers, $i, 0, array($newBrowser));
                    continue 2; // Skip the append after this loop
                }
            }
            if (count($solrBrowsers) < $listLength) {
                $solrBrowsers[] = $newBrowser;
            }
        }
        return $solrBrowsers;
    }
}
