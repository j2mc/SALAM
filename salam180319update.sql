USE salam;
ALTER TABLE `archivedata` CHANGE `daily` `resolution` TINYINT(1) NOT NULL;
CREATE TABLE `runstats` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT NOT NULL,
  `time` int(11) NOT NULL,
  `runtime` float NOT NULL,
  `hosts` smallint(6) NOT NULL,
  `services` smallint(6) NOT NULL,
  `warning` smallint(6) NOT NULL,
  `warning_conf` smallint(6) NOT NULL,
  `critical` smallint(6) NOT NULL,
  `critical_conf` smallint(6) NOT NULL,
  `email_sent` tinyint(1) NOT NULL,
  `rows_added` int(11) NOT NULL,
  `rows_deleted` int(11) NOT NULL
)