-- MySQL-Tabelle für den Admin-PDF-Upload.
-- In phpMyAdmin die Datenbank medizininformatik auswählen und dieses Skript ausführen.
-- Die Tabelle wird zusätzlich automatisch angelegt, sobald ein Admin admin/index.php öffnet.

CREATE TABLE IF NOT EXISTS wiki_pdfs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    file_size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wiki_pdfs_stored_name (stored_name),
    KEY idx_wiki_pdfs_title (title),
    KEY idx_wiki_pdfs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
