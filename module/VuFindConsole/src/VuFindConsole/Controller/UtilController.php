<?php
/**
 * CLI Controller Module
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;

use Laminas\Console\Console;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class UtilController extends AbstractBase
{
    /**
     * Command-line tool to delete suppressed records from the index.
     *
     * @return \Laminas\Console\Response
     */
    public function suppressedAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            Console::writeLine('Available switches:');
            Console::writeLine(
                '--authorities =>'
                . ' Delete authority records instead of bibliographic records'
            );
            Console::writeLine('--help or -h => Show this message');
            Console::writeLine(
                '--outfile=[/path/to/file] => Write the ID list to the specified'
                . ' file instead of updating Solr (optional)'
            );
            return $this->getFailureResponse();
        }

        // Setup Solr Connection
        $backend = $request->getParam('authorities') ? 'SolrAuth' : 'Solr';

        // Make ILS Connection
        try {
            $catalog = $this->getILS();
            $result = ($backend == 'SolrAuth')
                ? $catalog->getSuppressedAuthorityRecords()
                : $catalog->getSuppressedRecords();
        } catch (\Exception $e) {
            Console::writeLine("ILS error -- " . $e->getMessage());
            return $this->getFailureResponse();
        }

        // Validate result:
        if (!is_array($result)) {
            Console::writeLine("Could not obtain suppressed record list from ILS.");
            return $this->getFailureResponse();
        } elseif (empty($result)) {
            Console::writeLine("No suppressed records to delete.");
            return $this->getSuccessResponse();
        }

        // If 'outfile' set, write the list
        if ($file = $request->getParam('outfile')) {
            if (!file_put_contents($file, implode("\n", $result))) {
                Console::writeLine("Problem writing to $file");
                return $this->getFailureResponse();
            }
        } else {
            // Default behavior: Get Suppressed Records and Delete from index
            $solr = $this->serviceLocator->get(\VuFind\Solr\Writer::class);
            $solr->deleteRecords($backend, $result);
            $solr->commit($backend);
            $solr->optimize($backend);
        }
        return $this->getSuccessResponse();
    }

    /**
     * Tool to auto-fill hierarchy cache.
     *
     * @return \Laminas\Console\Response
     */
    public function createhierarchytreesAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            $scriptName = $this->getRequest()->getScriptName();
            if (substr($scriptName, -9) === 'index.php') {
                $scriptName .= ' util createHierarchyTrees';
            }
            Console::writeLine(
                'Usage: ' . $scriptName
                . ' [<backend>] [--skip-xml or -sx] [--skip-json or -sj]'
                . ' [--help or -h]'
            );
            Console::writeLine(
                "\t<backend> => Search backend, e.g. " . DEFAULT_SEARCH_BACKEND
                . " (default) or Search2"
            );
            Console::writeLine("\t--skip-xml or -sx => Skip the XML cache");
            Console::writeLine("\t--skip-json or -sj => Skip the JSON cache");
            Console::writeLine("\t--help or -h => Show this message");
            return $this->getFailureResponse();
        }
        $skipJson = $request->getParam('skip-json') || $request->getParam('sj');
        $skipXml = $request->getParam('skip-xml') || $request->getParam('sx');
        $backendId = $request->getParam('backend') ?? DEFAULT_SEARCH_BACKEND;
        $recordLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
        $hierarchies = $this->serviceLocator
            ->get(\VuFind\Search\Results\PluginManager::class)->get($backendId)
            ->getFullFieldFacets(['hierarchy_top_id']);
        if (!isset($hierarchies['hierarchy_top_id']['data']['list'])) {
            $hierarchies['hierarchy_top_id']['data']['list'] = [];
        }
        foreach ($hierarchies['hierarchy_top_id']['data']['list'] as $hierarchy) {
            $recordid = $hierarchy['value'];
            $count = $hierarchy['count'];
            if (empty($recordid)) {
                continue;
            }
            Console::writeLine(
                "\tBuilding tree for " . $recordid . '... '
                . number_format($count) . ' records'
            );
            try {
                $driver = $recordLoader->load($recordid, $backendId);
                // Only do this if the record is actually a hierarchy type record
                if ($driver->getHierarchyType()) {
                    // JSON
                    if (!$skipJson) {
                        Console::writeLine("\t\tJSON cache...");
                        $driver->getHierarchyDriver()->getTreeSource()->getJSON(
                            $recordid, ['refresh' => true]
                        );
                    } else {
                        Console::writeLine("\t\tJSON skipped.");
                    }
                    // XML
                    if (!$skipXml) {
                        Console::writeLine("\t\tXML cache...");
                        $driver->getHierarchyDriver()->getTreeSource()->getXML(
                            $recordid, ['refresh' => true]
                        );
                    } else {
                        Console::writeLine("\t\tXML skipped.");
                    }
                }
            } catch (\VuFind\Exception\RecordMissing $e) {
                Console::writeLine(
                    'WARNING! - Caught exception: ' . $e->getMessage() . "\n"
                );
            }
        }
        Console::writeLine(
            count($hierarchies['hierarchy_top_id']['data']['list']) . ' files'
        );

        return $this->getSuccessResponse();
    }
}
