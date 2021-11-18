<?php

namespace TueFind\RecordDriver;

class SolrMarc extends SolrDefault
{
    const ISIL_PREFIX_GND = '(DE-588)';
    const ISIL_PREFIX_K10PLUS = '(DE-627)';

    use MarcAdvancedTrait;

    /**
     * Search for author and return its id (e.g. GND number or PPN)
     *
     * @param string $author_heading    name of the author and birth/death years if exist, e.g. "Strecker, Christian 1960-"
     * @param string $scheme_prefix     see class constants (ISIL_PREFIX_*)
     * @return string
     */
    protected function getAuthorIdByHeading($author_heading, $scheme_prefix)
    {
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

    public function getAuthorGNDNumber($author_heading)
    {
        return $this->getAuthorIdByHeading($author_heading, self::ISIL_PREFIX_GND);
    }

    public function getAuthorPPN($author_heading)
    {
        return $this->getAuthorIdByHeading($author_heading, self::ISIL_PREFIX_K10PLUS);
    }

    /**
     * Special function for metadata vocabularies.
     * Get names of all authors from 100a/700a only.
     * (Special request from Google Scholar, b+c are completely omitted,
     * even if this does not make sense in all cases, because
     * the result might only be e.g. "Thomas" instead of "Thomas von Aquin"
     * or "Benedikt" instead of "Benedikt XVI.")
     */
    public function getAuthorNames(): array
    {
        $authorNames = [];
        $authors = $this->getMarcRecord()->getFields('^100|700$', true);
        foreach ($authors as $author) {
            $authorName = $author->getSubfield('a')->getData();
            $authorNames[] = $authorName;
        }
        return array_unique($authorNames);
    }

    /**
     * This function is used to get author roles for a given author from MARC.
     * VuFind only stores the first roles in Solr. If MARC roles cannot be found,
     * Solr roles can be passed as fallback.
     *
     * (same result format as getPrimaryAuthorsRoles: ['role' => []])
     *
     * @param string $author_heading
     * @param array $fallback_roles
     * @return array
     */
    public function getAuthorRoles($author_heading)
    {
        $roles = [];
        $authors = $this->getMarcRecord()->getFields('^100|700$', true);
        foreach ($authors as $author) {
            $subfield_a = $author->getSubfield('a');
            if ($subfield_a != false && $subfield_a->getData() == $author_heading) {
                $subfields_4 = $author->getSubfields('4');
                foreach ($subfields_4 as $subfield_4) {
                    $roles[] = $subfield_4->getData();
                }
                break;
            }
        }
        return $roles;
    }

    /**
     * Get DOI from 024 instead of doi_str_mv field
     *
     * @return string
     */
    public function getCleanDOIs()
    {
        $clean_dois = [];
        $_024_fields = $this->getMarcRecord()->getFields('024');
        if (!$_024_fields)
            return;
        foreach ($_024_fields as $_024_field) {
            $subfields = $this->getSubfieldArray($_024_field, ['a', '2'], false);
            if ($subfields && count($subfields) == 2) {
                if (strtolower($subfields[1]) == 'doi')
                    $clean_dois[] = $subfields[0];
            }
        }
        return $clean_dois;
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
    protected function getFieldsArray($fields_and_subfields, $concat=true, $separator=' ')
    {
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


    public function getKflId(): ?string
    {
        // So far, only available for "Handbuch der Religionen".
        // Implementation will be changed as soon as
        // additional information about MARC fields is provided.
        if (in_array('1677766123', $this->fields['ids']))
            return 'handbuch-religionen';

        return null;
    }

    public function getKflEntitlement(): ?string
    {
        // So far, only available for "Handbuch der Religionen".
        // Implementation will be changed as soon as
        // additional information about MARC fields is provided.
        return null;
    }


    public function getLicense(): ?array
    {
        $licenseFields = $this->getMarcRecord()->getFields('540');
        foreach ($licenseFields as $licenseField) {
            $id = null;
            $idSubfield = $licenseField->getSubfield('f');
            if ($idSubfield != false)
                $id = $idSubfield->getData();

            $url = null;
            $urlSubfield = $licenseField->getSubfield('u');
            if ($urlSubfield != false)
                $url = $urlSubfield->getData();

            if ($id != null && $url != null)
                return ['id' => $id, 'url' => $url];
        }

        return null;
    }


    public function isTADTagged()
    {
        $ita_fields = $this->getMarcRecord()->getFields('ITA');
        foreach ($ita_fields as $ita_field) {
            $t_subfields = $this->getSubfieldArray($ita_field, ['t']);
            if (count($t_subfields) > 0)
                return true;
        }
        return false;
    }


    public function isArticle()
    {
        $leader = $this->getMarcRecord()->getLeader();

        if ($leader[7] == 'a' || $leader[7] == 'b')
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

    public function isArticleCollection()
    {
        $aco_fields = $this->getMarcRecord()->getFields('ACO');
        return (count($aco_fields) > 0);
    }

    public function isPrintedWork() {
        $fields = $this->getMarcRecord()->getFields('007');
        foreach ($fields as $field) {
            if ($field->getData()[0] == 't')
                return true;
        }
        return false;
    }

    public function isSubscriptionBundle(): bool
    {
        return ($this->isSuperiorWork() && in_array('Subscription Bundle', $this->getFormats()));
    }

    public function workIsTADCandidate(): bool
    {
        return ($this->isArticle() || $this->isArticleCollection()) && $this->isPrintedWork() && $this->isTADTagged();
    }

    public function workIsKfLCandidate(): bool
    {
        return ($this->getKflId() != null);
    }

    public function suppressDisplayByFormat()
    {
        if (in_array('Weblog', $this->getFormats()))
            return true;
        if (in_array('Subscription Bundle', $this->getFormats()))
            return true;
        if (in_array('Literary Remains', $this->getFormats()))
            return true;
        return false;
    }

    public function showContainerIdsAndTitles()
    {
        return (!empty($this->getContainerIDsAndTitles())
                || $this->getIssue() || $this->getPages()
                || $this->getVolume() || $this->getYear());
    }

    public function showHBZ()
    {
        return !$this->suppressDisplayByFormat();
    }

    public function showJOP()
    {
        return (count($this->getFormats()) > 0);
    }

    public function showPDA()
    {
        $formats = $this->getFormats();
        return (!empty($formats) && (in_array('Book', $formats)) && $this->isAvailableForPDA());
    }

    public function showSubito()
    {
        return !$this->suppressDisplayByFormat() && $this->getSubitoURL() != '';
    }

    public function getParallelEditionPPNs()
    {
        $parallel_ppns_and_type = [];
        foreach (['775', '776'] as $tag) {
            $fields = $this->getMarcRecord()->getFields($tag);
            foreach ($fields as $field) {
                $subfields_w = $this->getSubfieldArray($field, ['w'], false /* do not concatenate entries */);
                foreach ($subfields_w as $subfield_w) {
                    if (preg_match('/^' . preg_quote(self::ISIL_PREFIX_K10PLUS, '/') . '(.*)/', $subfield_w, $ppn)) {
                        $subfield_k = $field->getSubfield('k');
                        if ($subfield_k !== false && $subfield_k->getData() !== 'dangling')
                            array_push($parallel_ppns_and_type, [ $ppn[1], $subfield_k->getData() ]);
                    }
                }
            }
        }
        return $parallel_ppns_and_type;
    }


    public function getUnlinkedParallelEditions()
    {
        $parallel_editions = [];
        foreach (['775', '776'] as $tag) {
            $fields = $this->getMarcRecord()->getFields($tag);
            foreach ($fields as $field) {
                # If $w exists this is handled by getParallelEditionPPNs
                $subfield_w = $field->getSubfield('w');
                if (!empty($subfield_w))
                    continue;

                $parallel_edition = '';
                $subfield_i = $field->getSubfield('i');
                # If $i is not given we will not have a proper key for processing
                if (empty($subfield_i))
                    continue;
                $subfield_a = $field->getSubfield('a');
                if (!empty($subfield_a))
                    $parallel_edition .= $subfield_a->getData() . ': ';
                $further_subfields = $this->getSubfieldArray($field, ['t','d','h','g','o','u','z'], false);
                $parallel_edition .= implode('. - ', $further_subfields);
                $description = \TueFind\Utility::normalizeGermanParallelDescriptions($subfield_i->getData());
                array_push($parallel_editions, [ $description => $parallel_edition ]);
            }
        }
        return $parallel_editions;
    }


    protected function getFirstK10PlusPPNFromSubfieldW($field)
    {
        $subfields_w = $this->getSubfieldArray($field, ['w'], false /* do not concatenate entries */);
        foreach ($subfields_w as $subfield_w) {
            if (preg_match('/^' . preg_quote(self::ISIL_PREFIX_K10PLUS, '/') . '(.*)/', $subfield_w, $match_ppn)) {
                return $match_ppn[1];
            }
        }
    }


    public function getReferenceInformation(): array
    {
        $references = [];
        $fields = $this->getMarcRecord()->getFields('770|772', true);
        foreach ($fields as $field) {
            $opening = $field->getSubfield('i') ? $field->getSubfield('i')->getData() . ':' : '';
            $timeRange = $field->getSubfield('n') ? $field->getSubfield('n')->getData() : '';
            if ($timeRange != '') {
                if ($opening != '')
                    $opening .= ' ';
                $opening .= '(' . $timeRange . '):';
            }
            $titles = [];
            $field->getSubfield('a') ? $titles[] = $field->getSubfield('a')->getData() : '';
            $field->getSubfield('d') ? $titles[] = $field->getSubfield('d')->getData() : '';
            $field->getSubfield('h') ? $titles[] = $field->getSubfield('h')->getData() : '';
            $field->getSubfield('t') ? $titles[] = $field->getSubfield('t')->getData() : '';
            $description = $opening . ' ' .  implode(', ' , array_filter($titles) /*skip empty elements */);
            $link_ppn = $this->getFirstK10PlusPPNFromSubfieldW($field);
            $references[] = ['id' => $link_ppn, 'description' => $description];
        }
        return array_merge($references, $this->getOtherReferences());
    }


    public function getContainsInformation(): array
    {
        $contains = [];
        $fields = $this->getMarcRecord()->getFields('773');
        foreach ($fields as $field) {
            if ($field->getIndicator(1) != 0)
                continue;
            $opening = $field->getSubfield('i') ? $field->getSubfield('i')->getData() : '';
            $titles = [];
            $field->getSubfield('a') ? $titles[] = $field->getSubfield('a')->getData() : '';
            $field->getSubfield('t') ? $titles[] = $field->getSubfield('t')->getData() : '';
            $description = $opening . ': ' .  implode(', ', array_filter($titles) /*skip empty elements */);
            $link_ppn = $this->getFirstK10PlusPPNFromSubfieldW($field);
            $contains[] = ['id' => $link_ppn, 'description' => $description];
        }
        return $contains;
    }


    protected function getReferencesFrom787(): array
    {
        $references = [];

        $fields = $this->getMarcRecord()->getFields('787');
        foreach ($fields as $field) {
            $iSubfield = $field->getSubfield('i');
            if ($iSubfield == false)
                continue;

            $aSubfield = $field->getSubfield('a');
            $dSubfield = $field->getSubfield('d');
            $tSubfield = $field->getSubfield('t');
            $gSubfield = $field->getSubfield('g');

            $title = '';
            if ($tSubfield != false )
                $title = $tSubfield->getData();
            elseif ($aSubfield != false)
                $title = $aSubfield->getData();

            if ($gSubfield != false) // Year and Page information
	        $title .= ' ' . $gSubfield->getData();

            if ($dSubfield != false)
                $title .= ' (' . $dSubfield->getData() . ')';

            $referencedId = null;
            $ppn = $this->getFirstK10PlusPPNFromSubfieldW($field);
            if (!empty($ppn))
                $referencedId = $ppn;

            $type = $iSubfield->getData();
            if (preg_match('"^(Rezension|Rezensiert in)(:)?$"i', $type)) {
                $resultType = 'review';
                if (!isset($references[$resultType]))
                    $references[$resultType] = [];

                $author = '';
                if ($aSubfield != false)
                    $author = $aSubfield->getData();

                $references[$resultType][] = ['id' => $referencedId,
                                              'title' => $title,
                                              'author' => $author];
            } else if (preg_match('"^Rezension von$"i', $type)) {
                $resultType = 'reviewed_record';
                if (!isset($references[$resultType]))
                    $references[$resultType] = [];
                $author = '';
                $authorFields = $this->getMarcRecord()->getFields('100');
                foreach ($authorFields as $authorField) {
                    $a100Subfields = $authorField->getSubfields('a');
                    foreach ($a100Subfields as $a100Subfield) {
                        $a100 = $a100Subfield->getData();
                        if ($a100 != '') {
                            $author = $a100;
                            break 2;
                        }
                    }
                }
                $references[$resultType][] = ['id' => $referencedId,
                                              'title' => $title,
                                              'author' => $author];
            } else {
                $resultType = 'other';
                if (!isset($references[$resultType]))
                    $references[$resultType] = [];
                $references[$resultType][] = ['id' => $referencedId,
                                              'description' => $type . ' "' . $title . '"'];
            }
        }

        return $references;
    }

    public function getReviews(): array
    {
        return $this->getReferencesFrom787()['review'] ?? [];
    }

    public function getReviewedRecords(): array
    {
        return $this->getReferencesFrom787()['reviewed_record'] ?? [];
    }

    protected function getOtherReferences(): array
    {
        return $this->getReferencesFrom787()['other'] ?? [];
    }


    public function cleanISSN($issn)
    {
        if ($pos = strpos($issn, ' ')) {
            $issn = substr($issn, 0, $pos);
        }
        return $issn;
    }


    public function getJOPISSNsAndAdditionalInformation()
    {
        $issns_and_additional_information = [];
        $_022fields = $this->getMarcRecord()->getFields('022');
        foreach ($_022fields as $_022field) {
            $subfield_a = $_022field->getSubfield('a') ? $_022field->getSubfield('a')->getData() : ''; //$a is non-repeatable in 022
            if (!empty($subfield_a)) {
                $subfield_2 = $_022field->getSubfield('2') ? $_022field->getSubfield('2')->getData() : '';
                $additional_information = empty($subfield_2) ? '' : $this->translate($subfield_2);
                $subfield_3 = $_022field->getSubfield('3') ? $_022field->getSubfield('3')->getData() : '';
                if (!empty($subfield_3)) {
                    if (!empty($additional_information))
                        $additional_information .= ' ';
                    $additional_information .= $subfield_3;
                }
                $issns_and_additional_information[$this->cleanISSN($subfield_a)] = $additional_information;
            }
        }
        $_029fields = $this->getMarcRecord()->getFields('029');
        foreach ($_029fields as $_029field) {
            if ($_029field->getIndicator('1') == 'x') {
                switch ($_029field->getIndicator('2')) {
                    case 'c':
                        $subfield_a = $_029field->getSubfield('a') ? $_029field->getSubfield('a')->getData() : '';
                        $issn = $this->cleanISSN($subfield_a);
                        if (!array_key_exists($issn, $issns_and_additional_information))
                            $issns_and_additional_information[$issn] = '';
                        break;
                    default:
                        break;
                }
            }
        }
        if (!empty($issns_and_additional_information))
            return $issns_and_additional_information;

        // Fall back to the ISSN of the parallel (print) edition
        $_776fields = $this->getMarcRecord()->getFields('776');
        foreach ($_776fields as $_776field) {
            $subfield_x = $_776field->getSubfield('x') ? $_776field->getSubfield('x')->getData() : '';
            $issn = $this->cleanISSN($subfield_x);
            if (!empty($issn)) {
                $issns_and_additional_information[$issn] = '';
                return $issns_and_additional_information;
            }

        }
        return [];
    }

    public function getSuperiorFrom773a()
    {
        $_773_fields = $this->getMarcRecord()->getFields('773');
        foreach ($_773_fields as $_773_field) {
            $subfield_a = $_773_field->getSubfield('a') ? $_773_field->getSubfield('a')->getData() : '';
            if (!empty($subfield_a))
                return $subfield_a;
        }
    }
}
