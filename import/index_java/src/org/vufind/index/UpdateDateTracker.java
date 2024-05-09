package org.vufind.index;
/**
 * Class for managing record update dates.
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

import java.sql.*;
import java.time.format.DateTimeFormatter;
import java.time.LocalDateTime;

/**
 * Class for managing record update dates.
 */
public class UpdateDateTracker
{
    private Connection db;
    private String core;
    private String id;
    private DateTimeFormatter iso8601 = DateTimeFormatter.ofPattern("yyyy-MM-dd'T'HH:mm:ss'Z'");

    private Timestamp firstIndexed;
    private Timestamp lastIndexed;
    private Timestamp lastRecordChange;
    private Timestamp deleted;

    private static ThreadLocal<UpdateDateTracker> trackerCache =
        new ThreadLocal<UpdateDateTracker>()
        {
            @Override
            protected UpdateDateTracker initialValue()
            {
                try {
                    return new UpdateDateTracker(DatabaseManager.instance().getConnection());
                } catch (SQLException e) {
                    throw new RuntimeException(e.getMessage());
                }
            }
        };

    public static UpdateDateTracker instance()
    {
        return trackerCache.get();
    }

    /* Private support method: create a row in the change_tracker table.
     */
    private void createRow(Timestamp newRecordChange) throws SQLException
    {
        // Save new values to the object:
        firstIndexed = lastIndexed = Timestamp.valueOf(LocalDateTime.now());
        lastRecordChange = newRecordChange;

        // Save new values to the database:
        try (
            PreparedStatement insertSql = db.prepareStatement(
                "INSERT INTO change_tracker(core, id, first_indexed, last_indexed, last_record_change) " +
                "VALUES(?, ?, ?, ?, ?);"
            );
        ) {
            insertSql.setString(1, core);
            insertSql.setString(2, id);
            insertSql.setTimestamp(3, firstIndexed);
            insertSql.setTimestamp(4, lastIndexed);
            insertSql.setTimestamp(5, lastRecordChange);
            insertSql.executeUpdate();
        }
    }

    /* Private support method: read a row from the change_tracker table.
     */
    private boolean readRow() throws SQLException
    {
        try (
            PreparedStatement selectSql = db.prepareStatement(
                "SELECT first_indexed, last_indexed, last_record_change, deleted " +
                "FROM change_tracker WHERE core = ? AND id = ?;",
                ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY
            )
        ) {
            selectSql.setString(1, core);
            selectSql.setString(2, id);
            try (ResultSet result = selectSql.executeQuery()) {
                // No results? Return false:
                if (!result.first()) {
                    return false;
                } else {
                    // If we got this far, we have results -- load them into the object:
                    firstIndexed = result.getTimestamp(1);
                    lastIndexed = result.getTimestamp(2);
                    lastRecordChange = result.getTimestamp(3);
                    deleted = result.getTimestamp(4);
                }
            }
        }
        return true;
    }

    /* Private support method: update a row in the change_tracker table.
     */
    private void updateRow(Timestamp newRecordChange) throws SQLException
    {
        // Save new values to the object:
        lastIndexed = Timestamp.valueOf(LocalDateTime.now());
        // If first indexed is null, we're restoring a deleted record, so
        // we need to treat it as new -- we'll use the current time.
        if (firstIndexed == null) {
            firstIndexed = lastIndexed;
        }
        lastRecordChange = newRecordChange;

        // Save new values to the database:
        try (
            PreparedStatement updateSql = db.prepareStatement(
                "UPDATE change_tracker " +
                "SET first_indexed = ?, last_indexed = ?, last_record_change = ?, deleted = ? " +
                "WHERE core = ? AND id = ?;"
            )
        ) {
            updateSql.setTimestamp(1, firstIndexed);
            updateSql.setTimestamp(2, lastIndexed);
            updateSql.setTimestamp(3, lastRecordChange);
            updateSql.setNull(4, java.sql.Types.NULL);
            updateSql.setString(5, core);
            updateSql.setString(6, id);
            updateSql.executeUpdate();
        }
    }

    /* Constructor:
     */
    public UpdateDateTracker(Connection dbConnection) throws SQLException
    {
        db = dbConnection;
    }

    /* Get the first indexed date (IMPORTANT: index() must be called before this method)
     */
    public String getFirstIndexed()
    {
        return firstIndexed.toLocalDateTime().format(iso8601);
    }

    /* Get the last indexed date (IMPORTANT: index() must be called before this method)
     */
    public String getLastIndexed()
    {
        return lastIndexed.toLocalDateTime().format(iso8601);
    }

    /* Update the database to indicate that the record has just been received by the indexer:
     */
    public void index(String selectedCore, String selectedId, LocalDateTime recordChange) throws SQLException
    {
        // If core and ID match the values currently in the class, we have already
        // indexed the record and do not need to repeat ourselves!
        if (selectedCore.equals(core) && selectedId.equals(id)) {
            return;
        }

        // If we made it this far, we need to update the database, so let's store
        // the current core/ID pair we are operating on:
        core = selectedCore;
        id = selectedId;

        // Convert incoming LocalDateTime to a Timestamp:
        Timestamp newRecordChange = Timestamp.valueOf(recordChange);

        // No row?  Create one!
        if (!readRow()) {
            createRow(newRecordChange);
        // Row already exists?  See if it needs to be updated:
        } else {
            // Are we restoring a previously deleted record, or was the stored
            // record change date before current record change date?  Either way,
            // we need to update the table!
            //
            // Note that we check for a time difference of at least one second in
            // order to count as a change.  Because dates are stored with second
            // precision, some of the date conversions have been known to create
            // minor inaccuracies in the millisecond range, which used to cause
            // false positives.
            if (deleted != null ||
                Math.abs(lastRecordChange.getTime() - newRecordChange.getTime()) > 999) {
                updateRow(newRecordChange);
            }
        }
    }
}
