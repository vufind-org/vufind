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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;
import org.solrmarc.tools.DataUtil;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Date indexing routines.
 */
public class DateTools
{
    /**
     * Get all available dates from the record.
     *
     * @param  record MARC record
     * @return set of dates
     */
    public Set<String> getDates(final Record record) {
        Set<String> dates = new LinkedHashSet<String>();

        // First check old-style 260c date:
        List<VariableField> list260 = record.getVariableFields("260");
        for (VariableField vf : list260) {
            DataField df = (DataField) vf;
            List<Subfield> currentDates = df.getSubfields('c');
            for (Subfield sf : currentDates) {
                String currentDateStr = DataUtil.cleanDate(sf.getData());
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
                String currentDateStr = DataUtil.cleanDate(sf.getData());
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
        String result = null;
        Set<String> dates = getDates(record);
        for(String current: dates) {
            if (result == null || Integer.parseInt(current) < Integer.parseInt(result)) {
                result = current;
            }
        }
        return result;
    }
}