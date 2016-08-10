--
-- Table structure for table `invoice`
--

CREATE TABLE IF NOT EXISTS `invoice` (
  `id` int(16) NOT NULL AUTO_INCREMENT,
  `invoice_no` int(16) NOT NULL,
  `project_id` int(16) NOT NULL,
  `month` TINYINT(1) NOT NULL,
  `year` YEAR(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_project_date` (`project_id`,`month`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
ALTER TABLE `invoice` ADD FOREIGN KEY (`project_id`) REFERENCES `ki_pct`(`pct_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;