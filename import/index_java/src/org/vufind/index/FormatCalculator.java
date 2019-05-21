package org.vufind.index;
/**
 * Format determination logic.
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
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import java.util.ArrayList;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Format determination logic.
 */
public class FormatCalculator
{
    /**
     * Determine whether a record cannot be a book due to findings in 007.
     *
     * @param char formatCode
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOn007(char formatCode) {
        switch (formatCode) {
            // Things that are not books: filmstrips/transparencies (g),
            // pictures (k) and videos/films (m, v):
            case 'g':
            case 'k':
            case 'm':
            case 'v':
                return true;
        }
        return false;
    }

    /**
     * Determine whether a record cannot be a book due to findings in leader
     * and fixed fields (008).
     *
     * @param char formatCode
     * @param ControlField marc008
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOnRecordType(char recordType, ControlField marc008) {
        switch (recordType) {
            case 'm':
                // If this is a computer file containing numeric data, it is not a book:
                if (getTypeOfComputerFile(marc008) == 'a') {
                    return true;
                }
                break;
            case 'j':
            case 'r':
                // Music recordings (j) and Physical objects (r) are not books.
                return true;
        }
        return false;
    }

    /**
     * Return the best format string based on codes extracted from 007; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param char formatCode
     * @param String formatString
     * @return String
     */
    protected String getFormatFrom007(char formatCode, String formatString) {
        char formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
        switch (formatCode) {
            case 'a':
                return formatCode2 == 'd' ? "Atlas" : "Map";
            case 'c':
                switch(formatCode2) {
                    case 'a':
                        return "TapeCartridge";
                    case 'b':
                        return "ChipCartridge";
                    case 'c':
                        return "DiscCartridge";
                    case 'f':
                        return "TapeCassette";
                    case 'h':
                        return "TapeReel";
                    case 'j':
                        return "FloppyDisk";
                    case 'm':
                    case 'o':
                        return "CDROM";
                    case 'r':
                        // Do not return anything - otherwise anything with an
                        // 856 field would be labeled as "Electronic"
                        return "";
                }
                return "Software";
            case 'd':
                return "Globe";
            case 'f':
                return "Braille";
            case 'g':
                switch(formatCode2) {
                    case 'c':
                    case 'd':
                        return "Filmstrip";
                    case 't':
                        return "Transparency";
                }
                return "Slide";
            case 'h':
                return "Microfilm";
            case 'k':
                switch(formatCode2) {
                    case 'c':
                        return "Collage";
                    case 'd':
                        return "Drawing";
                    case 'e':
                        return "Painting";
                    case 'f':
                        return "Print";
                    case 'g':
                        return "Photonegative";
                    case 'j':
                        return "Print";
                    case 'l':
                        return "Drawing";
                    case 'o':
                        return "FlashCard";
                    case 'n':
                        return "Chart";
                }
                return "Photo";
            case 'm':
                switch(formatCode2) {
                    case 'f':
                        return "VideoCassette";
                    case 'r':
                        return "Filmstrip";
                }
                return "MotionPicture";
            case 'o':
                return "Kit";
            case 'q':
                return "MusicalScore";
            case 'r':
                return "SensorImage";
            case 's':
                switch(formatCode2) {
                    case 'd':
                        return "SoundDisc";
                    case 's':
                        return "SoundCassette";
                }
                return "SoundRecording";
            case 'v':
                switch(formatCode2) {
                    case 'c':
                        return "VideoCartridge";
                    case 'd':
                        char formatCode5 = formatString.length() > 4
                            ? formatString.charAt(4) : ' ';
                        return formatCode5 == 's' ? "BRDisc" : "VideoDisc";
                    case 'f':
                        return "VideoCassette";
                    case 'r':
                        return "VideoReel";
                }
                // assume other video is online:
                return "VideoOnline";
        }
        return "";
    }

    /**
     * Return the best format string based on bib level in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char bibLevel
     * @param char formatCode
     * @param ControlField marc008
     * @param boolean couldBeBook
     * @return String
     */
    protected String getFormatFromBibLevel(Record record, char bibLevel, char formatCode, ControlField marc008, boolean couldBeBook) {
        switch (bibLevel) {
            // Monograph
            case 'm':
                if (couldBeBook) {
                    return (formatCode == 'c') ? "eBook" : "Book";
                }
                break;
            // Component parts
            case 'a':
                return "BookComponentPart";
            case 'b':
                return "SerialComponentPart";
            // Integrating resources (e.g. loose-leaf binders, databases)
            case 'i':
                return (formatCode == 'c')
                    ? "OnlineIntegratingResource" : "PhysicalIntegratingResource";
            // Serial
            case 's':
                // Look in 008 to determine what type of Continuing Resource
                switch (marc008.getData().toLowerCase().charAt(21)) {
                    case 'n':
                        return "Newspaper";
                    case 'p':
                        return "Journal";
                    default:
                        if (!isConferenceProceeding(record)) {
                            return "Serial";
                        }
                }
                break;
        }
        return "";
    }

    /**
     * Return the best format string based on record type in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char recordType
     * @return String
     */
    protected String getFormatFromRecordType(Record record, char recordType) {
        switch (recordType) {
            case 'c':
            case 'd':
                return "MusicalScore";
            case 'e':
            case 'f':
                return "Map";
            case 'g':
                // We're going to rely on the 007 instead for Projected Media
                //return "Slide";
                return "";
            case 'i':
                return "SoundRecording";
            case 'j':
                return "MusicRecording";
            case 'k':
                return "Photo";
            case 'o':
            case 'p':
                return "Kit";
            case 'r':
                return "PhysicalObject";
            case 't':
                if (!isThesis(record)) {
                    return "Manuscript";
                }
                break;
        }
        return "";
    }

    /**
     * Extract the computer file type from the 008 field
     *
     * @param ControlField marc008
     * @return char
     */
    protected char getTypeOfComputerFile(ControlField marc008) {
        // Check the 008 for the type of computer file:
        try {
            return marc008.getData().toLowerCase().charAt(26);
        } catch (java.lang.StringIndexOutOfBoundsException e) {
            // ignore errors (leave the string blank if out of bounds)
            return ' ';
        }
    }

    /**
     * Determine whether a record is a conference proceeding.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isConferenceProceeding(Record record) {
        // Is there a main entry meeting name?
        DataField conference1 = (DataField) record.getVariableField("111");
        // The 711 could possibly have more then one entry, although probably unlikely
        DataField conference2 = (DataField) null;
        List conferenceFields = record.getVariableFields("711");
        Iterator conferenceFieldsIter = conferenceFields.iterator();
        if (conferenceFields != null) {
            if (conferenceFieldsIter.hasNext()) {
                conference2 = (DataField) conferenceFieldsIter.next();
            }
        }
        return (conference1 != null || conference2 != null);
    }

    /**
     * Determine whether a record is electronic in format.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isElectronic(Record record) {
        /* Example from Villanova of how to use holdings locations to detect online status;
         * You can override this method in a subclass if you wish to use this approach.
        DataField holdingsField = (DataField) record.getVariableField("852");
        if (holdingsField != null) {
            if (holdingsField.getSubfield('b') != null) {
                String holdingsLocation = holdingsField.getSubfield('b').getData().toLowerCase();
                if (holdingsLocation.equals("www") || holdingsLocation.equals("e-ref")) {
                    return true;
                }
            }
        }
         */
        DataField title = (DataField) record.getVariableField("245");
        if (title != null) {
            if (title.getSubfield('h') != null) {
                if (title.getSubfield('h').getData().toLowerCase().contains("[electronic resource]")) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine whether a record is a government document.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isGovernmentDocument(Record record) {
        // Is there a SuDoc number? If so, it's a government document.
        return record.getVariableField("086") != null;
    }


    /**
     * Determine whether a record is a thesis.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isThesis(Record record) {
        // Is there a dissertation note? If so, it's a government document.
        return record.getVariableField("502") != null;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  Record record
     * @return Set format(s) of record
     */
    protected List<String> getFormatsAsList(Record record) {
        List<String> result = new ArrayList<String>();
        String leader = record.getLeader().toString();
        ControlField marc008 = (ControlField) record.getVariableField("008");
        String formatString;
        char formatCode = ' ';

        // This record could be a book... until we prove otherwise!
        boolean couldBeBook = true;

        // Some format-specific special cases:
        if (isGovernmentDocument(record)) {
            result.add("GovernmentDocument");
        }
        if (isThesis(record)) {
            result.add("Thesis");
        }
        if (isElectronic(record)) {
            result.add("Electronic");
        }
        if (isConferenceProceeding(record)) {
            result.add("ConferenceProceeding");
        }

        // check the 007 - this is a repeating field
        List fields = record.getVariableFields("007");
        Iterator fieldsIter = fields.iterator();
        if (fields != null) {
            ControlField formatField;
            while(fieldsIter.hasNext()) {
                formatField = (ControlField) fieldsIter.next();
                formatString = formatField.getData().toLowerCase();
                formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
                if (definitelyNotBookBasedOn007(formatCode)) {
                    couldBeBook = false;
                }
                if (formatCode == 'v') {
                    // All video content should get flagged as video; we will also
                    // add a more detailed value in getFormatFrom007 to distinguish
                    // different types of video.
                    result.add("Video");
                }
                String formatFrom007 = getFormatFrom007(formatCode, formatString);
                if (formatFrom007.length() > 0) {
                    result.add(formatFrom007);
                }
            }
        }

        // check the Leader at position 6
        char recordType = Character.toLowerCase(leader.charAt(6));
        if (definitelyNotBookBasedOnRecordType(recordType, marc008)) {
            couldBeBook = false;
        }
        String formatFromRecordType = getFormatFromRecordType(record, recordType);
        if (formatFromRecordType.length() > 0) {
            result.add(formatFromRecordType);
        }

        // check the Leader at position 7
        char bibLevel = Character.toLowerCase(leader.charAt(7));
        String formatFromBibLevel = getFormatFromBibLevel(
            record, bibLevel, formatCode, marc008, couldBeBook
        );
        if (formatFromBibLevel.length() > 0) {
            result.add(formatFromBibLevel);
        }

        // Nothing worked -- time to set up a value of last resort!
        if (result.isEmpty()) {
            // If the leader bit indicates a "Collection," treat it as a kit for now;
            // this is a rare case but helps cut down on the number of unknowns.
            result.add(bibLevel == 'c' ? "Kit" : "Unknown");
        }

        return result;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  record MARC record
     * @return set of record formats
     */
    public Set<String> getFormats(final Record record) {
        // Deduplicate list by converting to set:
        return new LinkedHashSet<String>(getFormatsAsList(record));
   }

   /**
    * Determine Record Format
    *
    * @param  record MARC record
    * @return set of record formats
    */
   public Set<String> getFormat(final Record record) {
       // Create set containing first element from list:
       Set<String> result = new LinkedHashSet<String>();
       List<String> list = getFormatsAsList(record);
       result.add(list.get(0));
       return result;
  }
}