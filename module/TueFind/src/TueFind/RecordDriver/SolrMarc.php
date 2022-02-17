<?php

namespace TueFind\RecordDriver;

class SolrMarc extends SolrDefault
{
    const ISIL_PREFIX_GND = '(DE-588)';
    const ISIL_PREFIX_K10PLUS = '(DE-627)';

    use Feature\MarcAdvancedTrait;

    protected $marcReaderClass = \TueFind\Marc\MarcReader::class;

    /**
     * Search for author and return its id (e.g. GND number or PPN)
     *
     * @param string $author_heading    name of the author and birth/death years if exist, e.g. "Strecker, Christian 1960-"
     * @param string $scheme_prefix     see class constants (ISIL_PREFIX_*)
     * @return string
     */
    protected function getAuthorIdByHeading($author_heading, $scheme_prefix)
    {
        $authors = $this->getMarcReader()->getFieldsDelimiter('100|700');

        foreach ($authors as $author) {
            $subfield_a = $this->getSubfieldArray($author, ['a']);
            $subfield_d = $this->getSubfieldArray($author, ['d']);
            $current_author_heading = $subfield_a[0];
            if (isset($subfield_d[0])) {
                $current_author_heading .= ' ' . $subfield_d[0];
            }
            if ($author_heading == $subfield_a[0] || $author_heading == $current_author_heading) {
                $subfields_0 = $this->getSubfieldArray($author, ['0']);
                foreach ($subfields_0 as $subfield_0) {
                    if (preg_match('"^' . preg_quote($scheme_prefix) . '"', $subfield_0[0]))
                        return substr($subfield_0[0], strlen($scheme_prefix));
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
        $authors = $this->getMarcReader()->getFieldsDelimiter('100|700');

        foreach ($authors as $author) {
            $authorName = $this->getSubfieldArray($author, ['a']);
            if(!empty($authorName) && isset($authorName[0])) {
              $authorNames[] = $authorName[0];
            }

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
        $authors = $this->getMarcReader()->getFieldsDelimiter('100|700');
        foreach ($authors as $author) {
            $subfield_a = $this->getSubfieldArray($author, ['a']);
            if (!empty($authorName) && isset($authorName[0]) && $authorName[0]  == $author_heading) {
                $subfields_4 = $this->getSubfieldArray($author, ['4']);
                foreach ($subfields_4 as $subfield_4) {
                    $roles[] = $subfield_4[0];
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
        $_024_fields = $this->getMarcReader()->getFields('024');
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


    public function getLicense(): ?array
    {
        $licenseFields = $this->getMarcReader()->getFields('540');
        foreach ($licenseFields as $licenseField) {
            $id = null;
            $idSubfield = $this->getMarcReader()->getSubfield($licenseField,'f');
            if ($idSubfield != false)
                $id = $idSubfield;

            $url = null;
            $urlSubfield = $this->getMarcReader()->getSubfield($licenseField,'u');
            if ($urlSubfield != false)
                $url = $urlSubfield;

            if ($id == null && preg_match('"^http(s)?:' . preg_quote('//rightsstatements.org/vocab/InC/1.0/', '"') . '$"', $url)) {
                // force correct ID + english URL
                $id = 'InC 1.0';
                $url = 'https://rightsstatements.org/page/InC/1.0/?language=en';
            }

            if ($id != null && $url != null)
                return ['id' => $id, 'url' => $url];
        }

        return null;
    }


    public function isTADTagged()
    {
        $ita_fields = $this->getMarcReader()->getFields('ITA');
        foreach ($ita_fields as $ita_field) {
            $t_subfields = $this->getSubfieldArray($ita_field, ['t']);
            if (count($t_subfields) > 0)
                return true;
        }
        return false;
    }


    public function isArticle()
    {
        $leader = $this->getMarcReader()->getLeader();

        if ($leader[7] == 'a' || $leader[7] == 'b')
            return true;
        $_935_fields = $this->getMarcReader()->getFields('935');
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
        $aco_fields = $this->getMarcReader()->getFields('ACO');
        return (count($aco_fields) > 0);
    }

    public function isPrintedWork() {
        $fields = $this->getMarcReader()->getFields('007');
        foreach ($fields as $field) {
            if ($field == 't')
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
            $fields = $this->getMarcReader()->getFields($tag);
            foreach ($fields as $field) {
                $subfields_w = $this->getSubfieldArray($field, ['w'], false /* do not concatenate entries */);
                foreach ($subfields_w as $subfield_w) {
                    if (preg_match('/^' . preg_quote(self::ISIL_PREFIX_K10PLUS, '/') . '(.*)/', $subfield_w, $ppn)) {
                        $subfield_k = $this->getMarcReader()->getSubfields($field,'k');
                        if ($subfield_k !== false && $subfield_k !== 'dangling')
                            array_push($parallel_ppns_and_type, [ $ppn[1], $subfield_k ]);
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
            $fields = $this->getMarcReader()->getFields($tag);
            foreach ($fields as $field) {
                # If $w exists this is handled by getParallelEditionPPNs
                $subfield_w = $this->getMarcReader()->getSubfield($field,'w');
                if (!empty($subfield_w))
                    continue;

                $parallel_edition = '';
                $subfield_i = $this->getMarcReader()->getSubfield($field,'i');
                # If $i is not given we will not have a proper key for processing
                if (empty($subfield_i))
                    continue;
                $subfield_a = $this->getMarcReader()->getSubfield($field,'a');
                if (!empty($subfield_a))
                    $parallel_edition .= $subfield_a . ': ';
                $further_subfields = $this->getSubfieldArray($field, ['t','d','h','g','o','u','z'], false);
                $parallel_edition .= implode('. - ', $further_subfields);
                $description = \TueFind\Utility::normalizeGermanParallelDescriptions($subfield_i);
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
        $fields = $this->getMarcReader()->getFieldsDelimiter('770|772');

        foreach ($fields as $field) {

            $opening = $this->getMarcReader()->getSubfield($field,'i') ? $this->getMarcReader()->getSubfield($field,'i') . ':' : '';
            $timeRange = $this->getMarcReader()->getSubfield($field,'n') ? $this->getMarcReader()->getSubfield($field,'n') : '';
            if ($timeRange != '') {
                if ($opening != '')
                    $opening .= ' ';
                $opening .= '(' . $timeRange . '):';
            }
            $titles = [];
            $this->getMarcReader()->getSubfield($field,'a') ? $titles[] = $this->getMarcReader()->getSubfield($field,'a') : '';
            $this->getMarcReader()->getSubfield($field,'d') ? $titles[] = $this->getMarcReader()->getSubfield($field,'d') : '';
            $this->getMarcReader()->getSubfield($field,'h') ? $titles[] = $this->getMarcReader()->getSubfield($field,'h') : '';
            $this->getMarcReader()->getSubfield($field,'t') ? $titles[] = $this->getMarcReader()->getSubfield($field,'t') : '';
            $description = $opening . ' ' .  implode(', ' , array_filter($titles) /*skip empty elements */);
            $link_ppn = $this->getFirstK10PlusPPNFromSubfieldW($field);
            $references[] = ['id' => $link_ppn, 'description' => $description];
        }
        return array_merge($references, $this->getOtherReferences());
    }


    public function getContainsInformation(): array
    {
        $contains = [];
        $fields = $this->getMarcReader()->getFields('773');
        foreach ($fields as $field) {
            $opening = $this->getMarcReader()->getSubfield($field,'i') ? $this->getMarcReader()->getSubfield($field,'i') : '';
            $titles = [];
            $this->getMarcReader()->getSubfield($field,'a') ? $titles[] = $this->getMarcReader()->getSubfield($field,'a') : '';
            $this->getMarcReader()->getSubfield($field,'t') ? $titles[] = $this->getMarcReader()->getSubfield($field,'t') : '';
            $description = $opening . ': ' .  implode(', ', array_filter($titles) /*skip empty elements */);
            $link_ppn = $this->getFirstK10PlusPPNFromSubfieldW($field);
            if(!empty($link_ppn)) {
                $contains[] = ['id' => $link_ppn, 'description' => $description];
            }

        }
        return $contains;
    }


    protected function getReferencesFrom787(): array
    {
        $references = [];

        $fields = $this->getMarcReader()->getFields('787');
        foreach ($fields as $field) {
            $iSubfield = $this->getMarcReader()->getSubfield($field,'i');
            if ($iSubfield == false)
                continue;

            $aSubfield = $this->getMarcReader()->getSubfield($field,'a');
            $dSubfield = $this->getMarcReader()->getSubfield($field,'d');
            $tSubfield = $this->getMarcReader()->getSubfield($field,'t');
            $gSubfield = $this->getMarcReader()->getSubfield($field,'g');

            $title = '';
            if ($tSubfield != false )
                $title = $tSubfield;
            elseif ($aSubfield != false)
                $title = $aSubfield;

            if ($gSubfield != false) // Year and Page information
	        $title .= ' ' . $gSubfield;

            if ($dSubfield != false)
                $title .= ' (' . $dSubfield . ')';

            $referencedId = null;
            $ppn = $this->getFirstK10PlusPPNFromSubfieldW($field);
            if (!empty($ppn))
                $referencedId = $ppn;

            $type = $iSubfield;
            if (preg_match('"^(Rezension|Rezensiert in)(:)?$"i', $type)) {
                $resultType = 'review';
                if (!isset($references[$resultType]))
                    $references[$resultType] = [];

                $author = '';
                if ($aSubfield != false)
                    $author = $aSubfield;

                $references[$resultType][] = ['id' => $referencedId,
                                              'title' => $title,
                                              'author' => $author];
            } else if (preg_match('"^Rezension von$"i', $type)) {
                $resultType = 'reviewed_record';
                if (!isset($references[$resultType]))
                    $references[$resultType] = [];
                $author = '';
                $authorFields = $this->getMarcReader()->getFields($field,'100');
                foreach ($authorFields as $authorField) {
                    $a100Subfields = $this->getMarcReader()->getSubfields($authorField,'a');
                    foreach ($a100Subfields as $a100Subfield) {
                        $a100 = $a100Subfield;
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
        $_022fields = $this->getMarcReader()->getFields('022');
        foreach ($_022fields as $_022field) {
            $subfield_a = $this->getMarcReader()->getSubfield($_022field,'a') ? $this->getMarcReader()->getSubfield($_022field,'a') : ''; //$a is non-repeatable in 022
            if (!empty($subfield_a)) {
                $subfield_2 = $this->getMarcReader()->getSubfield($_022field,'2') ? $this->getMarcReader()->getSubfield($_022field,'2') : '';
                $additional_information = empty($subfield_2) ? '' : $this->translate($subfield_2);
                $subfield_3 = $this->getMarcReader()->getSubfield($_022field,'3') ? $this->getMarcReader()->getSubfield($_022field,'3') : '';

                if (!empty($subfield_3)) {
                    if (!empty($additional_information))
                        $additional_information .= ' ';
                    $additional_information .= $subfield_3;
                }
                $issns_and_additional_information[$this->cleanISSN($subfield_a)] = $additional_information;
            }
        }

        $_029fields = $this->getMarcReader()->getFields('029');

        foreach ($_029fields as $_029field) {
            $x = $this->getMarcReader()->getSubfield($_029field,'x');
            if (!empty($x)) {
              $subfield_a = $this->getMarcReader()->getSubfield($_029field,'a') ? $this->getMarcReader()->getSubfield($_029field,'a') : '';
              $issn = $this->cleanISSN($subfield_a);
              if (!array_key_exists($issn, $issns_and_additional_information)) {
                $issns_and_additional_information[$issn] = '';
              }
            }
        }

        if (!empty($issns_and_additional_information))
            return $issns_and_additional_information;

        // Fall back to the ISSN of the parallel (print) edition
        $_776fields = $this->getMarcReader()->getFields('776');
        foreach ($_776fields as $_776field) {
            $subfield_x = $this->getMarcReader()->getSubfields($_776field,'x') ? $this->getMarcReader()->getSubfields($_776field,'x') : '';
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
        $_773_fields = $this->getMarcReader()->getFields('773');
        foreach ($_773_fields as $_773_field) {
            $subfield_a = $this->getMarcReader()->getSubfields($_773_field,'a') ? $this->getMarcReader()->getSubfields($_773_field,'a') : '';
            if (!empty($subfield_a))
                return $subfield_a;
        }
    }
}
