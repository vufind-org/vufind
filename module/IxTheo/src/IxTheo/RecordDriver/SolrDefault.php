<?php

namespace IxTheo\RecordDriver;

class SolrDefault extends \TueFind\RecordDriver\SolrMarc
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Get a highlighted corporation string, if available.
     *
     * @return string
     */
    public function getHighlightedCorporation(){
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->highlightDetails['corporation'][0]))
            ? $this->highlightDetails['corporation'][0] : '';
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->getShortTitle();
        $subtitle = $this->getSubtitle();
        $titleSection = $this->getTitleSection();
        if (!empty($subtitle)) { $title .= ' : ' . $subtitle; }
        if (!empty($titleSection)) { $title .= ' / ' . $titleSection; }
        return $title;
    }

    /**
     * Get an array of publication detail lines combining information from
     * getPublicationDates(), getPublishers()
     *
     * @return array
     */
    public function getPublicationDetailsNoPlaces(){
        $names = $this->getPublishers();
        $dates = $this->getHumanReadablePublicationDates();

        $i = 0;
        $retval = [];
        while (isset($names[$i]) || isset($dates[$i])) {
            // Build objects to represent each set of data; these will
            // transform seamlessly into strings in the view layer.
            $retval[] = new \VuFind\RecordDriver\Response\PublicationDetails(
                isset($names[$i]) ? $names[$i] : '',
                isset($dates[$i]) ? $dates[$i] : '',
                null
            );
            $i++;
        }

        return $retval;
    }

    /**
     * Get secondary author and its role in a '$'-separated string
     *
     * @return array
     */
    public function getSecondaryAuthorsAndRole(){
        return isset($this->fields['author2_and_role']) ?
            $this->fields['author2_and_role'] : [];
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthors()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        if (!isset($this->fields['author2_and_role']))
            return [];

        $authors = array();
        foreach ($this->fields['author2_and_role'] as $author_and_roles) {
            $parts = explode('$', $author_and_roles);
            $authors[] = $parts[0];
        }

        return $authors;
    }

    /**
     * Get an array of all secondary authors roles (complementing
     * getPrimaryAuthorsRoles()).
     *
     * @return array
     */
    public function getSecondaryAuthorsRoles()
    {
        if (!isset($this->fields['author2_and_role']))
            return [];

        $roles = array();
        foreach ($this->fields['author2_and_role'] as $author_and_roles) {
            $parts = explode('$', $author_and_roles);
            $roles[] = array_slice($parts, 1);
        }

        return $roles;
    }

    /**
     * Helper function to restructure author arrays including relators
     *
     * @param array $authors Array of authors
     * @param array $roles   Array with relators of authors
     *
     * @return array
     */
    protected function getAuthorRolesArray($authors = [], $roles = [])
    {
        $authorRolesArray = [];

        if (!empty($authors)) {
            foreach ($authors as $index => $author) {
                if (!isset($authorRolesArray[$author])) {
                    $authorRolesArray[$author] = [];
                }
                if (isset($roles[$index]) && !empty($roles[$index])) {
                    if (is_array($roles[$index]))
                        $authorRolesArray[$author] = $roles[$index];
                    else
                        $authorRolesArray[$author][] = $roles[$index];
                }
            }
        }

        return $authorRolesArray;
    }

    /**
     * Get corporation.
     *
     * @return array
     */
    public function getCorporation()
    {
        return isset($this->fields['corporation']) ?
            $this->fields['corporation'] : [];
    }


    private static function IntDiv($numerator, $denominator) {
        return (int)($numerator / $denominator);
    }

    private static function HasChapter($code) {
        return ($code % 1000000 != 999999) && ((self::IntDiv($code, 1000) % 1000) != 0);
    }

    private static function HasVerse($code) {
        return ($code % 1000000 != 999999) && (($code % 1000) != 0);
    }

    private static function GetBookCode($code) {
        return self::IntDiv($code, 1000000);
    }

    private static function GetChapter($code) {
        return self::IntDiv($code, 1000) % 1000;
    }

    private static function GetVerse($code) {
        return $code % 1000;
    }

    private static $codes_to_book_abbrevs = array(
        1 => "Mt",
        2 => "Mk",
        3 => "Lk",
        4 => "Jn",
        5 => "Acts",
        6 => "Rom",
        7 => "1 Cor",
        8 => "2 Cor",
        9 => "Gal",
        10 => "Eph",
        11 => "Phil",
        12 => "Col",
        13 => "1 Thess",
        14 => "2 Thess",
        15 => "1 Tim",
        16 => "2 Tim",
        17 => "Titus",
        18 => "Philemon",
        19 => "Heb",
        20 => "Jas",
        21 => "1 Pet",
        22 => "2 Pet",
        23 => "1 Jn",
        24 => "2 Jn",
        25 => "3 Jn",
        26 => "Jude",
        27 => "Rev",
        28 => "Gen",
        29 => "Ex",
        30 => "Lev",
        31 => "Num",
        32 => "Deut",
        33 => "Josh",
        34 => "Judg",
        35 => "Ruth",
        36 => "1 Sam",
        37 => "2 Sam",
        38 => "1 Kings",
        39 => "2 Kings",
        40 => "1 Chr",
        41 => "2 Chr",
        42 => "Ezra",
        43 => "Neh",
        44 => "Eth1",
        45 => "Job",
        46 => "Ps",
        47 => "Prov",
        48 => "Ecc1",
        49 => "Song",
        50 => "Isa",
        51 => "Jer",
        52 => "Lam",
        53 => "Ezek",
        54 => "Dan",
        55 => "Hos",
        56 => "Joel",
        57 => "Am",
        58 => "Obadiah",
        59 => "Jon",
        60 => "Mic",
        61 => "Nah",
        62 => "Hab",
        63 => "Zeph",
        64 => "Hag",
        65 => "Zech",
        66 => "Mal",
        67 => "3 Ezra",
        68 => "4 Ezra",
        69 => "1 Macc",
        70 => "2 Macc",
        71 => "3 Macc",
        72 => "4 Macc",
        73 => "Tob",
        74 => "Jdt",
        75 => "Bar",
        77 => "Sir",
        78 => "Wis",
        81 => "6 Macc",
        82 => "5 Ezra",
        83 => "6 Ezra",
        84 => "",
        85 => "",
    );

    private static function DecodeBookCode($book_code, $separator) {
        $book_code_as_string = self::$codes_to_book_abbrevs[self::GetBookCode($book_code)];
        if (!self::HasChapter($book_code))
            return $book_code_as_string;
        $book_code_as_string .= " " . strval(self::GetChapter($book_code));
        if (!self::HasVerse($book_code))
            return $book_code_as_string;
        return $book_code_as_string . $separator . strval(self::GetVerse($book_code));
    }

    private static function BibleRangeToDisplayString($bible_range, $language_code) {
        $separator = (substr($language_code, 0, 2) == "de") ? "," : ":";
        $code1 = (int)substr($bible_range, 0, 8);
        $code2 = (int)substr($bible_range, 9, 8);
        if ($code1 + 999999 == $code2)
            return self::DecodeBookCode($code1, $separator);
        if (self::GetBookCode($code1) != self::GetBookCode($code2))
            return self::DecodeBookCode($code1, $separator) . " – " . self::DecodeBookCode($code2, $separator);
        $codes_as_string = self::$codes_to_book_abbrevs[self::GetBookCode($code1)] . " ";
        $chapter1 = self::GetChapter($code1);
        $chapter2 = self::GetChapter($code2);
        if ($chapter1 == $chapter2) {
            $codes_as_string .= strval($chapter1);
            $verse1 = self::GetVerse($code1);
            $verse2 = self::GetVerse($code2);
            if ($verse1 == $verse2)
                return $codes_as_string . $separator . strval($verse1);
            elseif ($verse1 == 0 && $verse2 == 999)
                return $codes_as_string;
            else
                return $codes_as_string . $separator . strval($verse1) . "–" . strval($verse2);
        }
        return $codes_as_string . strval($chapter1) . "–" . strval($chapter2);
    }

    public function getBibleRangesString() {
        if (!isset($this->fields['bible_ranges']))
            return "";
        $language_code = $this->getTranslatorLocale();
        $bible_references = "";
        foreach (explode(',', $this->fields['bible_ranges']) as $bible_range) {
            if (!empty($bible_references))
                $bible_references .= ", ";
            $bible_references .= self::BibleRangeToDisplayString($bible_range, $language_code);
        }
        return $bible_references;
    }
    
    /** Check whether a record is potentially available for PDA
     *
     * @return bool
     */
    public function isPotentiallyPDA()
    {
        return isset($this->fields['is_potentially_pda']) && $this->fields['is_potentially_pda'];
    }
}
