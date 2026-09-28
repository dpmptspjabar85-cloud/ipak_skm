CREATE TABLE IF NOT EXISTS ipak_kbli (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kode_gabungan VARCHAR(32) NOT NULL,
    kategori VARCHAR(32) NOT NULL DEFAULT '',
    kode VARCHAR(32) NOT NULL DEFAULT '',
    sektor_bps VARCHAR(255) NOT NULL DEFAULT '',
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NOT NULL,
    digit TINYINT UNSIGNED NOT NULL DEFAULT 0,
    hirarki VARCHAR(40) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ipak_kbli_kode_gabungan (kode_gabungan),
    KEY idx_ipak_kbli_kategori (kategori),
    KEY idx_ipak_kbli_kode (kode),
    KEY idx_ipak_kbli_digit_hirarki (digit, hirarki)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;