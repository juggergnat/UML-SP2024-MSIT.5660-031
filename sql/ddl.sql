CREATE DATABASE IF NOT EXISTS webdb;

use webdb;

drop table if exists asset_item;
drop table if exists asset_type;

create table asset_type
 (
  akey  varchar(4) NOT NULL,
  atype  varchar(16) NOT NULL,
  primary key (akey)
 );

create table asset_item
 (aid int(11) AUTO_INCREMENT,
  sku  varchar(8) NOT NULL,
  akey varchar(4) NOT NULL,
  person varchar(40) DEFAULT NULL,
  note  varchar(32) DEFAULT NULL,
  image varchar(256) DEFAULT NULL,
  primary key (aid),
  foreign key (akey) references asset_type (akey)
 );

