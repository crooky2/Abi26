# Abi26 Voting & Management Website

This is a small PHP/MySQL website for voting and simple event management for our "Abi 26" celebration. It includes user accounts, an admin survey builder, and a voting UI with validation and clear feedback.

Deutsch folgt weiter unten.

---

## English

### Overview
- Tech stack: PHP 8+, MySQL (via PDO), Vanilla JS, CSS
- Where it runs: Typical XAMPP on Windows (htdocs), but works on any LAMP/WAMP/MAMP stack
- Main features:
	- Login/Registration with email + password (passwords are hashed)
	- Account page with inline profile edit (email/displayname), save and discard
	- Admin panel to create surveys with multiple question types
	- Survey list with search and a masonry-style layout
	- Voting with client- and server-side validation (all questions required)
	- Consistent success/error feedback using flash messages

### Project structure (top-level)
- `index.php` – Home page; shows surveys when logged in
- `account.php` – Account page; profile or login/register depending on session
- `db/` – PHP endpoints (login/register, create survey, vote, etc.) and DB connection
- `source/php/` – Templates (header, account, admin, surveys)
- `source/css/` – Stylesheets (general, account, index) aggregated via `stylesheet.css`

### Prerequisites
- PHP 8+ (works with 7.4+, but prefer 8+)
- MySQL 5.7+ (or MariaDB equivalent)
- Web server (Apache via XAMPP is fine)

### Quick setup (Windows + XAMPP)
1. Place the folder into `C:\xampp\htdocs` (e.g., `C:\xampp\htdocs\abi26`).
2. Create a database (e.g., `abi26testdb`).
3. Configure database credentials in `db/db.php`:
	 - Host, DB name, username, password
4. Create the tables (see minimal schema below).
5. Start Apache and MySQL in XAMPP.
6. Visit `http://localhost/abi26/` to use the app.

### Minimal database schema (MySQL)
This schema matches how the code uses the database. Adjust table/column types to your preferences.

```sql
CREATE TABLE accounts (
	id INT AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	displayname VARCHAR(60) NOT NULL,
	admin TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE surveys (
	id INT AUTO_INCREMENT PRIMARY KEY,
	account_id INT NOT NULL,
	title VARCHAR(255) NOT NULL,
	description TEXT NULL,
	expires_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX (account_id),
	CONSTRAINT fk_surveys_account FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE survey_questions (
	id INT AUTO_INCREMENT PRIMARY KEY,
	survey_id INT NOT NULL,
	question_text VARCHAR(500) NOT NULL,
	question_type VARCHAR(20) NOT NULL, -- 'text' | 'single' | 'multiple' | 'number'
	options TEXT NULL,                  -- JSON string of options for choice questions
	INDEX (survey_id),
	CONSTRAINT fk_questions_survey FOREIGN KEY (survey_id) REFERENCES surveys(id)
);

CREATE TABLE survey_responses (
	id INT AUTO_INCREMENT PRIMARY KEY,
	survey_id INT NOT NULL,
	account_id INT NULL, -- kept NULL-able to allow future flexibility
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX (survey_id),
	INDEX (account_id),
	CONSTRAINT fk_responses_survey FOREIGN KEY (survey_id) REFERENCES surveys(id),
	CONSTRAINT fk_responses_account FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE survey_answers (
	id INT AUTO_INCREMENT PRIMARY KEY,
	response_id INT NOT NULL,
	question_id INT NOT NULL,
	answer_text TEXT NOT NULL,
	INDEX (response_id),
	INDEX (question_id),
	CONSTRAINT fk_answers_response FOREIGN KEY (response_id) REFERENCES survey_responses(id),
	CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES survey_questions(id)
);
```

### How to use
1. Create or sign in to an account at `/account.php`.
2. To grant admin rights, set `admin = 1` for your user in the `accounts` table.
3. As an admin, use the admin panel on the account page to create surveys:
	 - Add questions of type: Text, Single choice, Multiple choice, or Number
	 - Reorder, duplicate, or remove questions
	 - Enter options for Single/Multiple choice via the options editor
4. On the home page `/`, search and vote on active surveys.
5. Every question must be answered before submitting; you’ll get inline feedback otherwise.

### Configuration notes
- DB connection is defined in `db/db.php`.
- Sessions are used for auth and flash messages.
- Icons: Google Material Icons are loaded in the account page for the inline edit UI.

### Security notes (basic)
- Passwords are hashed using PHP’s `password_hash`.
- There is no CSRF protection implemented. For production use, add CSRF tokens to forms.
- Input validation exists on both client and server, but you should still harden inputs further if you expose this publicly.

### License
Internal project for the Abi26 celebration. Choose and add a license if you plan to open-source or distribute.

---

## Deutsch

### Überblick
- Tech-Stack: PHP 8+, MySQL (PDO), Vanilla JS, CSS
- Umgebung: z. B. XAMPP auf Windows (htdocs), funktioniert aber auch auf anderen LAMP/WAMP/MAMP-Stacks
- Hauptfunktionen:
	- Login/Registrierung mit E-Mail + Passwort (Passwörter werden gehasht)
	- Account-Seite mit Inline-Profilbearbeitung (E-Mail/Anzeigename), Speichern und Verwerfen
	- Admin-Panel zum Erstellen von Umfragen mit verschiedenen Fragetypen
	- Umfragenliste mit Suche und Masonry-Layout
	- Voting mit Client- und Server-Validierung (alle Fragen müssen beantwortet werden)
	- Einheitliche Erfolg-/Fehlerhinweise per Flash-Meldungen

### Projektstruktur (Top-Level)
- `index.php` – Startseite; zeigt Umfragen nach Login
- `account.php` – Account-Seite; Profil oder Anmelden/Registrieren je nach Session
- `db/` – PHP-Endpunkte (Login/Registrierung, Umfrage erstellen, Voting, etc.) und DB-Verbindung
- `source/php/` – Templates (Header, Account, Admin, Umfragen)
- `source/css/` – Stylesheets (global, Account, Index) gebündelt über `stylesheet.css`

### Voraussetzungen
- PHP 8+ (funktioniert auch mit 7.4+, empfohlen 8+)
- MySQL 5.7+ (oder MariaDB)
- Webserver (Apache über XAMPP ist okay)

### Schnelleinrichtung (Windows + XAMPP)
1. Projektordner nach `C:\xampp\htdocs` kopieren (z. B. `C:\xampp\htdocs\abi26`).
2. Datenbank erstellen (z. B. `abi26testdb`).
3. Zugangsdaten in `db/db.php` anpassen:
	 - Host, DB-Name, Benutzername, Passwort
4. Tabellen gemäß minimalem Schema (unten) anlegen.
5. Apache und MySQL in XAMPP starten.
6. `http://localhost/abi26/` im Browser öffnen.

### Minimales Datenbankschema (MySQL)
Siehe SQL-Block oben im englischen Teil; er entspricht der tatsächlichen Nutzung im Code. Typen/Constraints können bei Bedarf angepasst werden.

### Nutzung
1. Auf `/account.php` anmelden oder registrieren.
2. Admin-Rechte vergeben, indem `admin = 1` in der Tabelle `accounts` für den gewünschten Benutzer gesetzt wird.
3. Als Admin im Admin-Panel (auf der Account-Seite) Umfragen erstellen:
	 - Fragetypen: Text, Einzelauswahl, Mehrfachauswahl, Zahl
	 - Fragen verschieben, duplizieren, entfernen
	 - Optionen für Auswahlfragen komfortabel im Options-Editor pflegen
4. Auf der Startseite `/` nach Umfragen suchen und abstimmen.
5. Alle Fragen müssen beantwortet werden; bei fehlenden Antworten erscheint ein deutlicher Hinweis.

### Konfiguration
- DB-Verbindung in `db/db.php`.
- Sessions für Authentifizierung und Flash-Meldungen.
- Icons: Google Material Icons werden u. a. für die Inline-Bearbeitung geladen.

### Sicherheit (Kurzfassung)
- Passwörter werden mit `password_hash` gehasht.
- Es ist kein CSRF-Schutz eingebaut. Für produktive Nutzung bitte CSRF-Tokens ergänzen.
- Client- und Server-Validierung existieren, dennoch sollten Eingaben weiter gehärtet werden, falls öffentlich zugänglich.

### Lizenz
Internes Projekt für die Abi26-Feier. Bitte eine Lizenz hinzufügen, wenn das Projekt veröffentlicht oder verteilt werden soll.

test
