<?php

/**
 * Console service for verifying record links, resources and ratings.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2024.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace FinnaConsole\Command\Util;

use Closure;
use Finna\Db\Service\FinnaRecordServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;

/**
 * Console service for verifying record links, resources and ratings.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
#[AsCommand(
    name: 'util/verify_record_links'
)]
class VerifyRecordLinks extends AbstractUtilCommand
{
    /**
     * Constructor
     *
     * @param FinnaRecordServiceInterface        $recordService Record database service
     * @param \VuFindSearch\Backend\Solr\Backend $solr          Search backend
     * @param \VuFind\Record\Loader              $recordLoader  Record loader
     * @param \VuFind\Config\Config              $searchConfig  Search config
     */
    public function __construct(
        protected FinnaRecordServiceInterface $recordService,
        protected \VuFindSearch\Backend\Solr\Backend $solr,
        protected \VuFind\Record\Loader $recordLoader,
        protected \VuFind\Config\Config $searchConfig
    ) {
        $recordLoader->setCacheContext(\VuFind\Record\Cache::CONTEXT_DISABLED);

        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Verify and update record links in the database')
            ->addOption(
                'resources',
                null,
                InputOption::VALUE_NEGATABLE,
                'Whether to process saved resources (records) -- default is true',
                true
            )
            ->addOption(
                'comments',
                null,
                InputOption::VALUE_NEGATABLE,
                'Whether to process comments -- default is true',
                true
            )
            ->addOption(
                'ratings',
                null,
                InputOption::VALUE_NEGATABLE,
                'Whether to process ratings -- default is true',
                true
            );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->msg('Record link verification started');

        if ($input->getOption('comments')) {
            $this->recordService->checkCommentLinks(
                Closure::fromCallable([$this, 'getDedupRecordIds']),
                Closure::fromCallable([$this, 'msg'])
            );
        }

        if ($input->getOption('ratings')) {
            $this->recordService->checkRatingLinks(
                Closure::fromCallable([$this, 'getDedupRecordIds']),
                Closure::fromCallable([$this, 'msg'])
            );
        }
        if ($input->getOption('resources')) {
            $this->recordService->checkResources(
                $this->recordLoader,
                Closure::fromCallable([$this, 'msg'])
            );
        }

        return 0;
    }

    /**
     * Get IDs of duplicate records (including the given record)
     *
     * @param array $recordIds Record IDs
     *
     * @return array Associative array of arrays with record ID as the key
     */
    protected function getDedupRecordIds(array $recordIds): array
    {
        // Search directly in Solr to avoid any listeners or filters from interfering
        $escapedIds = array_map(
            function ($i) {
                return '"' . addcslashes($i, '"') . '"';
            },
            $recordIds
        );

        $query = new \VuFindSearch\Query\Query();
        $params = new \VuFindSearch\ParamBag(
            [
                'hl' => 'false',
                'spellcheck' => 'false',
                'sort' => '',
                'q' => 'local_ids_str_mv:(' . implode(' OR ', $escapedIds) . ')',
            ]
        );
        $records = $this->solr->search($query, 0, 1000, $params)->getRecords();

        $result = [];
        foreach ($records as $record) {
            $localIds = $record->getLocalIds();
            foreach ($recordIds as $id) {
                if (in_array($id, $localIds)) {
                    $result[$id] = $localIds;
                    break;
                }
            }
        }
        return $result;
    }
}
