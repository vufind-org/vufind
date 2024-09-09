<?php

/**
 * Unit tests for Explanation.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @package  Search
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Solr;

use VuFind\Config\PluginManager;
use VuFind\Search\Solr\Explanation;
use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFindSearch\Backend\Exception\BackendException;

/**
 * Unit tests for Explanation.
 *
 * @category VuFind
 * @package  Search
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExplanationTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Solr 9 example response
     *
     * @var string
     */
    protected $testResponse =
        '200575.50390625 = weight(FunctionScoreQuery(+(fulltext:harri | (allfields_unstemmed:harry)^10.0 | '
        . '(author:harry)^300.0 | (title_alt:harri)^200.0 | (title_full:harri)^400.0 | (contents:harri)^10.0 | '
        . '(title:harri)^500.0 | long_lat_display:harri | (geographic:harri)^300.0 | (series2:harri)^30.0 | '
        . '(title_short:harri)^750.0 | (title_full_unstemmed:harry)^600.0 | (topic:harri)^500.0 | '
        . '(title_new:harri)^100.0 | description:harri | (genre:harri)^300.0 | (fulltext_unstemmed:harry)^10.0 | '
        . '(series:harri)^50.0 | (ConstantScore(allfields:harri))^0.0 | '
        . '(topic_unstemmed:harry)^550.0)~0.4 +((topic_unstemmed:potter)^550.0 | (title:potter)^500.0 | '
        . '(title_short:potter)^750.0 | (title_new:potter)^100.0 | (ConstantScore(allfields:potter))^0.0 | '
        . '(author:potter)^300.0 | fulltext:potter | long_lat_display:potter | (series:potter)^50.0 | '
        . '(allfields_unstemmed:potter)^10.0 | (topic:potter)^500.0 | (contents:potter)^10.0 | '
        . '(title_alt:potter)^200.0 | (fulltext_unstemmed:potter)^10.0 | (geographic:potter)^300.0 | '
        . 'description:potter | (genre:potter)^300.0 | (title_full_unstemmed:potter)^600.0 | (title_full:potter)^400.0 '
        . '| (series2:potter)^30.0)~0.4, scored by boost(const(19)))), result of:'
        . <<<EXPLANATION
              200575.50390625 = product of:
                10556.605 = sum of:
                  4893.0547 = max plus 0.4 times others of:
                    31.96489 = weight(allfields_unstemmed:harry in 43415) [SchemaSimilarity], result of:
                      31.96489 = score(freq=1.0), computed as boost * idf * tf from:
                        10.0 = boost
                        7.714137 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          298 = n, number of documents containing term
                          668576 = N, total number of documents with field
                        0.41436762 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          128.0 = dl, length of field (approximate)
                          103.474686 = avgdl, average length of field
                    1107.874 = weight(title_full:harri in 43415) [SchemaSimilarity], result of:
                      1107.874 = score(freq=1.0), computed as boost * idf * tf from:
                        400.0 = boost
                        7.6367993 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          322 = n, number of documents containing term
                          668573 = N, total number of documents with field
                        0.36267614 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          20.0 = dl, length of field
                          12.351774 = avgdl, average length of field
                    1659.002 = weight(title:harri in 43415) [SchemaSimilarity], result of:
                      1659.002 = score(freq=1.0), computed as boost * idf * tf from:
                        500.0 = boost
                        10.174222 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          25 = n, number of documents containing term
                          668572 = N, total number of documents with field
                        0.3261187 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          16.0 = dl, length of field
                          8.15232 = avgdl, average length of field
                    3084.0 = weight(title_short:harri in 43415) [SchemaSimilarity], result of:
                      3084.0 = score(freq=1.0), computed as boost * idf * tf from:
                        750.0 = boost
                        10.495121 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          18 = n, number of documents containing term
                          668566 = N, total number of documents with field
                        0.39180106 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          7.0 = dl, length of field
                          5.0306807 = avgdl, average length of field
                    1723.7961 = weight(title_full_unstemmed:harry in 43415) [SchemaSimilarity], result of:
                      1723.7961 = score(freq=1.0), computed as boost * idf * tf from:
                        600.0 = boost
                        7.9219007 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          242 = n, number of documents containing term
                          668573 = N, total number of documents with field
                        0.36266464 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          20.0 = dl, length of field
                          12.351032 = avgdl, average length of field
                    0.0 = ConstantScore(allfields:harri)^0.0
                  5663.5503 = max plus 0.4 times others of:
                    1775.2554 = weight(title:potter in 43415) [SchemaSimilarity], result of:
                      1775.2554 = score(freq=1.0), computed as boost * idf * tf from:
                        500.0 = boost
                        10.887173 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          12 = n, number of documents containing term
                          668572 = N, total number of documents with field
                        0.3261187 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          16.0 = dl, length of field
                          8.15232 = avgdl, average length of field
                    3349.3086 = weight(title_short:potter in 43415) [SchemaSimilarity], result of:
                      3349.3086 = score(freq=1.0), computed as boost * idf * tf from:
                        750.0 = boost
                        11.397989 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          7 = n, number of documents containing term
                          668566 = N, total number of documents with field
                        0.39180106 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          7.0 = dl, length of field
                          5.0306807 = avgdl, average length of field
                    0.0 = ConstantScore(allfields:potter)^0.0
                    42.497154 = weight(allfields_unstemmed:potter in 43415) [SchemaSimilarity], result of:
                      42.497154 = score(freq=1.0), computed as boost * idf * tf from:
                        10.0 = boost
                        10.255906 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          23 = n, number of documents containing term
                          668576 = N, total number of documents with field
                        0.41436762 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          128.0 = dl, length of field (approximate)
                          103.474686 = avgdl, average length of field
                    2452.9556 = weight(title_full_unstemmed:potter in 43415) [SchemaSimilarity], result of:
                      2452.9556 = score(freq=1.0), computed as boost * idf * tf from:
                        600.0 = boost
                        11.272836 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          8 = n, number of documents containing term
                          668573 = N, total number of documents with field
                        0.36266464 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          20.0 = dl, length of field
                          12.351032 = avgdl, average length of field
                    1514.8965 = weight(title_full:potter in 43415) [SchemaSimilarity], result of:
                      1514.8965 = score(freq=1.0), computed as boost * idf * tf from:
                        400.0 = boost
                        10.442488 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                          19 = n, number of documents containing term
                          668573 = N, total number of documents with field
                        0.36267614 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                          1.0 = freq, occurrences of term within document
                          1.2 = k1, term saturation parameter
                          0.75 = b, length normalization parameter
                          20.0 = dl, length of field
                          12.351774 = avgdl, average length of field
                19.0 = const(19)
            EXPLANATION;

    /**
     * Second Solr 9 example response
     *
     * @var string
     */
    protected $testResponse2 = <<<EXPLANATION
        3794.7397 = max of:
          3098.3008 = weight(title:darwin in 62810) [SchemaSimilarity], result of:
            3098.3008 = score(freq=1.0), computed as boost * idf * tf from:
            500.0 = boost
              12.160138 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                3 = n, number of documents containing term
                668572 = N, total number of documents with field
              0.5095832 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                1.0 = freq, occurrences of term within document
                1.2 = k1, term saturation parameter
                0.75 = b, length normalization parameter
                6.0 = dl, length of field
                8.15232 = avgdl, average length of field
          3.003481 = weight(allfields:darwin in 62810) [SchemaSimilarity], result of:
            3.003481 = score(freq=1.0), computed as boost * idf * tf from:
              9.12932 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                72 = n, number of documents containing term
                668576 = N, total number of documents with field
              0.32899284 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                1.0 = freq, occurrences of term within document
                1.2 = k1, term saturation parameter
                0.75 = b, length normalization parameter
                200.0 = dl, length of field (approximate)
                103.473274 = avgdl, average length of field
          30.270142 = weight(allfields_unstemmed:darwin in 62810) [SchemaSimilarity], result of:
            30.270142 = score(freq=1.0), computed as boost * idf * tf from:
              10.0 = boost
              9.200779 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                67 = n, number of documents containing term
                668576 = N, total number of documents with field
              0.3289954 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                1.0 = freq, occurrences of term within document
                1.2 = k1, term saturation parameter
                0.75 = b, length normalization parameter
                200.0 = dl, length of field (approximate)
                103.474686 = avgdl, average length of field
          2529.8735 = weight(title_full:darwin in 62810) [SchemaSimilarity], result of:
            2529.8735 = score(freq=1.0), computed as boost * idf * tf from:
              400.0 = boost
              11.908825 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                4 = n, number of documents containing term
                668573 = N, total number of documents with field
              0.53109217 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                1.0 = freq, occurrences of term within document
                1.2 = k1, term saturation parameter
                0.75 = b, length normalization parameter
                8.0 = dl, length of field
                12.351774 = avgdl, average length of field
          3794.7397 = weight(title_full_unstemmed:darwin in 62810) [SchemaSimilarity], result of:
            3794.7397 = score(freq=1.0), computed as boost * idf * tf from:
              600.0 = boost
              11.908825 = idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from:
                4 = n, number of documents containing term
                668573 = N, total number of documents with field
              0.53108233 = tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from:
                1.0 = freq, occurrences of term within document
                1.2 = k1, term saturation parameter
                0.75 = b, length normalization parameter
                8.0 = dl, length of field
                12.351032 = avgdl, average length of field
        EXPLANATION;

    /**
     * Third Solr 9 example response
     *
     * @var string
     */
    protected $testResponse3 =
        '0.598864 = (MATCH) boost(+(((title_lc_word:charls^4.0 | rvk_full:charls^0.5 | kls_lc_word_3:charls^0.5 | '
        . '(other:tSarls other:xarls) | test_title_stemm:charl | series_statement:charls^0.01 | '
        . '(author:tSarls author:xarls) | (retroocr:tSarls retroocr:xarls) | author_lc_word:charls^5.0 | '
        . '(test_title_sound:tSarls test_title_sound:xarls) | topic_lc_word_3:charls^0.6 | kls_3:charls^0.5 | '
        . 'shelfmark_word_3:charls | isxn:charls | retroocr_lc_word:charls | ((topic:tSarls topic:xarls)^0.6) | '
        . 'id:charls | ((topic_3:tSarls topic_3:xarls)^0.5) | other_lc_word:charls^2.5 | topic_lc_word:charls^0.6 | '
        . 'misc:charls^0.01)~0.1 (title_lc_word:darwin^4.0 | rvk_full:darwin^0.5 | kls_lc_word_3:darwin^0.5 | '
        . '(other:darvin other:darwin) | test_title_stemm:darwin | series_statement:darwin^0.01 | '
        . '(author:darvin author:darwin) | (retroocr:darvin retroocr:darwin) | author_lc_word:darwin^5.0 | '
        . '(test_title_sound:darvin test_title_sound:darwin) | topic_lc_word_3:darwin^0.6 | kls_3:darwin^0.5 | '
        . 'shelfmark_word_3:darwin | isxn:darwin | retroocr_lc_word:darwin | ((topic:darvin topic:darwin)^0.6) | '
        . 'id:darwin | ((topic_3:darvin topic_3:darwin)^0.5) | other_lc_word:darwin^2.5 | topic_lc_word:darwin^0.6 | '
        . 'misc:darwin^0.01)~0.1 (title_lc_word:evolution^4.0 | rvk_full:evolution^0.5 | kls_lc_word_3:evolution^0.5 | '
        . 'other:evolutjon | test_title_stemm:evolution | series_statement:evolution^0.01 | author:evolutjon | '
        . 'retroocr:evolutjon | author_lc_word:evolution^5.0 | test_title_sound:evolutjon | '
        . 'topic_lc_word_3:evolution^0.6 | kls_3:evolution^0.5 | shelfmark_word_3:evolution | isxn:evolution | '
        . 'retroocr_lc_word:evolution | topic:evolutjon^0.6 | id:evolution | topic_3:evolutjon^0.5 | '
        . 'other_lc_word:evolution^2.5 | topic_lc_word:evolution^0.6 | '
        . 'misc:evolution^0.01)~0.1 (title_lc_word:theroy^4.0 | rvk_full:theroy^0.5 | kls_lc_word_3:theroy^0.5 | '
        . 'other:teroj | test_title_stemm:theroy | series_statement:theroy^0.01 | author:teroj | retroocr:teroj | '
        . 'author_lc_word:theroy^5.0 | test_title_sound:teroj | topic_lc_word_3:theroy^0.6 | kls_3:theroy^0.5 | '
        . 'shelfmark_word_3:theroy | isxn:theroy | retroocr_lc_word:theroy | topic:teroj^0.6 | id:theroy | '
        . 'topic_3:teroj^0.5 | other_lc_word:theroy^2.5 | topic_lc_word:theroy^0.6 | '
        . 'misc:theroy^0.01)~0.1 (title_lc_word:species^4.0 | rvk_full:species^0.5 | kls_lc_word_3:species^0.5 | '
        . '(other:Spetses other:spetses) | test_title_stemm:speci | series_statement:species^0.01 | '
        . '(author:Spetses author:spetses) | (retroocr:Spetses retroocr:spetses) | author_lc_word:species^5.0 | '
        . '(test_title_sound:Spetses test_title_sound:spetses) | topic_lc_word_3:species^0.6 | kls_3:species^0.5 | '
        . 'shelfmark_word_3:species | isxn:species | retroocr_lc_word:species | ((topic:Spetses topic:spetses)^0.6) | '
        . 'id:species | ((topic_3:Spetses topic_3:spetses)^0.5) | other_lc_word:species^2.5 | '
        . 'topic_lc_word:species^0.6 | '
        . 'misc:species^0.01)~0.1)~4) (title_lc_word:"charls darwin evolution theroy species"~3^3.0 | '
        . 'kls_lc_word_3:"charls darwin evolution theroy species"~3)~0.1,product(if(exists(query(id:HEBr*,def=0.0)),'
        . 'const(0.4),const(1)),sum(product(max(const(0),sum(product(abs(ms(const(1672531200000),date(pub_date_max))),'
        . 'const(-5.285E-13)),const(1))),const(6.5)),const(1)))), product of:'
        . <<<EXPLANATION
                  0.598864 = (MATCH) sum of:
                    0.598864 = (MATCH) product of:
                      0.74858004 = (MATCH) sum of:
                        0.07653126 = (MATCH) max plus 0.1 times others of:
                          0.07653126 = (MATCH) sum of:
                            0.04210584 = (MATCH) weight(author:tSarls in 115379) [DefaultSimilarity], result of:
                              0.04210584 = score(doc=115379,freq=3.0), product of:
                                0.040880267 = queryWeight, product of:
                                  9.5145445 = idf(docFreq=4085, maxDocs=20375968)
                                  0.004296608 = queryNorm
                                1.0299796 = fieldWeight in 115379, product of:
                                  1.7320508 = tf(freq=3.0), with freq of:
                                    3.0 = termFreq=3.0
                                  9.5145445 = idf(docFreq=4085, maxDocs=20375968)
                                  0.0625 = fieldNorm(doc=115379)
                            0.034425426 = (MATCH) weight(author:xarls in 115379) [DefaultSimilarity], result of:
                              0.034425426 = score(doc=115379,freq=2.0), product of:
                                0.040907696 = queryWeight, product of:
                                  9.520928 = idf(docFreq=4059, maxDocs=20375968)
                                  0.004296608 = queryNorm
                                0.8415391 = fieldWeight in 115379, product of:
                                  1.4142135 = tf(freq=2.0), with freq of:
                                    2.0 = termFreq=2.0
                                  9.520928 = idf(docFreq=4059, maxDocs=20375968)
                                  0.0625 = fieldNorm(doc=115379)
                        0.6704539 = (MATCH) max plus 0.1 times others of:
            EXPLANATION
        . '              0.0017296325 = (MATCH) weight(series_statement:darwin^0.01 in 115379) [DefaultSimilarity], '
        . 'result of:'
        . <<<EXPLANATION
                        0.0017296325 = score(doc=115379,freq=1.0), product of:
                          4.8765732E-4 = queryWeight, product of:
                            0.01 = boost
                            11.349822 = idf(docFreq=651, maxDocs=20375968)
                            0.004296608 = queryNorm
                          3.5468194 = fieldWeight in 115379, product of:
                            1.0 = tf(freq=1.0), with freq of:
                              1.0 = termFreq=1.0
                            11.349822 = idf(docFreq=651, maxDocs=20375968)
                            0.3125 = fieldNorm(doc=115379)
                      0.24371147 = (MATCH) sum of:
                        0.14047433 = (MATCH) weight(author:darvin in 115379) [DefaultSimilarity], result of:
                          0.14047433 = score(doc=115379,freq=26.0), product of:
                            0.04351891 = queryWeight, product of:
                              10.128667 = idf(docFreq=2210, maxDocs=20375968)
                              0.004296608 = queryNorm
                            3.227892 = fieldWeight in 115379, product of:
                              5.0990195 = tf(freq=26.0), with freq of:
                                26.0 = termFreq=26.0
                              10.128667 = idf(docFreq=2210, maxDocs=20375968)
                              0.0625 = fieldNorm(doc=115379)
                        0.10323714 = (MATCH) weight(author:darwin in 115379) [DefaultSimilarity], result of:
                          0.10323714 = score(doc=115379,freq=14.0), product of:
                            0.04355207 = queryWeight, product of:
                              10.136385 = idf(docFreq=2193, maxDocs=20375968)
                              0.004296608 = queryNorm
                            2.37043 = fieldWeight in 115379, product of:
                              3.7416575 = tf(freq=14.0), with freq of:
                                14.0 = termFreq=14.0
                              10.136385 = idf(docFreq=2193, maxDocs=20375968)
                              0.0625 = fieldNorm(doc=115379)
                      0.64587224 = (MATCH) weight(author_lc_word:darwin^5.0 in 115379) [DefaultSimilarity], result of:
                        0.64587224 = score(doc=115379,freq=14.0), product of:
                          0.21786834 = queryWeight, product of:
                            5.0 = boost
                            10.141412 = idf(docFreq=2182, maxDocs=20375968)
                            0.004296608 = queryNorm
                          2.964507 = fieldWeight in 115379, product of:
                            3.7416575 = tf(freq=14.0), with freq of:
                              14.0 = termFreq=14.0
                            10.141412 = idf(docFreq=2182, maxDocs=20375968)
                            0.078125 = fieldNorm(doc=115379)
                      3.753989E-4 = (MATCH) weight(misc:darwin^0.01 in 115379) [DefaultSimilarity], result of:
                        3.753989E-4 = score(doc=115379,freq=2.0), product of:
                          3.8208225E-4 = queryWeight, product of:
                            0.01 = boost
                            8.892649 = idf(docFreq=7609, maxDocs=20375968)
                            0.004296608 = queryNorm
                          0.9825081 = fieldWeight in 115379, product of:
                            1.4142135 = tf(freq=2.0), with freq of:
                              2.0 = termFreq=2.0
                            8.892649 = idf(docFreq=7609, maxDocs=20375968)
                            0.078125 = fieldNorm(doc=115379)
                    0.00135112 = (MATCH) max plus 0.1 times others of:
            EXPLANATION
        . '          0.00135112 = (MATCH) weight(series_statement:evolution^0.01 in 115379) [DefaultSimilarity], '
        . 'result of:'
        . <<<EXPLANATION
                        0.00135112 = score(doc=115379,freq=1.0), product of:
                          4.3100747E-4 = queryWeight, product of:
                            0.01 = boost
                            10.031343 = idf(docFreq=2436, maxDocs=20375968)
                            0.004296608 = queryNorm
                          3.1347947 = fieldWeight in 115379, product of:
                            1.0 = tf(freq=1.0), with freq of:
                              1.0 = termFreq=1.0
                            10.031343 = idf(docFreq=2436, maxDocs=20375968)
                            0.3125 = fieldNorm(doc=115379)
                    2.4372259E-4 = (MATCH) max plus 0.1 times others of:
                      2.4372259E-4 = (MATCH) weight(misc:species^0.01 in 115379) [DefaultSimilarity], result of:
                        2.4372259E-4 = score(doc=115379,freq=2.0), product of:
                          3.0786352E-4 = queryWeight, product of:
                            0.01 = boost
                            7.16527 = idf(docFreq=42812, maxDocs=20375968)
                            0.004296608 = queryNorm
                          0.7916579 = fieldWeight in 115379, product of:
                            1.4142135 = tf(freq=2.0), with freq of:
                              2.0 = termFreq=2.0
                            7.16527 = idf(docFreq=42812, maxDocs=20375968)
                            0.078125 = fieldNorm(doc=115379)
                  0.8 = coord(4/5)
            EXPLANATION
        . '  1.0 = product(if(exists(query(id:HEBr*,def=0.0)=0.0),const(0.4),const(1)),sum(product(max(const(0),'
        . 'sum(product(abs(ms(const(1672531200000),date(pub_date_max)=1862-01-01T00:00:00Z)),const(-5.285E-13)),'
        . 'const(1))),const(6.5)),const(1)))';

    /**
     * Test basic Explanation attributes
     *
     * @return void
     */
    public function testExplainParams()
    {
        $recordId = 424332884;
        $response = [
            'maxScore' => 10,
        ];
        $debug = [
            'rawquerystring' => 'harry potter',
            'explainOther' => [
                $recordId => $this->testResponse,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ]
        );

        $explanation->performRequest($recordId);

        $this->assertEquals('harry potter', $explanation->getLookfor());
        $this->assertEquals(424332884, $explanation->getRecordId());

        $this->assertEquals(10, $explanation->getMaxScore());
        $this->assertEquals(200575.50390625, $explanation->getTotalScore());
        $this->assertEquals(200575.50390625, $explanation->getBaseScore());
        $this->assertEquals(null, $explanation->getBoost());
        $this->assertEquals(null, $explanation->getCoord());
        $this->assertEquals(-1, $explanation->getMaxFields());
        $this->assertEquals(0, $explanation->getMinPercentage());
        $this->assertEquals(2, $explanation->getDecimalPlaces());
    }

    /**
     * Test the explanation array
     *
     * @return void
     */
    public function testExplanationArray()
    {
        $recordId = 436708868;
        $response = [
            'maxScore' => 10000,
        ];
        $debug = [
            'rawquerystring' => 'harry potter',
            'explainOther' => [
                $recordId => $this->testResponse,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ]
        );
        $explanation->performRequest($recordId);
        $this->assertCount(12, $explanation->getExplanation());
        $explanationElement = $explanation->getExplanation()[0];
        $this->assertEquals('3349.3086', $explanationElement['value']);
        $this->assertEquals('1.6698492760937964', $explanationElement['percent']);
        $this->assertEquals(['title_short'], $explanationElement['fieldName']);
        $this->assertEquals(['potter'], $explanationElement['fieldValue']);
        $this->assertEquals(['exact'], $explanationElement['exactMatch']);
        $this->assertEquals('750.0', $explanationElement['fieldModifier']);

        $explanationElement = $explanation->getExplanation()[1];
        $this->assertEquals('3084.0', $explanationElement['value']);
        $this->assertEquals('1.5375755961911866', $explanationElement['percent']);
        $this->assertEquals(['title_short'], $explanationElement['fieldName']);
        $this->assertEquals(['harri'], $explanationElement['fieldValue']);
        $this->assertEquals(['inexact'], $explanationElement['exactMatch']);
        $this->assertEquals('750.0', $explanationElement['fieldModifier']);
    }

    /**
     * Test rest length with minPercent
     *
     * @return void
     */
    public function testMinPercent()
    {
        $minPercent = 1;
        $recordId = 436708868;
        $response = [
            'maxScore' => 58008200575.5,
        ];
        $debug = [
            'rawquerystring' => 'harry potter',
            'explainOther' => [
                $recordId => $this->testResponse,
            ],
        ];
        $config = [
            'Explain' => [
                'minPercent' => $minPercent,
                'maxFields' => -1,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ],
            $config
        );

        $explanation->performRequest($recordId);

        $this->assertEquals($minPercent, $explanation->getMinPercentage());
        $this->assertCount(2, $explanation->getExplanation());
        $this->assertCount(10, $explanation->getExplanationForRest());
    }

    /**
     * Test rest length with maxFields
     *
     * @return void
     */
    public function testMaxFields()
    {
        $recordId = 436708868;
        $explanationText = $this->testResponse;
        $response = [
            'maxScore' => 200575.5,
        ];
        $debug = [
            'rawquerystring' => 'harry potter',
            'explainOther' => [
                $recordId => $explanationText,
            ],
        ];
        $config = [
            'Explain' => [
                'maxFields' => 3,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ],
            $config
        );

        $explanation->performRequest($recordId);

        $this->assertEquals(3, $explanation->getMaxFields());
        $this->assertCount(9, $explanation->getExplanationForRest());
        $this->assertCount(3, $explanation->getExplanation());
    }

    /**
     * Test escaping brackets
     *
     * @return void
     */
    public function testEscapingBrackets()
    {
        $recordId = '(De-565)ada436708868';
        $explanationText = $this->testResponse;
        $response = [
            'maxScore' => 10000,
        ];
        $debug = [
            'rawquerystring' => 'harry potter',
            'explainOther' => [
                $recordId => $explanationText,
            ],
        ];
        $config = [
            'Explain' => [
                'maxFields' => -1,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ],
            $config
        );
        $explanation->performRequest($recordId);
        $this->assertEquals($recordId, $explanation->getRecordId());
        $this->assertCount(12, $explanation->getExplanation());
    }

    /**
     * Test without boost
     *
     * @return void
     */
    public function testNoBoost()
    {
        $recordId = 1;
        $explanationText = $this->testResponse2;
        $response = [
            'maxScore' => 10000,
        ];
        $debug = [
            'rawquerystring' => 'darwin',
            'explainOther' => [
                $recordId => $explanationText,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ]
        );
        $explanation->performRequest($recordId);
        $this->assertEquals($recordId, $explanation->getRecordId());
        $this->assertEquals(3794.7397, $explanation->getTotalScore());
        $this->assertCount(5, $explanation->getExplanation());
    }

    /**
     * Test boost
     *
     * @return void
     */
    public function testBoost()
    {
        $recordId = 436708868;
        $responseHeader = [
            'params' => [
                'boost' => 19,
            ],
        ];
        $response = [
            'maxScore' => 58008200575.5,
        ];
        $debug = [
            'rawquerystring' => 'harry potter',
            'explainOther' => [
                $recordId => $this->testResponse,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'responseHeader' => $responseHeader,
                'response' => $response,
                'debug' => $debug,
            ]
        );

        $explanation->performRequest($recordId);

        $this->assertEquals(['value' => 19.0, 'description' => ' const(19)'], $explanation->getBoost());
    }

    /**
     * Test coord
     *
     * @return void
     */
    public function testCoord()
    {
        $recordId = 1;
        $explanationText = $this->testResponse3;
        $response = [
            'maxScore' => 10000,
        ];
        $debug = [
            'rawquerystring' => 'darwin',
            'explainOther' => [
                $recordId => $explanationText,
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ]
        );

        $explanation->performRequest($recordId);

        $this->assertEquals($recordId, $explanation->getRecordId());
        $this->assertEquals(0.598864, $explanation->getTotalScore());
    }

    /**
     * Test when explainOther ins empty (recordId is not in Solr index)
     *
     * @return void
     */
    public function testNoExplainOther()
    {
        $recordId = 1;
        $response = [
            'maxScore' => 10000,
        ];
        $debug = [
            'otherQuery' => 1,
            'rawquerystring' => 'darwin',
            'explainOther' => [
                $recordId => '',
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ]
        );

        $this->expectException(BackendException::class);

        $explanation->performRequest($recordId);
    }

    /**
     * Test when explainOther has no matching clause
     *
     * @return void
     */
    public function testNoMatchingClause()
    {
        $recordId = 1;
        $response = [
            'maxScore' => 10000,
        ];
        $debug = [
            'rawquerystring' => 'darwin',
            'explainOther' => [
                $recordId => '0.0 = No matching clause',
            ],
        ];
        $explanation = $this->getExplanation(
            [
                'response' => $response,
                'debug' => $debug,
            ]
        );

        $this->expectException(BackendException::class);

        $explanation->performRequest($recordId);
    }

    /**
     * Creates an Explanation object with all the required mocks.
     *
     * @param array $result Result of the Solr request
     * @param array $config Optional searches.ini configs
     *
     * @return Explanation
     */
    protected function getExplanation($result, $config = null)
    {
        $mockConfig = $this->createMock(PluginManager::class);
        $paramsObj = new Params(
            new Options($mockConfig),
            $mockConfig
        );
        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $commandObj = $this->getMockBuilder(\VuFindSearch\Command\AbstractBase::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMockForAbstractClass();
        $commandObj->expects($this->once())->method('getResult')
            ->will($this->returnValue($result));
        $checkCommand = function ($command) {
            return $command::class === \VuFindSearch\Backend\Solr\Command\RawJsonSearchCommand::class;
        };
        $searchService->expects($this->once())->method('invoke')
            ->with($this->callback($checkCommand))
            ->will($this->returnValue($commandObj));
        return new Explanation(
            $paramsObj,
            $searchService,
            $this->getMockConfigPluginManager(
                [
                    'searches' => $config ?? [
                        'Explain' => [
                            'maxFields' => -1,
                        ],
                    ],
                ]
            )
        );
    }
}
