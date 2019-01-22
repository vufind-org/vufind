<?php

namespace IxTheo\Search\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;

define("_BIB_REF_MAPS_PATH_", '/usr/local/var/lib/tuelib/bibleRef/'); // Must end with backslash!
define("_BIB_REF_CMD_PARAMS_", implode(' ', [_BIB_REF_MAPS_PATH_ . 'bible_aliases.map',
       _BIB_REF_MAPS_PATH_ . 'books_of_the_bible_to_code.map', _BIB_REF_MAPS_PATH_ . 'books_of_the_bible_to_canonical_form.map',
       _BIB_REF_MAPS_PATH_ . 'pericopes_to_codes.map']));

class QueryBuilder extends \VuFindSearch\Backend\Solr\QueryBuilder
{
    const BIBLE_REFERENCE_COMMAND = '/usr/local/bin/bib_ref_to_codes_tool';
    const BIBLE_REFERENCE_COMMAND_PARAMETERS = _BIB_REF_CMD_PARAMS_;

    public function build(AbstractQuery $query)
    {
        // TODO: Bei Erweiterter Suche wird eine andere Query-Klasse genutzt.
        // Diese muss anders behandelt werden, da sie aus vielen Sub-Queries
        // besteht. Vorerst wird die Bibelstellensuche nur bei der Standartsuche
        // angewendet, wenn direkt für Bibelstellen gesucht wird.
        if (is_a($query, 'VuFindSearch\Query\QueryGroup') || $query->getHandler() !== "BibleRangeSearch") {
            return parent::build($query);
        }
        $queryString = $query->getString();
        $newQuery = $this->getManipulatedQueryString($query);
        $result = parent::build($query);
        $result->set('q', $newQuery);
        $query->setString($queryString);
        $result->set('defType', 'bibleRangeParser');
        return $result;
    }

    private function getManipulatedQueryString(AbstractQuery $query)
    {
        $bibleReferences = $this->parseBibleReference($query);
        return $this->translateToSearchString($bibleReferences);
    }

    private function parseBibleReference(AbstractQuery $query)
    {
        $searchQuery = $query->getString();
        if (!empty($searchQuery)) {
            $cmd = $this->getBibleReferenceCommand($searchQuery);
            exec($cmd, $output, $return_var);
            return $output;
        }
        return array();
    }

    private function translateToSearchString($bibleReferences)
    {
        if (empty($bibleReferences)) {
            // if no bible references were found for given query, search for a range which doesn't exist to get no result.
            $bibleReferences = ["99999999_99999999"];
        }
        $searchString = implode(',', $bibleReferences);
        return $searchString;
    }

    private function getBibleReferenceCommand($searchQuery)
    {
        setlocale(LC_CTYPE, "de_DE.UTF-8");
        return implode(' ', [
            self::BIBLE_REFERENCE_COMMAND,
            "--query",
            escapeshellarg($searchQuery),
            self::BIBLE_REFERENCE_COMMAND_PARAMETERS
        ]);
    }
}
