drop table if exists asset_item;
drop table if exists asset_type;

create table asset_item
 (aid int(11) AUTO_INCREMENT,
  sku  varchar(8) NOT NULL,
  note  varchar(32) DEFAULT NULL,,
  primary key (aid)
 );

create table asset_type
 (
  key  varchar(4) NOT NULL,
  type  varchar(16) NOT NULL,
  primary key (key)
 );
