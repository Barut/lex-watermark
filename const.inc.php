<?php

    define('LOG_METHOD', '|console_color|file|');
    define('LOG_TYPES', '|NOTICE|ERROR|SQL_DEBUG|DEBUG|CURL_DEBUG|');
    define('LOG_FILE', dirname(__FILE__).'/log.txt');


    define('MYSQL_DB', 'test');
    define('MYSQL_HOST', 'localhost');
    define('MYSQL_USER', 'test_user');
    define('MYSQL_PASS', 'test_pass');
	
    define('ORIGINALS_DIR', '/home/barut/originals');
    define('IMAGE_DIR', '/home/barut/images');

    define('MAX_INPUT', 50);

    define('WATERMARK_IMAGE','/home/barut/watermark.png');
    define('WATERMARK_HASH','/home/barut/watermark.hash');

    define('HASH_TYPE', 'crc32');

    define('TPL_TABLE', '
                CREATE TABLE IF NOT EXISTS `%table%` (
                `file_hash` varchar(8) NOT NULL,
		`file_path` varchar(300) BINARY NOT NULL,
                `file_type` varchar(8) NOT NULL,
                `file_size` int(11) NOT NULL,
		PRIMARY KEY `file_hash` (`file_hash`),
                KEY `file_path` (`file_path`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;
                ');

    define('TPL_TABLE_ORIGINALS', '
		CREATE TABLE IF NOT EXISTS `%table%` (
                `original_hash` varchar(8) NOT NULL,
		`original_type` varchar(8) NOT NULL,
		`file_path` varchar(300) BINARY NOT NULL,
                `file_size` int(11) NOT NULL,
		PRIMARY KEY `original_hash` (`original_hash`),
                KEY `file_path` (`file_path`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;
	    ');

    define('TPL_SQL_NEW','
		SELECT `watermark_new`.`file_hash`, `watermark_new`.`file_path`, `watermark_new`.`file_type` 
                FROM `watermark_main` RIGHT JOIN `watermark_new`
                ON `watermark_main`.`file_hash`=`watermark_new`.`file_hash`
                WHERE `watermark_main`.`file_hash` IS NULL
	');

    define('TPL_SQL_CHANGED','
	    SELECT `watermark_new`.`file_hash`, `watermark_new`.`file_path`, `watermark_new`.`file_type` 
            FROM `watermark_new` INNER JOIN `watermark_main` 
            ON `watermark_new`.`file_path`=`watermark_main`.`file_path`
            WHERE `watermark_new`.`file_hash` <> `watermark_main`.`file_hash`
	');


    define ('MAIN_TABLE', 'watermark_main');
    define ('NEW_TABLE', 'watermark_new');
    define ('ORIGINALS_TABLE','watermark_original');

    
?>
