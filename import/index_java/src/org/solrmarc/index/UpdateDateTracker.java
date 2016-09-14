package org.solrmarc.index;
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import java.sql.*;
import java.text.SimpleDateFormat;

public class UpdateDateTracker
{
    private Connection db;
    private String core;
    private String id;
    private SimpleDateFormat iso8601 = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");

    private Timestamp firstIndexed;
    private Timestamp lastIndexed;
    private Timestamp lastRecordChange;
    private Timestamp deleted;

    PreparedStatement insertSql;
    PreparedStatement selectSql;
    PreparedStatement updateSql;

    /* Private support method: create a row in the change_tracker table.
     */
    private void createRow(Timestamp newRecordChange) throws SQLException
    {
        // Save new values to the object:
        java.util.Date rightNow = new java.util.Date();
        firstIndexed = lastIndexed = new Timestamp(rightNow.getTime());
        lastRecordChange = newRecordChange;
        
        // Save new values to the database:
        insertSql.setString(1, core);
        insertSql.setString(2, id);
        insertSql.setTimestamp(3, firstIndexed);
        insertSql.setTimestamp(4, lastIndexed);
        insertSql.setTimestamp(5, lastRecordChange);
        insertSql.executeUpdate();
    }

    /* Private support method: read a row from the change_tracker table.
     */
    private boolean readRow() throws SQLException
    {
        selectSql.setString(1, core);
        selectSql.setString(2, id);
        ResultSet result = selectSql.executeQuery();
        
        // No results?  Free resources and return false:
        if (!result.first()) {
            result.close();
            return false;
        }
        
        // If we got this far, we have results -- load them into the object:
        firstIndexed = result.getTimestamp(1);
        lastIndexed = result.getTimestamp(2);
        lastRecordChange = result.getTimestamp(3);
        deleted = result.getTimestamp(4);

        // Free resources and report success:
        result.close();
        return true;
    }

    /* Private support method: update a row in the change_tracker table.
     */
    private void updateRow(Timestamp newRecordChange) throws SQLException
    {
        // Save new values to the object:
        java.util.Date rightNow = new java.util.Date();
        lastIndexed = new Timestamp(rightNow.getTime());
        // If first indexed is null, we're restoring a deleted record, so
        // we need to treat it as new -- we'll use the current time.
        if (firstIndexed == null) {
            firstIndexed = lastIndexed;
        }
        lastRecordChange = newRecordChange;

        // Save new values to the database:
        updateSql.setTimestamp(1, firstIndexed);
        updateSql.setTimestamp(2, lastIndexed);
        updateSql.setTimestamp(3, lastRecordChange);
        updateSql.setNull(4, java.sql.Types.NULL);
        updateSql.setString(5, core);
        updateSql.setString(6, id);
        updateSql.executeUpdate();
    }

    /* Constructor:
     */
    public UpdateDateTracker(Connection dbConnection) throws SQLException
    {
        db = dbConnection;
        insertSql = db.prepareStatement(
            "INSERT INTO change_tracker(core, id, first_indexed, last_indexed, last_record_change) " +
            "VALUES(?, ?, ?, ?, ?);");
        selectSql = db.prepareStatement(
            "SELECT first_indexed, last_indexed, last_record_change, deleted " +
            "FROM change_tracker WHERE core = ? AND id = ?;",
            ResultSet.TYPE_SCROLL_INSENSITIVE, ResultSet.CONCUR_READ_ONLY);
        updateSql = db.prepareStatement("UPDATE change_tracker " +
            "SET first_indexed = ?, last_indexed = ?, last_record_change = ?, deleted = ? " +
            "WHERE core = ? AND id = ?;");
    }

    /* Finalizer
     */
    protected void finalize() throws SQLException, Throwable
    {
        insertSql.close();
        selectSql.close();
        updateSql.close();
        super.finalize();
    }

    /* Get the first indexed date (IMPORTANT: index() must be called before this method)
     */
    public String getFirstIndexed()
    {
        return iso8601.format(new java.util.Date(firstIndexed.getTime()));
    }

    /* Get the last indexed date (IMPORTANT: index() must be called before this method)
     */
    public String getLastIndexed()
    {
        return iso8601.format(new java.util.Date(lastIndexed.getTime()));
    }

    /* Update the database to indicate that the record has just been received by the indexer:
     */
    public void index(String selectedCore, String selectedId, java.util.Date recordChange) throws SQLException
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

        // Convert incoming java.util.Date to a Timestamp:
        Timestamp newRecordChange = new Timestamp(recordChange.getTime());

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
