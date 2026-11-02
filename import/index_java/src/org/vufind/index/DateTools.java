package org.vufind.index;
/**
 * Date indexing routines.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;
import org.solrmarc.tools.DataUtil;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Date indexing routines.
 */
public class DateTools
{
    private final static Pattern YEAR_PATTERN = Pattern.compile("-?\\d{1,4}");
    private final static Pattern BC_YEAR_PATTERN = Pattern.compile("([0-9]+) [Bb][.]?\\s?[Cc][.]?");

    /**
     * Get all available dates from the record.
     *
     * @param  record MARC record
     * @return set of dates
     */
    public Set<String> getDates(final Record record) {
        return this.getDates(record, true);
    }

    /**
     * Get all available dates from the record with a more lenient validity check.
     *
     * @param  record MARC record
     * @return set of dates
     */
    public Set<String> getDatesRelaxed(final Record record) {
        return this.getDates(record, false);
    }

    /**
     * Get all available dates from the record.
     *
     * @param  record MARC record
     * @param  strict use strict date validity checks?
     * @return set of dates
     */
    public Set<String> getDates(final Record record, final Boolean strict) {
        Set<String> dates = new LinkedHashSet<String>();

        // First check old-style 260c date:
        List<VariableField> list260 = record.getVariableFields("260");
        for (VariableField vf : list260) {
            DataField df = (DataField) vf;
            List<Subfield> currentDates = df.getSubfields('c');
            for (Subfield sf : currentDates) {
                String currentDateStr = strict ? DataUtil.cleanDate(sf.getData()) : this.extractYear(sf.getData());
                if (currentDateStr != null) dates.add(currentDateStr);
            }
        }

        // Now track down relevant RDA-style 264c dates; we only care about
        // copyright and publication dates (and ignore copyright dates if
        // publication dates are present).
        Set<String> pubDates = new LinkedHashSet<String>();
        Set<String> copyDates = new LinkedHashSet<String>();
        List<VariableField> list264 = record.getVariableFields("264");
        for (VariableField vf : list264) {
            DataField df = (DataField) vf;
            List<Subfield> currentDates = df.getSubfields('c');
            for (Subfield sf : currentDates) {
                String currentDateStr = strict ? DataUtil.cleanDate(sf.getData()) : this.extractYear(sf.getData());
                char ind2 = df.getIndicator2();
                switch (ind2)
                {
                    case '1':
                        if (currentDateStr != null) pubDates.add(currentDateStr);
                        break;
                    case '4':
                        if (currentDateStr != null) copyDates.add(currentDateStr);
                        break;
                }
            }
        }
        if (pubDates.size() > 0) {
            dates.addAll(pubDates);
        } else if (copyDates.size() > 0) {
            dates.addAll(copyDates);
        }

        return dates;
    }

    /**
     * Get the earliest publication date from the record.
     *
     * @param  record MARC record
     * @return earliest date
     */
    public String getFirstDate(final Record record) {
        return getFirstDate(record, true);
    }

    /**
     * Get the earliest publication date from the record with a more lenient validity check.
     *
     * @param  record MARC record
     * @return earliest date
     */
    public String getFirstDateRelaxed(final Record record) {
        return getFirstDate(record, false);
    }

    /**
     * Get the earliest publication date from the record.
     *
     * @param  record MARC record
     * @param  strict use strict date validity checks?
     * @return earliest date
     */
    public String getFirstDate(final Record record, final Boolean strict) {
        String result = null;
        Set<String> dates = getDates(record, strict);
        for(String current: dates) {
            if (result == null || Integer.parseInt(current) < Integer.parseInt(result)) {
                result = current;
            }
        }
        return result;
    }

    /**
     * Extract a year from a string
     *
     * @param year year that can contain braces etc.
     * @return year with leading zeroes for four digits, if found
     */
    protected String extractYear(final String year) {
        String prefix = "";
        String found_year = null;
        Matcher bc_matcher = BC_YEAR_PATTERN.matcher(year);
        if (bc_matcher.find()) {
            prefix = "-";
            found_year = bc_matcher.group(1);
        } else {
            Matcher matcher = YEAR_PATTERN.matcher(year);
            if (matcher.find()) {
                found_year = matcher.group();
            }
        }

        return null != found_year ? prefix + String.format("%04d", Integer.parseInt(found_year)) : null;
    }
}
