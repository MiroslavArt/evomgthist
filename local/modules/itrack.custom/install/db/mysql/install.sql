CREATE TABLE if not exists itrack_mobilephone_timezone
(
    ID int(11) not null auto_increment,
    DEF_CODE int(3) not null,
    FROM_CODE int(7) not null,
    TO_CODE int(7) not null,
    BLOCK_SIZE int(11) not null,
    OPERATOR varchar(255) not null,
    REGION_CODE varchar(255) not null,
    REGION_NAME varchar(255) not null,
    TIMEZONE varchar(255) not null,
    PHONE_TYPE varchar(255) not null,
    GMT varchar(255) not null,
    MNC varchar(255) not null,
    PRIMARY KEY (ID),

    KEY IX_IMPT_C1 (DEF_CODE),
    KEY IX_IMPT_C2 (FROM_CODE, TO_CODE)
);