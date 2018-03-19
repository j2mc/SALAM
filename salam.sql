CREATE DATABASE `salam`;
GRANT USAGE ON *.* TO salam@localhost IDENTIFIED by "s@1aM";
GRANT ALL PRIVILEGES ON `salam`.* TO salam@localhost;
FLUSH PRIVILEGES;
USE salam;
CREATE TABLE `hostdata` (`id` int PRIMARY KEY AUTO_INCREMENT NOT NULL, `host_id` int, `type` tinytext, `data` tinytext);
CREATE TABLE `alerts` (`id` int PRIMARY KEY AUTO_INCREMENT NOT NULL, `type` tinytext, `type_id` int, `active` BOOLEAN, `level` tinyint, `start` int, `lastcheck` int, `email_sent` BOOLEAN, `checked` int);
CREATE TABLE `hosts` (`id` int PRIMARY KEY AUTO_INCREMENT NOT NULL, `name` tinytext, `description` tinytext,`enabled` BOOLEAN, `site_id` int,`status` tinyint, `avg_rt` bigint NOT NULL DEFAULT 0, `avg_count` int NOT NULL DEFAULT 0, `lastcheck` int, `uptime` int DEFAULT 0, `downtime` int DEFAULT 0, `warntime` int DEFAULT 0, `last_rt` int);
CREATE TABLE `sites` (`id` int PRIMARY KEY AUTO_INCREMENT NOT NULL,`name` tinytext, `subnet` tinytext, `exclude` tinytext,`hostmethod` tinytext, `servicecmd` tinytext, `alert_new_hosts` BOOLEAN, `alert_new_services` BOOLEAN, `warn_percent` tinyint DEFAULT 5, `service_int` tinyint NOT NULL DEFAULT 5, `discovery_int` tinyint NOT NULL DEFAULT 60, `status` tinyint, `lastcheck` int DEFAULT 0, `uptime` int DEFAULT 0, `downtime` int DEFAULT 0);
CREATE TABLE `services` (`id` int PRIMARY KEY AUTO_INCREMENT NOT NULL, `host_id` int, `type` tinytext, `port` tinytext, `name` tinytext, `enabled` BOOLEAN, `status` tinyint, `avg_rt` bigint DEFAULT 0, `avg_count` int DEFAULT 0, `lastcheck` int DEFAULT 0, `uptime` int DEFAULT 0, `downtime` int DEFAULT 0, `warntime` int DEFAULT 0, `last_rt` int);
CREATE TABLE `checkdata` (`id` int PRIMARY KEY AUTO_INCREMENT NOT NULL, `type` tinytext, `type_id` int, `time` int, `rtime` int);
CREATE TABLE `archivedata` ( `id` INT NOT NULL AUTO_INCREMENT , `type` TINYTEXT NOT NULL , `type_id` INT NOT NULL , `time` INT NOT NULL , `min_rt` INT NOT NULL , `avg_rt` INT NOT NULL , `max_rt` INT NOT NULL , `resolution` TINYINT , PRIMARY KEY (`id`));
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