# Wiki-Medizininformatik
alles über Medizininformatik

## Admin-PDF-Upload

Den Projektordner nach `C:\xampp\htdocs\Wiki-Medizininformatik\` kopieren. `config.php` muss im Wiki-Ordner liegen (Vorlage: `config.example.php`). Apache und MySQL in XAMPP starten.

Die Tabelle `wiki_pdfs` wird beim ersten Öffnen von `admin/index.php` angelegt. Alternativ `database-pdf.sql` in phpMyAdmin in der Datenbank `medizininformatik` ausführen.

Danach als Admin anmelden, `admin/index.php` öffnen und ein PDF hochladen. Die Datei liegt in `uploads/pdf` und ist über `dokumente.php` erreichbar.
