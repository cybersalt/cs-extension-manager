CREATE TABLE IF NOT EXISTS `#__csextensionmanager_log` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `created`        DATETIME      NOT NULL,
    `action`         VARCHAR(20)   NOT NULL DEFAULT '',
    `status`         VARCHAR(10)   NOT NULL DEFAULT '',
    `trigger_source` VARCHAR(10)   NOT NULL DEFAULT '',
    `element`        VARCHAR(100)  NOT NULL DEFAULT '',
    `title`          VARCHAR(255)  NOT NULL DEFAULT '',
    `from_version`   VARCHAR(50)   NOT NULL DEFAULT '',
    `to_version`     VARCHAR(50)   NOT NULL DEFAULT '',
    `user_id`        INT UNSIGNED  NOT NULL DEFAULT 0,
    `elapsed_ms`     INT UNSIGNED  NOT NULL DEFAULT 0,
    `message`        VARCHAR(1024) NOT NULL DEFAULT '',
    `details`        MEDIUMTEXT    NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created`),
    KEY `idx_action_created` (`action`, `created`),
    KEY `idx_status` (`status`),
    KEY `idx_element` (`element`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__csextensionmanager_cache` (
    `cache_key` VARCHAR(100) NOT NULL,
    `fetched`   DATETIME     NOT NULL,
    `data`      MEDIUMTEXT   NOT NULL,
    PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
