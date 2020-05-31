CREATE TABLE `armory`.`armory_session` (
  `sid` CHAR(32) NOT NULL,
  `shash` CHAR(32) NULL DEFAULT NULL,
  `ip` INT(16) UNSIGNED NOT NULL,
  `logintstamp` DATE NOT NULL,
  `active` BOOLEAN,
  `is_admin` BOOLEAN
) ENGINE = MyISAM CHARSET=utf8;