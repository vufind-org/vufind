CREATE USER vufind IDENTIFIED BY 'vufind';
-- `vufind`@`localhost` does not work because the application and the database
-- run in separate containers
GRANT ALL ON vufind.* TO `vufind`;
