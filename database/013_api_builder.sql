-- API Builder untuk membagikan data survei secara aman ke aplikasi lain.
-- Migrasi ini hanya menambah tabel baru dan tidak mengubah tabel SKM lama.

CREATE TABLE IF NOT EXISTS ipak_api_clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_name VARCHAR(120) NOT NULL,
    client_code VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    api_key_prefix VARCHAR(20) NOT NULL,
    api_key_hash CHAR(64) NOT NULL,
    survey_scope ENUM('all', 'selected') NOT NULL DEFAULT 'selected',
    allowed_survey_ids TEXT NOT NULL,
    allowed_resources TEXT NOT NULL,
    allowed_dimensions TEXT NOT NULL,
    allowed_detail_fields TEXT NOT NULL,
    max_page_size SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    rate_limit_per_minute SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    allowed_ip_addresses TEXT NULL,
    allowed_origin VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_api_client_code (client_code),
    UNIQUE KEY uq_ipak_api_key_hash (api_key_hash),
    KEY idx_ipak_api_client_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ipak_api_access_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_client_id INT UNSIGNED NOT NULL,
    resource_name VARCHAR(30) NOT NULL,
    survey_id INT UNSIGNED NULL,
    request_method VARCHAR(10) NOT NULL DEFAULT 'GET',
    request_ip VARCHAR(45) NULL,
    query_string TEXT NULL,
    status_code SMALLINT UNSIGNED NOT NULL,
    response_count INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ipak_api_log_client_time (api_client_id, requested_at),
    KEY idx_ipak_api_log_status_time (status_code, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
