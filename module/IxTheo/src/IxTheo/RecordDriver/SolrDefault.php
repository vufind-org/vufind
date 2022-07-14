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
    public function getHighlightedCorporation()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        return (isset($this->highlightDetails['corporation'][0]))
            ? $this->highlightDetails['corporation'][0] : '';
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


    private static function IntDiv($numerator, $denominator)
    {
        return (int)($numerator / $denominator);
    }

    private static function HasChapter($code)
    {
        return ($code % 1000000 != 999999) && ((self::IntDiv($code, 1000) % 1000) != 0);
    }

    private static function HasVerse($code)
    {
        return ($code % 1000000 != 999999) && (($code % 1000) != 0);
    }

    private static function GetBookCode($code)
    {
        return self::IntDiv($code, 1000000);
    }

    private static function GetChapter($code)
    {
        return self::IntDiv($code, 1000) % 1000;
    }

    private static function GetVerse($code)
    {
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
        44 => "Esth",
        45 => "Job",
        46 => "Ps",
        47 => "Prov",
        48 => "Eccl",
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

    private static $CodesToCodexTitles = [  1 => 'CIC17',
                                            2 => 'CIC83',
                                            3 => 'CCEO',
    ];

    private static function DecodeBookCode($book_code, $separator)
    {
        $book_code_as_string = self::$codes_to_book_abbrevs[self::GetBookCode($book_code)];
        if (!self::HasChapter($book_code))
            return $book_code_as_string;
        $book_code_as_string .= " " . strval(self::GetChapter($book_code));
        if (!self::HasVerse($book_code))
            return $book_code_as_string;
        return $book_code_as_string . $separator . strval(self::GetVerse($book_code));
    }

    private static function DecodeChapterVerse($book_code, $separator)
    {
        $chapter_code_as_string = "";

        if (!self::HasChapter($book_code))
            return $chapter_code_as_string;
        $chapter_code_as_string .= strval(self::GetChapter($book_code));
        if (!self::HasVerse($book_code))
            return $chapter_code_as_string;
        $verse = self::GetVerse($book_code);
        if ($verse != 0 && $verse != 999)
            return $chapter_code_as_string . $separator . strval(self::GetVerse($book_code));
        else
            return $chapter_code_as_string;
    }

    private static function BibleRangeToDisplayString($bible_range, $language_code)
    {
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
        return $codes_as_string . self::DecodeChapterVerse($code1, $separator) . "–" . self::DecodeChapterVerse($code2, $separator);
    }

    private static function CanonLawRangePartToArray($canonLawRangePart): array
    {
        // see also: https://github.com/ubtue/tuefind/wiki/Codices
        if (strlen($canonLawRangePart) != 9)
            throw new \Exception('Invalid canon law range part: ' . $canonLawRangePart);

        $codexId = $canonLawRangePart[0];
        $codexTitle = self::$CodesToCodexTitles[$codexId] ?? null;
        if ($codexTitle === null)
            throw new \Exception('Invalid codex id: ' . $codexId);

        return ['codexId' => $codexId,
                'codexTitle' => $codexTitle,
                'canon' => intval(substr($canonLawRangePart, 1, 4)),
                'pars1' => intval(substr($canonLawRangePart, 5, 2)),
                'pars2' => intval(substr($canonLawRangePart, 7, 2)),
        ];
    }

    private static function CanonLawRangeToDisplayString($canonLawRange): string
    {
        list ($canonLawRangeStart, $canonLawRangeEnd) = explode('_', $canonLawRange);
        $canonLawRangeStart = self::CanonLawRangePartToArray($canonLawRangeStart);
        $canonLawRangeEnd = self::CanonLawRangePartToArray($canonLawRangeEnd);

        $displayString = $canonLawRangeStart['codexTitle'];

        if ($canonLawRangeStart['canon'] == 0 && $canonLawRangeEnd['canon'] == 9999)
            return $displayString;
        $displayString .= ' can. ' . $canonLawRangeStart['canon'];

        if ($canonLawRangeStart['pars1'] . $canonLawRangeStart['pars2'] != 0) {
            if ($canonLawRangeStart['pars1'] != $canonLawRangeEnd['pars1']) {
                $displayString .= ', §§' . $canonLawRangeStart['pars1'] . '-' . $canonLawRangeEnd['pars1'];
            }  else if ($canonLawRangeStart['pars2'] != $canonLawRangeEnd['pars2']) {
                $displayString .= ', §' . $canonLawRangeStart['pars1'] . ' n. ' . $canonLawRangeStart['pars2'] . '-' . $canonLawRangeEnd['pars2'];
            } else {
                $displayString .= ', §' . $canonLawRangeStart['pars1'];
                if ($canonLawRangeStart['pars2'] != 99 && $canonLawRangeStart['pars2'] == $canonLawRangeEnd['pars2']) {
                    $displayString .= ' n. ' . $canonLawRangeStart['pars2'];
                }
                else if ($canonLawRangeStart['pars2'] != $canonLawRangeEnd['pars2']) {
                    $displayString .= '-' . $canonLawRangeStart['pars2'];
                }
            }
        }

        if ($canonLawRangeStart['canon'] != $canonLawRangeEnd['canon']) {
            $displayString .= '-' . $canonLawRangeEnd['canon'];
            if ($canonLawRangeEnd['pars1'] . $canonLawRangeEnd['pars2'] != 9999) {
                $displayString .= ', §' . $canonLawRangeEnd['pars1'];
                if ($canonLawRangeEnd['pars2'] != 99 && $canonLawRangeStart['pars2'] == $canonLawRangeEnd['pars2'])
                    $displayString .= ' n. ' . $canonLawRangeEnd['pars2'];
                else if ($canonLawRangeEnd['pars1'] != $canonLawRangeEnd['pars2'])
                    $displayString .= '-' . $canonLawRangeEnd['pars2'];
            }
        }

        return $displayString;
    }

    public function getBibleRangesString()
    {
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

    public function getBundleIds(): array
    {
        return $this->fields['bundle_id'] ?? [];
    }

    public function getCanonLawRangesStrings(): array
    {
        $canonLawRanges = $this->fields['canon_law_ranges'] ?? null;
        if ($canonLawRanges === null)
            return [];

        $canonLawRanges = explode(',', $canonLawRanges);
        $canonLawRangesStrings = [];
        foreach ($canonLawRanges as $canonLawRange) {
            $canonLawRangesStrings[] = self::CanonLawRangeToDisplayString($canonLawRange);
        }
        sort($canonLawRangesStrings);
        return $canonLawRangesStrings;
    }

    public function getKeyWordChainBag($languageSuffix=null)
    {
        $key = 'key_word_chain_bag';
        if (isset($languageSuffix))
            $key .= '_' . $languageSuffix;
        return isset($this->fields[$key]) ?
            $this->fields[$key] : [];
    }

    public function getPrefix4KeyWordChainBag()
    {
        return isset($this->fields['prefix4_key_word_chain_bag']) ?
            $this->fields['prefix4_key_word_chain_bag'] : '';
    }

    public function getTopicsForCloud($language=null): array
    {
        $key = 'topic_cloud';
        if (isset($language))
            $key .= '_' . $language;
        return array_unique($this->fields[$key] ?? []);
    }


    public function isAvailableForPDA()
    {
        return isset($this->fields['is_potentially_pda']) && $this->fields['is_potentially_pda'];
    }
    

    public function getAllClassification()
    {
        $result = array();
        if(isset($this->fields['ixtheo_notation']) && is_array($this->fields['ixtheo_notation'])) {
            $ixtheo_notation = $this->fields['ixtheo_notation'];
            foreach($ixtheo_notation as $notation) {
                $result[] = $notation;
            }
        }
        return $result;
    }
}
