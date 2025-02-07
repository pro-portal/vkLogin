CREATE TABLE IF NOT EXISTS `#__joomlab_soc_login` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`user_id` int(11) NOT NULL,
	`social_id` varchar(255) DEFAULT NULL,
    `social_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) 
ENGINE=INNODB 
DEFAULT CHARSET=utf8 
AUTO_INCREMENT=1;