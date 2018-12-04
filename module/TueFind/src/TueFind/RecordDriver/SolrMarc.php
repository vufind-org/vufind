<?php

namespace TueFind\RecordDriver;

class SolrMarc extends SolrDefault
{
    const SCHEME_PREFIX_GND = '(DE-588)';
    const SCHEME_PREFIX_BSZ = '(DE-576)';

    /**
     * Search for author and return its id (e.g. GND number or PPN)
     *
     * @param string $author_heading    name of the author and birth/death years if exist, e.g. "Strecker, Christian 1960-"
     * @param string $scheme_prefix     see class constants (SCHEME_PREFIX_*)
     * @return string
     */
    protected function getAuthorIdByHeading($author_heading, $scheme_prefix) {
        $authors = $this->getMarcRecord()->getFields('^100|700$', true);
        foreach ($authors as $author) {
            $subfield_a = $author->getSubfield('a');
            $subfield_d = $author->getSubfield('d');
            $current_author_heading = $subfield_a->getData();
            if ($subfield_d != false)
                $current_author_heading .= ' ' . $subfield_d->getData();

            if ($author_heading == $subfield_a->getData() || $author_heading == $current_author_heading) {
                $subfields_0 = $author->getSubfields('0');
                foreach ($subfields_0 as $subfield_0) {
                    if (preg_match('"^' . preg_quote($scheme_prefix) . '"', $subfield_0->getData()))
                        return substr($subfield_0->getData(), strlen($scheme_prefix));
                }
                break;
            }
        }
    }

    public function getAuthorGNDNumber($author_heading) {
        return $this->getAuthorIdByHeading($author_heading, self::SCHEME_PREFIX_GND);
    }

    public function getAuthorPPN($author_heading) {
        return $this->getAuthorIdByHeading($author_heading, self::SCHEME_PREFIX_BSZ);
    }

    /**
     * Get DOI from 024 instead of doi_str_mv field
     *
     * @return string
     */
    public function getCleanDOI() {
        $results = $this->getMarcRecord()->getFields('024');
        if (!$results)
            return;
        foreach ($results as $result) {
            $subfields = $this->getSubfieldArray($result, ['a', '2'], false);
            if ($subfields && count($subfields) == 2) {
                if (strtolower($subfields[1]) == 'doi');
                    return $subfields[0];
            }
        }
    }


    /**
     * Wrapper for parent's getFieldArray, allowing multiple fields to be
     * processed at once
     *
     * @param array $fields_and_subfields array(0 => field as string, 1 => subfields as array or string (string only 1))
     * @param bool $concat
     * @param string $separator
     *
     * @return array
     */
    protected function getFieldsArray($fields_and_subfields, $concat=true, $separator=' ') {
        $fields_array = array();
        foreach ($fields_and_subfields as $field_and_subfield) {
            $field = $field_and_subfield[0];
            $subfields = (isset($field_and_subfield[1])) ? $field_and_subfield[1] : null;
            if (!is_null($subfields) && !is_array($subfields)) $subfields = array($subfields);
            $field_array = $this->getFieldArray($field, $subfields, $concat, $separator);
            $fields_array = array_merge($fields_array, $field_array);
        }
        return array_unique($fields_array);
    }

    public function getSuperiorRecord() {
        $_773_field = $this->getMarcRecord()->getField("773");
        if (!$_773_field)
            return NULL;
        $subfields = $this->getSubfieldArray($_773_field, ['w'], /* $concat = */false);
        if (!$subfields)
            return NULL;
        $ppn = substr($subfields[0], 8);
        if (!$ppn || strlen($ppn) != 9)
            return NULL;
        return $this->getRecordDriverByPPN($ppn);
    }

    public function isAvailableInTubingenUniversityLibrary() {
        $ita_fields = $this->getMarcRecord()->getFields("ITA");
        return (count($ita_fields) > 0);
    }

    public function isArticle() {
        $leader = $this->getMarcRecord()->getLeader();

        if ($leader[7] == 'a')
            return true;
        $_935_fields = $this->getMarcRecord()->getFields('935');
        foreach ($_935_fields as $_935_field) {
            $c_subfields = $this->getSubfieldArray($_935_field, ['c']);
            foreach ($c_subfields as $c_subfield) {
                if ($c_subfield == 'sodr')
                    return true;
            }
        }

        return false;
    }

    public function isArticleCollection() {
        $aco_fields = $this->getMarcRecord()->getFields("ACO");
        return (count($aco_fields) > 0);
    }

    public function isPrintedWork() {
        $fields = $this->getMarcRecord()->getFields("007");
        foreach ($fields as $field) {
            if ($field->getData()[0] == 't')
                return true;
        }
        return false;
    }

    public function workIsTADCandidate() {
        return ($this->isArticle() || $this->isArticleCollection()) && $this->isPrintedWork() && $this->isAvailableInTubingenUniversityLibrary();
    }

    public function suppressDisplayByFormat() {
        if (in_array("Weblog", $this->getFormats()))
            return true;

        return false;
    }

    public function getParallelEditionPPNs() {
        $parallel_ppns_and_type = [];
        foreach (["775", "776"] as $tag) {
             $fields = $this->getMarcRecord()->getFields($tag);
             foreach ($fields as $field) {
                 $subfields_w = $this->getSubfieldArray($field, ['w'], false /* do not concatenate entries */);
                 foreach($subfields_w as $subfield_w) {
                     if (preg_match("/^" . preg_quote(self::SCHEME_PREFIX_BSZ) . "(.*)/", $subfield_w, $ppn)) {
                         $subfield_k = $field->getSubfield('k');
                         if ($subfield_k !== false && $subfield_k->getData() !== 'dangling')
                             array_push($parallel_ppns_and_type, [ $ppn[1], $subfield_k->getData() ]);
                     }
                 }
             }
        }
        return $parallel_ppns_and_type;
    }


    public function getUnlinkedParallelEditions() {
        $parallel_editions = [];
        foreach (["775", "776"] as $tag) {
            $fields = $this->getMarcRecord()->getFields($tag);
            foreach ($fields as $field) {
                # If $w exists this is handled by getParallelEditionPPNs
                $subfield_w = $field->getSubfield('w');
                if (!empty($subfield_w))
                    continue;

                $parallel_edition = "";
                $subfield_i = $field->getSubfield('i');
                # If $i is not given we will not have a proper key for processing
                if (empty($subfield_i))
                    continue;
                $subfield_a = $field->getSubfield('a');
                if (!empty($subfield_a))
                    $parallel_edition .= $subfield_a->getData() . ": ";
                $further_subfields = $this->getSubfieldArray($field, ['t','d','h','g','o','u','z'], false);
                $parallel_edition .= implode('. - ', $further_subfields);
                $description = \TueFind\Utility::normalizeGermanParallelDescriptions($subfield_i->getData());
                array_push($parallel_editions, [ $description => $parallel_edition ]);
            }
        }
        return $parallel_editions;
    }


    protected function getFirstBSZPPNFromSubfieldW(&$field, &$ppn) {
        $ppn = [];
        $subfields_w = $this->getSubfieldArray($field, ['w'], false /* do not concatenate entries */);
        foreach($subfields_w as $subfield_w) {
             if (preg_match("/^" . preg_quote(self::SCHEME_PREFIX_BSZ) . "(.*)/", $subfield_w, $match_ppn)) {
                 $ppn[0] = $match_ppn[1];
                 return;
             }
        }
    }


    public function getReferenceInformation() {
        $reference = [];
        $fields = $this->getMarcRecord()->getFields("770");
        foreach ($fields as $field) {
            $opening = $field->getSubfield('i') ? $field->getSubfield('i')->getData() : '';
            $field->getSubfield('a') ? array_push($reference, $field->getSubfield('a')->getData()) : '';
            $field->getSubfield('d') ? array_push($reference, $field->getSubfield('d')->getData()) : '';
            $field->getSubfield('h') ? array_push($reference, $field->getSubfield('h')->getData()) : '';
            $field->getSubfield('t') ? array_push($reference, $field->getSubfield('t')->getData()) : '';
            $this->getFirstBSZPPNFromSubfieldW($field, $link_ppn);
            $reference_description = $opening . ": " .  implode(", " , array_filter($reference) /*skip empty elements */);
            if (!empty($link_ppn))
                return "<a href=/Record/" . $link_ppn[0] . " target=\"_blank\">" . $reference_description . "</>";
            else
                return $reference_description;
        }
    }


    public function getContainsInformation() {
        $contains = [];
        $fields = $this->getMarcRecord()->getFields("772");
        foreach ($fields as $field) {
              $opening = $field->getSubfield('i') ? $field->getSubfield('i')->getData() : '';
              $field->getSubfield('a') ? array_push($contains, $field->getSubfield('a')->getData()) : '';
              $field->getSubfield('t') ? array_push($contains, $field->getSubfield('t')->getData()) : '';
              $contains_description = $opening . ": " .  implode(", ", array_filter($contains) /*skip empty elements */);
              $this->getFirstBSZPPNFromSubfieldW($field, $link_ppn);
              if (!empty($link_ppn))
                  return "<a href=/Record/" . $link_ppn[0] . " target=\"_blank\">" . $contains_description . "</>";
              else
                  return $contains_description;
        }
    }
}
