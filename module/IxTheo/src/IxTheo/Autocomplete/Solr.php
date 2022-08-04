<?php

namespace IxTheo\Autocomplete;

class Solr extends \VuFind\Autocomplete\Solr
{
    /**
     * Try to turn an array of record drivers into an array of suggestions.
     *
     * @param array  $searchResults An array of record drivers
     * @param string $query         User search query
     * @param bool   $exact         Ignore non-exact matches?
     *
     * @return array
     */
    protected function getSuggestionsFromSearch($searchResults, $query, $exact)
    {
        $results = [];
        foreach ($searchResults as $object) {
            $current = $object->getRawData();
            foreach ($this->displayField as $field) {
                if (isset($current[$field])) {
                    $bestMatch = $this->pickBestMatch(
                        $current[$field], $query, $exact
                    );
                    if ($bestMatch) {
                        $results[] = $this->mungeQuery($bestMatch);
                        break;
                    }
                }
            }
        }
        return (!empty($results)) ? array_diff($results, array("[Unassigned]*")) : $results;
    }


    public function getSuggestions($query) {
        $results = parent::getSuggestions($query);
        return (!empty($results)) ? array_diff($results, array("[Unassigned]*")) : $results;
    }

    /**
     * Process the user query to make it suitable for a Solr query.
     *
     * @param string $query Incoming user query
     *
     * @return string       Processed query
     */
    protected function mungeQuery($query)
    {
        // Modify the query so it makes a nice, truncated autocomplete query:
        $forbidden = [':', '(', ')', '*', '+', '"', '–' /* a hyphen, not a minus sign */, '='];
        $query = str_replace($forbidden, " ", $query);
        // Explanation for conditions:
        // first regex condition necessary for proper handling of author suggestions
        // second regex needed to do away with dot minus and number at the end
        // third regex needed to avoid ?* combinations that do not yield results
        // forth regex needed to avoid wildcarding of titles containing '-' since these would interfere
        if (substr($query, -1) != " " && !preg_match('/[.\-0-9]$/', $query) && !preg_match('/[?]$/', $query) && !preg_match('/-/', $query))
            $query .= "*";
        // Make sure we avoid empty results after a suggestion
        $escape = ['/'];
        $query = preg_replace('#(' . implode('|', $escape) . ')#', '\\\\' . '\\1', $query);
        return $query;
    }
}
