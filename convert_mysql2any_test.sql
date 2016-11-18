/*
TEST-File for checking cases:
   /* CONVERTED: COMMENT*/
  integer (notstandart)

*/

CREATE TABLE mysql2any_test (   /* CONVERTED: CREATE TABLE `mysql2any_test` (*/
test_id Integer   NOT NULL   ,   /* CONVERTED: `test_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Entity ID',*/
test_type_id Integer   NOT NULL DEFAULT '0' ,   /* CONVERTED: `test_type_id` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Entity Type ID',*/
test_set_id Integer   NOT NULL DEFAULT '0' ,   /* CONVERTED: `test_set_id` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Attribute Set ID',*/
test_type_id varchar(32) NOT NULL DEFAULT 'simple' ,   /* CONVERTED: `test_type_id` varchar(32) NOT NULL DEFAULT 'simple' COMMENT 'Type ID',*/
test_s varchar(64) DEFAULT NULL ,   /* CONVERTED: `test_s` varchar(64) DEFAULT NULL COMMENT 'SKU',*/
test_created_at timestamp NULL DEFAULT NULL ,   /* CONVERTED: `test_created_at` timestamp NULL DEFAULT NULL COMMENT 'Creation Time',*/
test_has_options Integer NOT NULL DEFAULT '0' ,   /* CONVERTED: `test_has_options` smallint(6) NOT NULL DEFAULT '0' COMMENT 'Has Options',*/
test_options Integer   NOT NULL DEFAULT '0' ,   /* CONVERTED: `test_options` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Required Options',*/
PRIMARY KEY (entity_id),   /* CONVERTED: PRIMARY KEY (`entity_id`),*/
FOREIGN KEY mysql2any_test(test_type_id)   /* CONVERTED: KEY `mysql2any_test_test_type_id` (`test_type_id`),*/
FOREIGN KEY mysql2any_test_ATTRIBUTE(test_set_id)   /* CONVERTED: KEY `mysql2any_test_ATTRIBUTE_test_set_id` (`test_set_id`),*/
FOREIGN KEY mysql2any_testT_ENTITY(test_s)   /* CONVERTED: KEY `mysql2any_testT_ENTITY_test_s` (`test_s`),*/
CONSTRAINT FK_mysql2any_t2 FOREIGN KEY (test_set_id) REFERENCES mysql2any_t2 (test2_id) ON DELETE CASCADE ON UPDATE CASCADE   /* CONVERTED: CONSTRAINT `FK_mysql2any_t2` FOREIGN KEY (`test_set_id`) REFERENCES `mysql2any_t2` (`test2_id`) ON DELETE CASCADE ON UPDATE CASCADE*/
);   /* CONVERTED: ) ENGINE=InnoDB AUTO_INCREMENT=11571 DEFAULT CHARSET=utf8 COMMENT='mysql2any_t2';*/


CREATE TABLE mysql2any_t2 (   /* CONVERTED: CREATE TABLE `mysql2any_t2` (*/
test2_id Integer   NOT NULL   ,   /* CONVERTED: `test2_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Entity ID',*/
test2_type_id Integer   NOT NULL DEFAULT '0'    /* CONVERTED: `test2_type_id` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Entity Type ID'*/
);   /* CONVERTED: ) ENGINE=InnoDB AUTO_INCREMENT=11571 DEFAULT CHARSET=utf8 COMMENT='test_table2';*/
