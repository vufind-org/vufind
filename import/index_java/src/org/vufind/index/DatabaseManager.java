package org.vufind.index;
/**
 * Database manager.
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

import org.apache.log4j.Logger;
import org.solrmarc.tools.SolrMarcIndexerException;
import java.sql.*;
import java.nio.charset.Charset;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.nio.file.Path;

/**
 * Database manager.
 */
public class DatabaseManager
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(DatabaseManager.class.getName());

    // Initialize VuFind database connection (null until explicitly activated)
    private Connection vufindDatabase = null;

    // Shutdown flag:
    private boolean shuttingDown = false;

    private static ThreadLocal<DatabaseManager> managerCache =
        new ThreadLocal<DatabaseManager>()
        {
            @Override
            protected DatabaseManager initialValue()
            {
                return new DatabaseManager();
            }
        };

    public static DatabaseManager instance()
    {
        return managerCache.get();
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

    /**
     * Connect to the VuFind database if we do not already have a connection.
     */
    private void connectToDatabase()
    {
        // Already connected?  Do nothing further!
        if (vufindDatabase != null) {
            return;
        }
        try {
            connectToDatabaseUsingSplitConfig();
        } catch (Throwable e) {
            logger.warn("Unable to connect to database using split config (" + e.getMessage() + ")");
        }
        // If the split config allowed to setup the DB, do nothing further
        if (vufindDatabase != null) {
            return;
        }
        try {
            connectToDatabaseUsingStringConfig();
        } catch (Throwable e) {
            dieWithError("Unable to connect to VuFind database; " + e.getMessage());
        }
    }

    /**
     * Connect to the VuFind database using the legacy PHP-style connection string in config.ini.
     */
    private void connectToDatabaseUsingStringConfig() throws Throwable
    {
        String dsn = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database");
        if (dsn == null || dsn.isEmpty()) {
            throw new Exception("Cannot find working database settings in config.ini");
        }

        // Parse key settings from the PHP-style DSN:
        String platform = "invalid";
        if (dsn.substring(0, 8).equals("mysql://")) {
            platform = "mysql";
        } else if (dsn.substring(0, 8).equals("pgsql://")) {
            platform = "postgresql";
        }

        String host = "";
        String port = "";
        String name = "";
        String username = "";
        String password = "";
        String[] parts = dsn.split("://");
        if (parts.length > 1) {
            parts = parts[1].split("@");
            if (parts.length > 1) {
                String[] pathParts = parts[1].split("/");
                name = pathParts[pathParts.length - 1];
                String[] hostParts = pathParts[0].split(":");
                host = hostParts[0];
                if (hostParts.length > 1) {
                    port = hostParts[1];
                }
                parts = parts[0].split(":");
                username = parts[0];
                if (parts.length > 1) {
                    password = parts[1];
                }
            }
        }
        connectToDatabaseUsingParams(platform, host, port, name, username, password);
    }

    /**
     * Connect to the VuFind database using the preferred granular config.ini settings.
     */
    private void connectToDatabaseUsingSplitConfig() throws Throwable
    {
        String username = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_username");
        String password = "";
        String passwordFile = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_password_file");
        if (passwordFile != null && !passwordFile.isEmpty()) {
            Path passwordFilePath = Paths.get(passwordFile);
            password = Files.readString(passwordFilePath, Charset.defaultCharset()).trim();
        }
        if (password.isEmpty()) {
            password = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_password");
        }
        String host = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_host");
        String port = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_port");
        String name = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_name");
        String platform = ConfigManager.instance().getConfigSetting("config.ini", "Database", "database_driver");
        // If no platform is set, don't bother trying to connect:
        if (platform != null && !platform.isEmpty()) {
            connectToDatabaseUsingParams(platform, host, port, name, username, password);
        }
    }

    /**
     * Connect to the VuFind database using provided values
     */
    private void connectToDatabaseUsingParams(String platform, String host, String port, String name, String username, String password) throws Throwable
    {
        String classname = "invalid";
        String extraParams = "";
        String prefix = "invalid";
        if (platform.equals("mysql")) {
            classname = "com.mysql.jdbc.Driver";
            prefix = "mysql";
            String useSsl = ConfigManager.instance().getBooleanConfigSetting("config.ini", "Database", "use_ssl", false) ? "true" : "false";
            extraParams = "?useSSL=" + useSsl;
            if (useSsl != "false") {
                String verifyCert = ConfigManager.instance().getBooleanConfigSetting("config.ini", "Database", "verify_server_certificate", false) ? "true" : "false";
                extraParams += "&verifyServerCertificate=" + verifyCert;
            }
        } else if (platform.equals("pgsql") || platform.equals("postgresql")) {
            classname = "org.postgresql.Driver";
            prefix = "postgresql";
        }

        Class.forName(classname).getDeclaredConstructor().newInstance();
        String dsn = prefix + "://" + host;
        if (!port.isEmpty()) {
            dsn = dsn + ":" + port;
        }
        dsn = dsn + "/" + name;

        // Connect to the database:
        vufindDatabase = DriverManager.getConnection("jdbc:" + dsn + extraParams, username, password);

        Runtime.getRuntime().addShutdownHook(new DatabaseManagerShutdownThread(this));
    }

    private void disconnectFromDatabase()
    {
        if (vufindDatabase != null) {
            try {
                vufindDatabase.close();
            } catch (SQLException e) {
                System.err.println("Unable to disconnect from VuFind database");
                logger.error("Unable to disconnect from VuFind database");
            }
        }
    }

    public void shutdown()
    {
        disconnectFromDatabase();
        shuttingDown = true;
    }

    public Connection getConnection()
    {
        connectToDatabase();
        return vufindDatabase;
    }

    public boolean isShuttingDown()
    {
        return shuttingDown;
    }

    class DatabaseManagerShutdownThread extends Thread
    {
        private DatabaseManager manager;

        public DatabaseManagerShutdownThread(DatabaseManager m)
        {
            manager = m;
        }

        public void run()
        {
            manager.shutdown();
        }
    }
}
