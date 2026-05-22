-- ============================================================================
-- AR Radius - Database Schema
-- Ubuntu 24.04 / MariaDB 10.x / FreeRADIUS 3.x
-- ============================================================================
-- This schema includes:
--   * Standard FreeRADIUS tables (radcheck, radreply, radacct, etc.)
--   * AR Radius admin tables (ar_admins, ar_settings, ar_audit_log)
-- ============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radcheck  (per-user check attributes: password, Simul-Use, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radcheck (
    id        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    username  VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op        CHAR(2) NOT NULL DEFAULT '==',
    value     VARCHAR(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radreply  (per-user reply attributes)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radreply (
    id        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    username  VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op        CHAR(2) NOT NULL DEFAULT '=',
    value     VARCHAR(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radgroupcheck
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radgroupcheck (
    id        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op        CHAR(2) NOT NULL DEFAULT '==',
    value     VARCHAR(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radgroupreply
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radgroupreply (
    id        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    attribute VARCHAR(64) NOT NULL DEFAULT '',
    op        CHAR(2) NOT NULL DEFAULT '=',
    value     VARCHAR(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radusergroup
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radusergroup (
    id        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    username  VARCHAR(64) NOT NULL DEFAULT '',
    groupname VARCHAR(64) NOT NULL DEFAULT '',
    priority  INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY username (username(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radacct  (accounting / session data)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radacct (
    radacctid           BIGINT(21) NOT NULL AUTO_INCREMENT,
    acctsessionid       VARCHAR(64) NOT NULL DEFAULT '',
    acctuniqueid        VARCHAR(32) NOT NULL DEFAULT '',
    username            VARCHAR(64) NOT NULL DEFAULT '',
    groupname           VARCHAR(64) NOT NULL DEFAULT '',
    realm               VARCHAR(64) DEFAULT '',
    nasipaddress        VARCHAR(15) NOT NULL DEFAULT '',
    nasportid           VARCHAR(32) DEFAULT NULL,
    nasporttype         VARCHAR(32) DEFAULT NULL,
    acctstarttime       DATETIME NULL DEFAULT NULL,
    acctupdatetime      DATETIME NULL DEFAULT NULL,
    acctstoptime        DATETIME NULL DEFAULT NULL,
    acctinterval        INT(12) DEFAULT NULL,
    acctsessiontime     INT(12) UNSIGNED DEFAULT NULL,
    acctauthentic       VARCHAR(32) DEFAULT NULL,
    connectinfo_start   VARCHAR(50) DEFAULT NULL,
    connectinfo_stop    VARCHAR(50) DEFAULT NULL,
    acctinputoctets     BIGINT(20) DEFAULT NULL,
    acctoutputoctets    BIGINT(20) DEFAULT NULL,
    calledstationid     VARCHAR(50) NOT NULL DEFAULT '',
    callingstationid    VARCHAR(50) NOT NULL DEFAULT '',
    acctterminatecause  VARCHAR(32) NOT NULL DEFAULT '',
    servicetype         VARCHAR(32) DEFAULT NULL,
    framedprotocol      VARCHAR(32) DEFAULT NULL,
    framedipaddress     VARCHAR(15) NOT NULL DEFAULT '',
    framedipv6address   VARCHAR(45) NOT NULL DEFAULT '',
    framedipv6prefix    VARCHAR(45) NOT NULL DEFAULT '',
    framedinterfaceid   VARCHAR(44) NOT NULL DEFAULT '',
    delegatedipv6prefix VARCHAR(45) NOT NULL DEFAULT '',
    class               VARCHAR(64) DEFAULT NULL,
    PRIMARY KEY (radacctid),
    UNIQUE KEY acctuniqueid (acctuniqueid),
    KEY username (username),
    KEY framedipaddress (framedipaddress),
    KEY acctsessionid (acctsessionid),
    KEY acctstarttime (acctstarttime),
    KEY acctstoptime (acctstoptime),
    KEY nasipaddress (nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: radpostauth  (authentication audit trail)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS radpostauth (
    id           INT(11) NOT NULL AUTO_INCREMENT,
    username     VARCHAR(64) NOT NULL DEFAULT '',
    pass         VARCHAR(64) NOT NULL DEFAULT '',
    reply        VARCHAR(32) NOT NULL DEFAULT '',
    authdate     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    class        VARCHAR(64) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY username (username),
    KEY authdate (authdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- FreeRADIUS: nas  (NAS / network device list)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nas (
    id          INT(10) NOT NULL AUTO_INCREMENT,
    nasname     VARCHAR(128) NOT NULL,
    shortname   VARCHAR(32) DEFAULT NULL,
    type        VARCHAR(30) DEFAULT 'other',
    ports       INT(5) DEFAULT NULL,
    secret      VARCHAR(60) DEFAULT 'secret',
    server      VARCHAR(64) DEFAULT NULL,
    community   VARCHAR(50) DEFAULT NULL,
    description VARCHAR(200) DEFAULT 'RADIUS Client',
    PRIMARY KEY (id),
    KEY nasname (nasname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- AR Radius - Custom tables
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ar_admins  (Web GUI admin accounts)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ar_admins (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login    DATETIME NULL DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- ar_user_meta  (extended user info: expiry, full name, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ar_user_meta (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username     VARCHAR(64) NOT NULL,
    full_name    VARCHAR(128) DEFAULT NULL,
    email        VARCHAR(128) DEFAULT NULL,
    expiry       DATETIME NULL DEFAULT NULL,
    notes        TEXT DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- ar_settings  (key-value app settings)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ar_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- ar_audit_log  (security / action audit trail)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ar_audit_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user VARCHAR(64) DEFAULT NULL,
    action     VARCHAR(64) NOT NULL,
    target     VARCHAR(128) DEFAULT NULL,
    details    TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY admin_user (admin_user),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default settings
INSERT IGNORE INTO ar_settings (setting_key, setting_value) VALUES
    ('default_simultaneous_use', '1'),
    ('online_session_threshold_minutes', '15');

SET FOREIGN_KEY_CHECKS=1;
