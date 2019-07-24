CREATE TABLE wikis(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	url VARCHAR(255) NOT NULL,
	name VARCHAR(255)	NOT NULL,
	public BOOLEAN
);

CREATE TABLE objs(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	ident VARCHAR(64) NOT NULL,
	name VARCHAR(255) NOT NULL,
	subset_name VARCHAR(255),
	subset VARCHAR(64),
	type ENUM('variable','study','method','assessment','class','nugget','encyclopedia') NOT NULL,
	page INT UNSIGNED NOT NULL,
	wiki_id INT UNSIGNED NOT NULL,
	UNIQUE INDEX ident_subset_index (ident, subset)
);

CREATE TABLE acts(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	obj_id INT UNSIGNED NOT NULL,
	series_id INT UNSIGNED NOT NULL,
	samples INT UNSIGNED NOT NULL,
	cells INT UNSIGNED,
	unit VARCHAR(64),
	type ENUM('replace','append') NOT NULL,
	who VARCHAR(255),
	`when` TIMESTAMP,
	comments VARCHAR(255),
	language VARCHAR(3) NOT NULL DEFAULT "eng",
	INDEX objs_index (obj_id),
	FOREIGN KEY (obj_id) REFERENCES objs(id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE inds(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	series_id INT UNSIGNED NOT NULL,
	ident VARCHAR(8) NOT NULL,
	type ENUM('entity','number','time') NOT NULL,
	name VARCHAR(255) NOT NULL,
	unit VARCHAR(64),
	page INT UNSIGNED NOT NULL,
	wiki_id INT UNSIGNED NOT NULL,
	order_index INT UNSIGNED NOT NULL,
	hidden BOOLEAN DEFAULT FALSE,
	UNIQUE INDEX series_ident_index (series_id, ident)
);

CREATE TABLE upload_sessions(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	act_id INT UNSIGNED,
	token VARCHAR(32) NOT NULL,
	opened TIMESTAMP,
	uploads INT UNSIGNED,
	UNIQUE INDEX token_index (token),
	FOREIGN KEY (act_id) REFERENCES acts(id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE download_sessions(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	act_id INT UNSIGNED,
	token VARCHAR(32) NOT NULL,
	opened TIMESTAMP,
	chunk_counter INT UNSIGNED,
	UNIQUE INDEX token_index (token),
	FOREIGN KEY (act_id) REFERENCES acts(id) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE users(
	id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(255) NOT NULL,
	password VARCHAR(255) NOT NULL,
	privileges VARCHAR(255) NOT NULL
);

INSERT INTO wikis(url,name,public) VALUES ("http://en.opasnet.org/w/index.php?title=rdb&curid=","Opasnet",TRUE);
INSERT INTO wikis(url,name,public) VALUES ("http://fi.opasnet.org/fi/index.php?title=rdb&curid=","FI Opasnet",TRUE);

INSERT INTO users(username, password, privileges) VALUES ("admin","","Opasnet=RW,FI Opasnet=RW");
INSERT INTO users(username, password, privileges) VALUES ("opasnet_en","","Opasnet=RW");
INSERT INTO users(username, password, privileges) VALUES ("opasnet_fi","","FI Opasnet=RW");



