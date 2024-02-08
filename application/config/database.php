<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
| This file will contain the settings needed to access your database.
|
| For complete instructions please consult the 'Database Connection'
| page of the User Guide.
|
| -------------------------------------------------------------------
| EXPLANATION OF VARIABLES
| -------------------------------------------------------------------
|
|	['dsn']      The full DSN string describe a connection to the database.
|	['hostname'] The hostname of your database server.
|	['username'] The username used to connect to the database
|	['password'] The password used to connect to the database
|	['database'] The name of the database you want to connect to
|	['dbdriver'] The database driver. e.g.: mysqli.
|			Currently supported:
|				 cubrid, ibase, mssql, mysql, mysqli, oci8,
|				 odbc, pdo, postgre, sqlite, sqlite3, sqlsrv
|	['dbprefix'] You can add an optional prefix, which will be added
|				 to the table name when using the  Query Builder class
|	['pconnect'] TRUE/FALSE - Whether to use a persistent connection
|	['db_debug'] TRUE/FALSE - Whether database errors should be displayed.
|	['cache_on'] TRUE/FALSE - Enables/disables query caching
|	['cachedir'] The path to the folder where cache files should be stored
|	['char_set'] The character set used in communicating with the database
|	['dbcollat'] The character collation used in communicating with the database
|				 NOTE: For MySQL and MySQLi databases, this setting is only used
| 				 as a backup if your server is running PHP < 5.2.3 or MySQL < 5.0.7
|				 (and in table creation queries made with DB Forge).
| 				 There is an incompatibility in PHP with mysql_real_escape_string() which
| 				 can make your site vulnerable to SQL injection if you are using a
| 				 multi-byte character set and are running versions lower than these.
| 				 Sites using Latin-1 or UTF-8 database character set and collation are unaffected.
|	['swap_pre'] A default table prefix that should be swapped with the dbprefix
|	['encrypt']  Whether or not to use an encrypted connection.
|
|			'mysql' (deprecated), 'sqlsrv' and 'pdo/sqlsrv' drivers accept TRUE/FALSE
|			'mysqli' and 'pdo/mysql' drivers accept an array with the following options:
|
|				'ssl_key'    - Path to the private key file
|				'ssl_cert'   - Path to the public key certificate file
|				'ssl_ca'     - Path to the certificate authority file
|				'ssl_capath' - Path to a directory containing trusted CA certificates in PEM format
|				'ssl_cipher' - List of *allowed* ciphers to be used for the encryption, separated by colons (':')
|				'ssl_verify' - TRUE/FALSE; Whether verify the server certificate or not
|
|	['compress'] Whether or not to use client compression (MySQL only)
|	['stricton'] TRUE/FALSE - forces 'Strict Mode' connections
|							- good for ensuring strict SQL while developing
|	['ssl_options']	Used to set various SSL options that can be used when making SSL connections.
|	['failover'] array - A array with 0 or more data for connections if the main should fail.
|	['save_queries'] TRUE/FALSE - Whether to "save" all executed queries.
| 				NOTE: Disabling this will also effectively disable both
| 				$this->db->last_query() and profiling of DB queries.
| 				When you run a query, with this setting set to TRUE (default),
| 				CodeIgniter will store the SQL statement for debugging purposes.
| 				However, this may cause high memory usage, especially if you run
| 				a lot of SQL queries ... disable this to avoid that problem.
|
| The $active_group variable lets you choose which connection group to
| make active.  By default there is only one group (the 'default' group).
|
| The $query_builder variables lets you determine whether or not to load
| the query builder class.
*/
$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
	'dsn'	=> '',
	/* 'hostname' => 'localhost',
	'username' => 'root',
	'password' => '',
	'database' => 'psdntechcpanel', */
	/* 'hostname' => 'psdntech.cq3nt3ftsmqy.ap-south-1.rds.amazonaws.com', */
	'hostname' => 'psdnlive.cq3nt3ftsmqy.ap-south-1.rds.amazonaws.com',
	'username' => 'admin',
	/* 'password' => 'QXMRbz9syGG02GjatYAd', */
	'password' => 'UEJmd3ChPZElQkQOLAYx',
	'database' => 'psdntech',
	'dbdriver' => 'mysqli',
	'dbprefix' => 'ci_',
	'pconnect' => FALSE,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE,
	'table_users'=>'users',
	'table_rto'=>'rto_number',
	'table_vehicle'=>'vehicle',
	'table_company'=>'company',
	'table_products'=>'products',
	'table_make'=>'vehicle_make',
	'table_model'=>'vehicle_model',
	'table_serial_no'=>'serial_numbers',
	'table_invoices'=>'invoices',
	'table_invoices_customer'=>'invoices_customer',
	'table_sos'=>'sos',
	'table_certificate'=>'certificate',
	'table_renewal'=>'renewal',
	'table_certificate_tracking'=>'certificate_tracking',
	'table_customers'=>'customers',
	'table_states'=>'states',
	'table_state'=>'state',
	'table_product_pricelog'=>'product_pricelog',
	'table_tracking'=>'gps_livetracking_data',
	'table_user_histroy'=>'user_history',
	'table_imei_history'=>'saved_history',
	'table_serial_no_test'=>'serial_numbers_test',
	'table_device_fitment'=>'device_fitment',
);

$db['tracking'] = array(
	'dsn'	=> '',
	/* 'hostname' => '127.0.0.1',
	'username' => 'root',
	'password' => '',
	'database' => 'psdn_livetracking', */
	'hostname' => 'psdnlive.cq3nt3ftsmqy.ap-south-1.rds.amazonaws.com',
	'username' => 'admin',
	'password' => 'UEJmd3ChPZElQkQOLAYx',
	'database' => 'live_psdn', 
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => (ENVIRONMENT !== 'production'),
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => TRUE,
	'table_users'=>'users',
	'table_rto'=>'rto_number',
	'table_vehicle'=>'vehicle',
	'table_company'=>'company',
	'table_products'=>'products',
	'table_make'=>'vehicle_make',
	'table_model'=>'vehicle_model',
	'table_serial_no'=>'serial_numbers',
	'table_invoices'=>'invoices',
	'table_invoices_customer'=>'invoices_customer',
	'table_certificate'=>'certificate',
	'table_renewal'=>'renewal',
	'table_certificate_tracking'=>'certificate_tracking',
	'table_customers'=>'customers',
	'table_trackings'=>'gps_livetracking_data'
);

$db['postgre_db'] = array(
    'dsn'       => '',
    // 'hostname' => '3.109.243.15',
    
    // 'dbname' => 'newuser',
    // /* 'user' => 'dbuser', */
    // 'username' => 'dbuser',
    // 'password' => 'IPI4Lucie',
    // 'database' => 'db_pgpsdn',
    /* 'hostname' => 'psdnpostgres.cq3nt3ftsmqy.ap-south-1.rds.amazonaws.com',
	// 'hostname' => '3.109.243.15',
    'username' => 'postgres',
    'password' => 'pHtyqVi7bmIVXNmJXtpt',
    'database' => 'psdn_postgres',
    'dbdriver' => 'postgre', */
	'hostname' => 'psdnpostgres.cq3nt3ftsmqy.ap-south-1.rds.amazonaws.com',
    'username' => 'postgres',
    'password' => 'pHtyqVi7bmIVXNmJXtpt',
    'database' => 'psdn_postgres',
	'dbdriver' => 'postgre',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => (ENVIRONMENT !== 'production'),
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt'  => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => TRUE,
    'tbl_unregistered_device_data'=>'tbl_unregistered_device_data',
	'tbl_cron_unregistered_records'=>'tbl_cron_unregistered_records',
	'tbl_registered_device_data'=>'tbl_registered_device_data',
	'tbl_cron_registered_records'=>'tbl_cron_registered_records',
	'tbl_trackinghistory_072023' => 'tbl_trackinghistory_072023',
	'port' => 5432
);