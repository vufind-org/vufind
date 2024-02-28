package org.vufind.index;
/**
 * Indexing routines using the UpdateDateTracker.
 *
 * Copyright (C) Villanova University 2017.
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
 */

import java.time.format.DateTimeFormatter;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.ZoneOffset;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.tools.SolrMarcIndexerException;
import org.marc4j.marc.Record;
import org.apache.log4j.Logger;

/**
 * Indexing routines using the UpdateDateTracker.
 */
public class UpdateDateTools
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(UpdateDateTools.class.getName());

    // the SimpleDateFormat class is not Thread-safe the below line were changes to be not static
    // which given the rest of the design of SolrMarc will make them work correctly.
    private DateTimeFormatter marc005date = DateTimeFormatter.ofPattern("yyyyMMddHHmmss.S");
    private DateTimeFormatter marc008date = DateTimeFormatter.ofPattern("yyMMdd");

    /**
     * Support method for getLatestTransaction.
     * @return Date extracted from 005 (or very old date, if unavailable)
     */
    private LocalDateTime normalize005Date(String input)
    {
        // Normalize "null" strings to a generic bad value:
        if (input == null) {
            input = "null";
        }

        // Try to parse the date; default to "millisecond 0" (very old date) if we can't
        // parse the data successfully.
        LocalDateTime retVal;
        try {
            retVal = LocalDateTime.parse(input, marc005date);
        } catch(java.time.format.DateTimeParseException e) {
            retVal = LocalDateTime.ofEpochSecond(0, 0, ZoneOffset.UTC);
        }
        return retVal;
    }

    /**
     * Support method for getLatestTransaction.
     * @return Date extracted from 008 (or very old date, if unavailable)
     */
    private LocalDateTime normalize008Date(String input)
    {
        // Normalize "null" strings to a generic bad value:
        if (input == null || input.length() < 6) {
            input = "null";
        }

        // Try to parse the date; default to "millisecond 0" (very old date) if we can't
        // parse the data successfully.
        LocalDateTime retVal;
        try {
            retVal = LocalDate.parse(input.substring(0, 6), marc008date).atStartOfDay();
        } catch(java.lang.StringIndexOutOfBoundsException | java.time.format.DateTimeParseException e) {
            retVal = LocalDateTime.ofEpochSecond(0, 0, ZoneOffset.UTC);
        }
        return retVal;
    }

    /**
     * Extract the latest transaction date from the MARC record.  This is useful
     * for detecting when a record has changed since the last time it was indexed.
     *
     * @param record MARC record
     * @return Latest transaction date.
     */
    public LocalDateTime getLatestTransaction(Record record) {
        // First try the 005 -- this is most likely to have a precise transaction date:
        for (String current005 : SolrIndexer.instance().getFieldList(record, "005")) {
            return normalize005Date(current005);
        }

        // No luck with 005?  Try 008 next -- less precise, but better than nothing:
        for (String current008 : SolrIndexer.instance().getFieldList(record, "008")) {
            return normalize008Date(current008);
        }

        // If we got this far, we couldn't find a valid value; return an arbitrary date:
        return LocalDateTime.ofEpochSecond(0, 0, ZoneOffset.UTC);
    }


    /**
     * Update the index date in the database for the specified core/ID pair.  We
     * maintain a database of "first/last indexed" times separately from Solr to
     * allow the history of our indexing activity to be stored permanently in a
     * fashion that can survive even a total Solr rebuild.
     */
    public void updateTracker(String core, String id, LocalDateTime latestTransaction)
    {
        // Update the database (if necessary):
        try {
            UpdateDateTracker.instance().index(core, id, latestTransaction);
        } catch (java.sql.SQLException e) {
            // If we're in the process of shutting down, an error is expected:
            if (!DatabaseManager.instance().isShuttingDown()) {
                dieWithError("Unexpected database error");
            }
        }
    }

    /**
     * Get the "first indexed" date for the current record.  (This is the first
     * time that SolrMarc ever encountered this particular record).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to be analyzed
     * @param core core name
     * @return ID string
     */
    public String getFirstIndexed(Record record, String fieldSpec, String core) {
        // Update the database, then send back the first indexed date:
        updateTracker(core, SolrIndexer.instance().getFirstFieldVal(record, fieldSpec), getLatestTransaction(record));
        return UpdateDateTracker.instance().getFirstIndexed();
    }

    /**
     * Get the "first indexed" date for the current record.  (This is the first
     * time that SolrMarc ever encountered this particular record).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to be analyzed
     * @return ID string
     */
    public String getFirstIndexed(Record record, String fieldSpec) {
        return getFirstIndexed(record, fieldSpec, "biblio");
    }

    /**
     * Get the "first indexed" date for the current record.  (This is the first
     * time that SolrMarc ever encountered this particular record).
     *
     * @param record current MARC record
     * @return ID string
     */
    public String getFirstIndexed(Record record) {
        return getFirstIndexed(record, "001", "biblio");
    }

    /**
     * Get the "last indexed" date for the current record.  (This is the last time
     * the record changed from SolrMarc's perspective).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to be analyzed
     * @param core core name
     * @return ID string
     */
    public String getLastIndexed(Record record, String fieldSpec, String core) {
        // Update the database, then send back the last indexed date:
        updateTracker(core, SolrIndexer.instance().getFirstFieldVal(record, fieldSpec), getLatestTransaction(record));
        return UpdateDateTracker.instance().getLastIndexed();
    }

    /**
     * Get the "last indexed" date for the current record.  (This is the last time
     * the record changed from SolrMarc's perspective).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to analyze
     * @return ID string
     */
    public String getLastIndexed(Record record, String fieldSpec) {
        return getLastIndexed(record, fieldSpec, "biblio");
    }

    /**
     * Get the "last indexed" date for the current record.  (This is the last time
     * the record changed from SolrMarc's perspective).
     *
     * @param record current MARC record
     * @return ID string
     */
    public String getLastIndexed(Record record) {
        return getLastIndexed(record, "001", "biblio");
    }

    /**
     * Log an error message and throw a fatal exception.
     * @param msg message to log
     */
    private void dieWithError(String msg)
    {
        logger.error(msg);
        throw new SolrMarcIndexerException(SolrMarcIndexerException.EXIT, msg);
    }
}