<?php

namespace KrimDok\Search\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;

define("_BIB_REF_MAPS_PATH_", '/usr/local/var/lib/tuelib/bibleRef/'); // Must end with backslash!
define("_BIB_REF_CMD_PARAMS_", implode(' ', [_BIB_REF_MAPS_PATH_ . 'bible_aliases.map',
       _BIB_REF_MAPS_PATH_ . 'books_of_the_bible_to_code.map', _BIB_REF_MAPS_PATH_ . 'books_of_the_bible_to_canonical_form.map',
       _BIB_REF_MAPS_PATH_ . 'pericopes_to_codes.map']));

class QueryBuilder extends \TueFindSearch\Backend\Solr\QueryBuilder
{
    const BIBLE_REFERENCE_COMMAND = '/usr/local/bin/bib_ref_to_codes_tool';
    const CANONES_REFERENCE_COMMAND = '/usr/local/bin/canon_law_ref_to_codes_tool';
    // TIME_ASPECTS_COMMAND see parent
    const BIBLE_REFERENCE_COMMAND_PARAMETERS = _BIB_REF_CMD_PARAMS_;
    const BIBLE_RANGE_HANDLER = 'BibleRangeSearch';
    const CANONES_RANGE_HANDLER = 'CanonesRangeSearch';
    const TIME_RANGE_HANDLER = 'TimeRangeSearch';
    const BIBLE_RANGE_PARSER = 'bibleRangeParser';
    const CANONES_RANGE_PARSER = 'canonesRangeParser';
    const TIME_RANGE_PARSER = 'timeAspectRangeParser';


    public function build(AbstractQuery $query)
    {
        // TODO: Bei Erweiterter Suche wird eine andere Query-Klasse genutzt.
        // Diese muss anders behandelt werden, da sie aus vielen Sub-Queries
        // besteht. Vorerst werden die Ixtheo-Bereichssuchen nur bei der Standardsuche angewendet
        if (is_a($query, 'VuFindSearch\Query\QueryGroup'))
            return parent::build($query);
        $handler = $query->getHandler();
        if ($handler !== self::BIBLE_RANGE_HANDLER && $handler !== self::CANONES_RANGE_HANDLER && $handler !== self::TIME_RANGE_HANDLER)
            return parent::build($query);
        $queryString = $query->getString();
        $newQuery = $this->getManipulatedQueryString($handler, $query);
        $result = parent::build($query);
        $result->set('q', $newQuery);
        $query->setString($queryString);
        $customParser = null;
        switch($handler) {
            case self::BIBLE_RANGE_HANDLER:
                $customParser = self::BIBLE_RANGE_PARSER;
                break;
            case self::CANONES_RANGE_HANDLER:
                $customParser = self::CANONES_RANGE_PARSER;
                break;
            case self::TIME_RANGE_HANDLER:
                $customParser = self::TIME_RANGE_PARSER;
                break;
            default:
                throw new Exception('No parser available for handler: ' . $handler);
        }
        $result->set('defType', $customParser);
        return $result;
    }


    protected function getManipulatedQueryString($handler, AbstractQuery $query)
    {
        $rangeReferences = '';
        if ($handler == self::BIBLE_RANGE_HANDLER)
            $rangeReferences = $this->parseBibleReference($query);
        else if ($handler == self::CANONES_RANGE_HANDLER)
            $rangeReferences = $this->parseCanonesReference($query);
        else if ($handler == self::TIME_RANGE_HANDLER)
            $rangeReferences = $this->parseTimeAspect($query);
        return $this->translateToSearchString($rangeReferences);
    }


    protected function translateToSearchString($rangeReferences)
    {
        if (empty($rangeReferences)) {
            // if no references were found for given query, search for a range which doesn't exist to get no result.
            $rangeReferences = ["999999999_999999999"];
        }
        $searchString = implode(',', $rangeReferences);
        return $searchString;
    }


    protected function getBibleReferenceCommand($searchQuery)
    {
        setlocale(LC_CTYPE, "de_DE.UTF-8");
        return implode(' ', [
            self::BIBLE_REFERENCE_COMMAND,
            "--query",
            escapeshellarg($searchQuery),
            self::BIBLE_REFERENCE_COMMAND_PARAMETERS
        ]);
    }


    protected function getCanonesReferenceCommand($searchQuery) {
        return implode(' ', [
            self::CANONES_REFERENCE_COMMAND,
            "--query",
            escapeshellarg($searchQuery)
        ]);
    }


    protected function parseBibleReference(AbstractQuery $query)
    {
        $searchQuery = $query->getString();
        if (!empty($searchQuery)) {
            $cmd = $this->getBibleReferenceCommand($searchQuery);
            exec($cmd, $output, $return_var);
            return $output;
        }
        return [];
    }


    protected function parseCanonesReference(AbstractQuery $query)
    {
        $searchQuery = $query->getString();
        if (!empty($searchQuery)) {
            $cmd = $this->getCanonesReferenceCommand($searchQuery);
            exec($cmd, $output, $return_var);
            return $output;
        }
        return [];
    }

    protected function parseTimeAspect(AbstractQuery $query)
    {
        $searchQuery = $query->getString();
        if (!empty($searchQuery)) {
            $cmd = $this->getTimeAspectsCommand($searchQuery);
            exec($cmd, $output, $return_var);
            return $output;
        }
        return [];
    }
}
