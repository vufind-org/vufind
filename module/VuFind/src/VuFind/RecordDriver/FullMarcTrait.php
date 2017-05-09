<?php
/**
 * Model for MARC records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  RecordDrivers
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Hannah Born <hannah.born@ub.uni-freiburg.de>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use VuFind\Exception\ILS as ILSException,
    VuFind\View\Helper\Root\RecordLink,
    VuFind\XSLT\Processor as XSLTProcessor;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Hannah Born <hannah.born@ub.uni-freiburg.de>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
trait FullMarcTrait
{

    /**
     * Return academia lettering 
     *
     * @return array
     */
    public function getAcademiaLettering()
    {
        return $this->getFieldArray('502', ['a']);
    }


    /**
     * Return the list of "source records" for this consortial record.
     *
     * @return array
     */
    public function getConsortialIDs()
    {
        return $this->getFieldArray('035', 'a', true);
    }

    /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal).  Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
        return $this->getFieldArray('362', ['a']);
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        return $this->getFirstFieldValue('250', ['a']);
    }

    /**
     * Get the external additional information
     *
     * @return array
     */
    public function getExternalAdditionalInformation()
    {
        $result = [];
        $total = $this->getFieldArray('856', ['u', '3', 'x'], true, "|");
        foreach ($total as $link) {
            $comp = explode("|", $link);
            if (count($comp) > 1) {
                $result[$comp[1]] = $comp[0];
            } else {
                $result[] = $comp[0];
            }
        } 
        return $result;
    }


    /**
     * Get an array of the volumes (for example: how many pages) 
     * associated with the record.
     *
     * @return array
     */
    public function getVolumes()
    {
        return $this->getFieldArray('300', ['a', 'b'], false);
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getMediaicon()
    {
        return $this->mapIcon($this->getFormats());
    }


    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        if ($this->formats === null) {
            $formats = [];
            $f007 = $f008 = $leader = null;
            $f007_0 = $f007_1 = $f008_21 = $leader_6 = $leader_7 = '';

            //field 007 - physical description
            $f007 = $this->getMarcRecord()->getFields("007", false);
            foreach ($f007 as $field) {
                $data = strtoupper($field->getData());
                if (strlen($data) > 0) {
                    $f007_0 = $data{0};
                }
                if (strlen($data) > 1) {
                    $f007_1 = $data{1};
                }
            }
            $f008 = $this->getMarcRecord()->getFields("008", false);
            foreach ($f008 as $field) {
                $data = strtoupper($field->getData());
                if (strlen($data) > 21) {
                    $f008_21 = $data{21};
                }
            }

            $leader = $this->getMarcRecord()->getLeader();
            $leader_6 = $leader{6};
            $leader_7 = $leader{7};

            $formats[] = $this->marc21007($f007_0, $f007_1);
            $formats[] = $this->marc21leader7($leader_7, $f007_0, $f008_21);
            if ($this->isCollection() && !$this->isArticle()) {
                $formats[] = 'Compilation';
            }

            $this->formats = array_filter($formats);
        }
        return $this->formats;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        // ToDo: remove duplex entries; with or without slash
        $isbn = array_merge(
            $this->getFieldArray('020', ['a', 'z', '9'], false), 
            $this->getFieldArray('773', ['z'])
        );
        foreach ($isbn as $key => $num) {
            $isbn[$key] = str_replace("-", "", $num);
        }
        $isbn = array_unique($isbn);
        return $isbn;
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $issn = array_merge(
            $this->getFieldArray('022', ['a']), $this->getFieldArray('029', ['a']), 
            $this->getFieldArray('440', ['x']), $this->getFieldArray('490', ['x']), 
            $this->getFieldArray('730', ['x']), $this->getFieldArray('773', ['x']), 
            $this->getFieldArray('776', ['x']), $this->getFieldArray('780', ['x']), 
            $this->getFieldArray('785', ['x'])
        );
        foreach ($issn as $key => $num) {
            $issn[$key] = str_replace("-", "", $num);
        }
        $issn = array_unique($issn);
        return $issn;
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        $languages = [];
        $fields = $this->getMarcRecord()->getFields('041');
        foreach ($fields as $field) {
            if (strcmp($field->getIndicator(2), '7') !== 0) {
                foreach ($field->getSubFields('a') as $sf) {
                    $languages[] = $this->translate($sf->getData());
                }
            }
        }
        return $languages;
    }

    /**
     * Get a LCCN, normalised according to info:lccn
     *
     * @return string
     */
    public function getLCCN()
    {
        //lccn = 010a, first
        return $this->getFirstFieldValue('010', ['a']);
    }

    /**
     * Get an array of all the MediaTypes associated with the record.
     *
     * @return array
     */
    public function getMediaTypes()
    {
        if ($this->formats === null) {
            $formats = [];
            $f007 = $f008 = $leader = null;
            $f007_0 = $f007_1 = $f008_21 = $leader_6 = $leader_7 = '';

            //field 007 - physical description
            $f007 = $this->getMarcRecord()->getFields("007", false);
            foreach ($f007 as $field) {
                $data = strtoupper($field->getData());
                if (strlen($data) > 0) {
                    $f007_0 = $data{0};
                }
                if (strlen($data) > 1) {
                    $f007_1 = $data{1};
                }
            }
            $f008 = $this->getMarcRecord()->getFields("008", false);
            foreach ($f008 as $field) {
                $data = strtoupper($field->getData());
                if (strlen($data) > 21) {
                    $f008_21 = $data{21};
                }
            }

            $leader = $this->getMarcRecord()->getLeader();
            $leader_6 = $leader{6};
            $leader_7 = $leader{7};

            $formats[] = $this->marc21007($f007_0, $f007_1);
            $formats[] = $this->marc21leader7($leader_7, $f007_0, $f008_21);
            if ($this->isCollection() && !$this->isArticle()) {
                $formats[] = 'Compilation';
            }

            $this->formats = array_filter($formats);
        }
        return $this->formats;
    }

    /**
     * Maps formats from formats.ini to icon file names
     *
     * @param string $formats the formats that are avialable
     *
     * @return string
     */
    protected function mapIcon($formats) 
    {

        //this function uses simplifies formats as we can only show one icon
        $formats = $this->simplify($formats);
        foreach ($formats as $k => $format) {
            $formats[$k] = strtolower($format);
        }
        $return = '';
        if (is_array($formats)) {
            if (in_array('electronicresource', $formats)  
                && in_array('e-book', $formats)
            ) {
                $return = 'ebook'; 
            } elseif (in_array('videodisc', $formats)  
                && in_array('video', $formats)
            ) {
                $return = 'movie';
            } elseif (in_array('electronicresource', $formats)  
                && in_array('journal', $formats)
            ) {
                $return = 'ejournal';
            } elseif (in_array('opticaldisc', $formats)  
                && in_array('e-book', $formats)
            ) {
                $return = 'disc';
            } elseif (in_array('cd', $formats)  
                && in_array('soundrecording', $formats)
            ) {
                $return = 'music-cd';
            } elseif (in_array('book', $formats)  
                && in_array('compilation', $formats)
            ) {
                $return = 'serial';
            } elseif (in_array('musicalscore', $formats)) {
                $return = 'partitur';
            } elseif (in_array('atlas', $formats)) {
                $return = 'map';
            } elseif (in_array('serial', $formats)) {
                $return = 'collection';
            } elseif (in_array('journal', $formats)) {
                $return = 'journal';
            } elseif (in_array('conference proceeding', $formats)) {
                $return = 'journal';
            } elseif (in_array('e-journal', $formats)) {
                $return = 'ejournal';
            } elseif (in_array('text', $formats)) {
                $return = 'article';
            } elseif (in_array('pdf', $formats)) {
                $return = 'article';
            } elseif (in_array('book', $formats)) {
                $return = 'book';
            } elseif (in_array('book chapter', $formats)) {
                $return = 'book';
            } elseif (in_array('e-book', $formats)) {
                $return = 'ebook';
            } elseif (in_array('e-book', $formats)) {
                $return = 'ebook';
            } elseif (in_array('ebook', $formats)) {
                $return = 'ebook';
            } elseif (in_array('vhs', $formats)) {
                $return = 'vhs';
            } elseif (in_array('video', $formats)) {
                $return = 'video-disc';
            } elseif (in_array('microfilm', $formats)) {
                $return = 'microfilm';
            } elseif (in_array('platter', $formats)) {
                $return = 'platter';
            } elseif (in_array('dvd/bluray', $formats)) {
                $return = 'video-disc';
            } elseif (in_array('music-cd', $formats)) {
                $return = 'music-disc';
            } elseif (in_array('cd-rom', $formats)) {
                $return = 'disc';
            } elseif (in_array('article', $formats)) {
                $return = 'article';
            } elseif (in_array('magazine article', $formats)) {
                $return = 'article';
            } elseif (in_array('journal article', $formats)) {
                $return = 'article';
            } elseif (in_array('band', $formats)) {
                $return = 'book';
            } elseif (in_array('cassette', $formats)) {
                $return = 'cassette';
            } elseif (in_array('soundrecording', $formats)) {
                $return = 'sound';
            } elseif (in_array('norm', $formats)) {
                $return = 'norm';
            } elseif (in_array('thesis', $formats)) {
                $return = 'thesis';
            } elseif (in_array('proceedings', $formats)) {
                $return = 'books';
            } elseif (in_array('electronic', $formats)) {
                $return = 'globe';
            } else {
                $return =  'article'; 
            }
        }


        return 'icon icon-'. $return;
    }


    /**
     * Get the OCLC number of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        $numbers = [];
        $pattern = '(OCoLC)';
        foreach ($this->getFieldArray('016') as $f) {
            if (!strncasecmp($pattern, $f, strlen($pattern))) {
                $numbers[] = substr($f, strlen($pattern));
            }
        }
        return $numbers;
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        return $this->getFieldArray('300', ['a', 'b', 'c', 'e', 'f', 'g'], true);
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return $this->getPublicationInfo('c');
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return trim($this->getFirstFieldValue('100', ['a']));
    }

    /**
     * Get the main authors of the record.
     *
     * @return string
     */
    public function getPrimaryAuthors()
    {
        return $this->getFieldArray('100', 'a', true);
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        // First check old-style 260 field:
        $results = $this->getFieldArray('260', ['b'], true);

        // Now track down relevant RDA-style 264 fields; 
        $pubResults = $this->getFieldArray('264', ['b'], true);

        $replace260 = isset($this->mainConfig->Record->replaceMarc260)
            ? $this->mainConfig->Record->replaceMarc260 : false;

        if (count($pubResults) > 0) {
            return $replace260 ? $pubResults : array_merge($results, $pubResults);
        }

        return $results;
    }

    /**
     * Get an array of all corporate authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getCorporateAuthors()
    {
        $other_author = array_merge(
            $this->getFieldArray('110', ['a', 'b']),
            $this->getFieldArray('111', ['a', 'b']),
            $this->getFieldArray('710', ['a', 'b']),
            $this->getFieldArray('711', ['a', 'b'])
        );
        return $other_author;
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $other_author = $this->getFieldArray('700', ['a', 'b', 'c', 'd']);
        return $other_author;
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        $shortTitle = $this->getFirstFieldValue('245', array('a'), false);

        // remove sorting char 
        if (strpos($shortTitle, '@') !== false) {
            $occurrence = strpos($shortTitle, '@');
            $shortTitle = substr_replace($shortTitle, '', $occurrence, 1);
        }

        return trim($shortTitle);
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        $subTitle = $this->getFirstFieldValue('245', array('b'), false);

        // remove sorting character 
        if (strpos($subTitle, '@') !== false) {
            $occurrence = strpos($subTitle, '@');
            $subTitle = substr_replace($subTitle, '', $occurrence, 1);
        }

        return trim($subTitle);
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = "";
        
        $stit = false;
        $subt = false;
        if (strlen($this->getShortTitle()) > 0) {
            $title .= $this->getShortTitle();
            $stit = true;
        }
        if (strlen($this->getSubtitle()) > 0) {
            if ($stit) { 
                $title .= ": ";
            }
            $title .= $this->getSubtitle();
            $subt = true;
        }
        if (strlen($this->getEdition()) > 0) {
            if ($stit || $subt) {
                $title .= " - ";
            }
            $title .= $this->getEdition(); 
        }
        return trim($title);
    }


    // maybe in a DefaultTrait

    /**
     * Get highlighted author data, if available.
     *
     * @return array
     */
    public function getRawAuthorHighlights()
    {
        // Don't check for highlighted values if highlighting is disabled:
        return ($this->highlight && isset($this->highlightDetails['author']))
            ? $this->highlightDetails['author'] : [];
    }

    /**
     * Get primary author information with highlights applied (if applicable)
     *
     * @return array
     */
    public function getPrimaryAuthorsWithHighlighting()
    {
        $highlights = [];
        // Create a map of de-highlighted valeus => highlighted values.
        foreach ($this->getRawAuthorHighlights() as $current) {
            $dehighlighted = str_replace(
                ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'], '', $current
            );
            $highlights[$dehighlighted] = $current;
        }

        // replace unhighlighted authors with highlighted versions where
        // applicable:
        $authors = [];
        foreach ($this->getPrimaryAuthors() as $author) {
            $authors[] = isset($highlights[$author])
                ? $highlights[$author] : $author;
        }
        return $authors;
    }

    /**
     * Returns content/format from Marc21 field 007
     *
     * @param char $leader7 Marc Leader
     * @param char $f007    Field 007
     * @param char $f008    Field 008
     *
     * @return string
     */
    protected function marc21leader7($leader7, $f007, $f008 ) 
    {
        $format = '';
        $leader7 = strtoupper($leader7);
        $f007 = strtoupper($f007);
        $mappings = [];
        $mappings['A']['default'] = 'Article'; 
        $mappings['B']['default'] = 'Article';
        $mappings['M']['C'] = 'E-Book';
        $mappings['M']['V'] = 'Video';
        $mappings['M']['S'] = 'SoundRecording';
        $mappings['M']['default'] = 'Book';
        $mappings['S']['N'] = 'Newspaper';
        $mappings['S']['P'] = 'Journal';
        $mappings['S']['M'] = 'Serial';
        $mappings['S']['default'] = 'Serial';

        if (isset($mappings[$leader7])) {
            if ($leader7 == 'S' && isset($mappings[$leader7][$f008])) {
                $format = $mappings[$leader7][$f008];
            } elseif ($leader7 != 'S' && isset($mappings[$leader7][$f007])) {
                $format = $mappings[$leader7][$f007];
            } elseif (isset($mappings[$leader7]['default'])) {
                $format = $mappings[$leader7]['default'];
            }
        }
        return $format;
    }

    /**
     * Returns physical medium from Marc21 field 007 - char 0 and 1
     *
     * @param char $code1 char 0 
     * @param char $code2 char 1
     *
     * @return string
     */
    protected function marc21007($code1, $code2) 
    {
        $medium = '';
        $code1 = strtoupper($code1);
        $code2 = strtoupper($code2);
        $mappings = [];
        $mappings['A']['D'] = 'Atlas';
        $mappings['A']['default'] = 'Map';
        $mappings['C']['A'] = 'TapeCartridge';
        $mappings['C']['B'] = 'ChipCartridge';
        $mappings['C']['C'] = 'DiscCartridge';
        $mappings['C']['F'] = 'TapeCassette';
        $mappings['C']['H'] = 'TapeReel';
        $mappings['C']['J'] = 'FloppyDisk';
        $mappings['C']['M'] = 'MagnetoOpticalDisc';
        $mappings['C']['Z'] = 'E-Journal on Disc';
        $mappings['C']['O'] = 'OpticalDisc';
        // Do not return - this will cause anything with an
        // 856 field to be labeled as "Electronic"
        $mappings['C']['R'] = 'E-Journal';
        $mappings['C']['default'] = 'ElectronicResource'; 
        $mappings['D']['default'] = 'Globe';
        $mappings['F']['default'] = 'Braille';
        $mappings['G']['C'] = 'FilmstripCartridge';
        $mappings['G']['D'] = 'Filmstrip';
        $mappings['G']['S'] = 'Slide';
        $mappings['G']['T'] = 'Transparency';
        $mappings['G']['default'] = 'Slide';
        $mappings['H']['default'] = 'Microfilm';
        $mappings['K']['C'] = 'Collage';
        $mappings['K']['D'] = 'Drawing';
        $mappings['K']['E'] = 'Painting';
        $mappings['K']['F'] = 'Print';
        $mappings['K']['G'] = 'Photonegative';
        $mappings['K']['J'] = 'Print';
        $mappings['K']['L'] = 'Drawing';
        $mappings['K']['O'] = 'FlashCard';
        $mappings['K']['N'] = 'Chart';
        $mappings['K']['default'] = 'Photo';
        $mappings['M']['F'] = 'VideoCassette';
        $mappings['M']['R'] = 'Filmstrip';
        $mappings['M']['default'] = 'MotionPicture';
        $mappings['O']['default'] = 'Kit';
        $mappings['Q']['U'] = 'SheetMusic';
        $mappings['Q']['default'] = 'MusicalScore';
        $mappings['R']['default'] = 'SensorImage';
        $mappings['S']['D'] = 'CD';
        $mappings['S']['O'] = 'SoundRecording'; // SO ist not specified
        $mappings['S']['S'] = 'SoundCassette';
        $mappings['S']['Z'] = 'Platter'; //Undefined         
        $mappings['S']['default'] = 'SoundRecording'; // unspecified
        $mappings['T']['A'] = 'Printed'; //Text               
        $mappings['T']['D'] = 'LooseLeaf'; //Text               
        $mappings['T']['default'] = null; //Text               
        $mappings['V']['C'] = 'VideoCartridge';
        $mappings['V']['D'] = 'VideoDisc';
        $mappings['V']['F'] = 'VideoCassette';
        $mappings['V']['R'] = 'VideoReel';
        $mappings['V']['default'] = 'Video';
        $mappings['Z']['default'] = 'Kit';


        if (isset($mappings[$code1])) {
            if (!empty($mappings[$code1][$code2])) {
                $medium = $mappings[$code1][$code2];
            } elseif (!empty($mappings[$code1]['default'])) {
                $medium = $mappings[$code1]['default'];
            }
        }
        return $medium;
    }

    /**
     * Simplify format array
     *
     * @param array $formats that are available
     *
     * @return array
     */
    protected function simplify($formats) 
    {
        $formats = array_unique($formats);
        foreach ($formats as$k => $format) {
            if (!empty($format)) {
                $formats[$k] = ucfirst($format);
            }
        }
        if (in_array('SoundRecording', $formats)  
            && in_array('MusicRecording', $formats)
        ) {
            return ['Musik']; 
        } elseif (in_array('SheetMusic', $formats)  
            && in_array('Book', $formats)
        ) {
            return ['MusicalScore']; 
        } elseif (in_array('Map', $formats)  
            && in_array('Book', $formats)
        ) {
            return ['Atlas']; 
        } elseif (in_array('Platter', $formats)  
            && in_array('SoundRecording', $formats)
        ) {
            return ['Platter']; 
        } elseif (in_array('E-Journal', $formats)  
            && in_array('E-Book', $formats)
        ) {
            return ['E-Book']; 
        } elseif (in_array('E-Journal on Disc', $formats)  
            && in_array('Journal', $formats)
        ) {
            return ['E-Journal']; 
        } elseif (in_array('VideoDisc', $formats)  
            && in_array('Video', $formats)
        ) {
            return ['DVD/BluRay']; 
        } elseif (in_array('CD', $formats)  
            && in_array('SoundRecording', $formats)
        ) {
            return ['Music-CD']; 
        } elseif (in_array('OpticalDisc', $formats)  
            && in_array('E-Book', $formats)
        ) {
            return ['CD-ROM']; 
        } elseif (in_array('E-Journal', $formats)  
            && in_array('Journal', $formats)
        ) {
            return ['E-Journal']; 
        } elseif (in_array('Journal', $formats)  
            && in_array('Printed', $formats)
        ) {
            return ['E-Journal']; 
        } elseif (in_array('VideoCassette', $formats)  
            && in_array('Video', $formats)
        ) {
            return ['VHS']; 
        } elseif (in_array('Microfilm', $formats)  
            && in_array('Book', $formats)
        ) {
            
            return ['Book']; 
        } elseif (in_array('Microfilm', $formats)  
            && in_array('Journal', $formats)
        ) {
            return ['Journal']; 
        } elseif (in_array('SoundCassette', $formats)  
            && in_array('SoundRecording', $formats)
        ) {
            return ['Cassette']; 
        } elseif (in_array('SoundRecording', $formats)   
            && in_array('Article', $formats)
        ) {
            return ['Music-CD']; 
        } 

        return $formats;

    }


}
