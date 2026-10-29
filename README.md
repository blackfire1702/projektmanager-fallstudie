# ProjektManager

Webbasierte Anwendung zur Verwaltung von **Projekten, Aufgaben und Mitarbeitenden** für ein
mittelständisches IT-Dienstleistungsunternehmen. Der Fokus liegt auf einer wartbaren,
erweiterbaren und rollenbasiert abgesicherten Architektur.

Die Anwendung löst die bisherige Verwaltung per Excel ab: Projekte und Aufgaben werden zentral
erfasst, rollenbasiert abgesichert und der Projektfortschritt automatisch aus den erledigten
Aufgaben berechnet.

## Technologiestack

| Baustein        | Umsetzung                                             |
|-----------------|-------------------------------------------------------|
| Sprache         | PHP 8.2 (prozedural, gemeinsame Includes)             |
| Datenbank       | SQLite via PDO (serverlos, Datei-basiert)             |
| Frontend        | HTML5 + eigenes CSS (ohne Framework), responsiv       |
| Umgebung        | XAMPP (Apache)                                         |
| Tests           | eigener, abhängigkeitsfreier Test-Harness (PHP-CLI)   |

> Hinweis: Der Stack ist bewusst schlank gehalten. Ein Wechsel auf MySQL/MariaDB wäre dank PDO
> mit minimalem Aufwand möglich (nur `includes/db.php` und `setup.php` anpassen).

## Einrichtung

1. Projektordner unter `C:\xampp\htdocs\projektmanager` ablegen und **Apache** in XAMPP starten.
2. Einmalig die Datenbank anlegen und mit Demodaten befüllen:
   <http://localhost/projektmanager/setup.php>
3. Anmelden unter <http://localhost/projektmanager/login.php>.

### Demo-Zugänge

| Rolle           | Benutzername | Passwort   |
|-----------------|--------------|------------|
| Administrator   | `admin`      | `admin123` |
| Projektleiterin | `leiterin`   | `leiter123`|
| Mitarbeiter     | `m.schmidt`  | `mita123`  |
| Mitarbeiter     | `a.yilmaz`   | `mita123`  |

Mit <http://localhost/projektmanager/setup.php?reset=1> lässt sich die Datenbank zurücksetzen.

## Rollen & Rechte

- **Administrator:** Benutzerkonten anlegen, Rollen vergeben, Konten (de)aktivieren; sieht alle Projekte.
- **Projektleiter:in:** Projekte anlegen/bearbeiten/archivieren, Mitarbeitende zuordnen, Fortschritt einsehen.
- **Mitarbeiter:in:** Aufgaben in zugeordneten Projekten anlegen/bearbeiten, Status ändern.
- **Alle:** An-/Abmeldung; es werden **nur berechtigte Projekte** angezeigt.

## Abdeckung der User Stories

| # | User Story | Umsetzung |
|---|------------|-----------|
| 1 | Admin: Benutzerkonten & Rollen verwalten | `admin.php` |
| 2 | Projektleiter: Projekte anlegen/bearbeiten/archivieren | `project_form.php`, `project_archive.php` |
| 3 | Projektleiter: Mitarbeitende zuordnen | `project_members.php` |
| 4 | Mitarbeiter: Aufgaben erstellen/bearbeiten | `task_form.php` |
| 5 | Mitarbeiter: Aufgabenstatus ändern | `task_status.php`, `project.php` |
| 6 | Projektleiter: Fortschritt sehen | Fortschrittsbalken in `index.php` / `project.php` |
| 7 | Benutzer: an-/abmelden | `login.php`, `logout.php` |
| 8 | Benutzer: nur berechtigte Projekte sehen | `canSeeProject()` in `includes/auth.php` |

## Projektstruktur

```
projektmanager/
├── index.php              Dashboard (sichtbare Projekte + Fortschritt)
├── login.php / logout.php Authentifizierung
├── admin.php              Benutzerverwaltung (nur Administrator)
├── project.php            Projektdetail: Aufgaben, Status, Fortschritt
├── project_form.php       Projekt anlegen/bearbeiten
├── project_archive.php    Projekt archivieren/reaktivieren
├── project_members.php    Mitarbeitende zuordnen
├── task_form.php          Aufgabe anlegen/bearbeiten/löschen
├── task_status.php        Aufgabenstatus ändern
├── setup.php              Schema + Demodaten anlegen
├── includes/              config, db, auth, functions, header, footer
├── assets/css/style.css   Stylesheet
├── database/              SQLite-Datenbank (zur Laufzeit erzeugt)
└── tests/run_tests.php    Automatisierte Tests
```

## Tests ausführen

```bash
C:\xampp\php\php.exe tests\run_tests.php
```

Die Tests prüfen die fachliche Kernlogik (Fortschrittsberechnung, Status-Validierung,
XSS-Escaping, E-Mail-Normalisierung, Passwort-Hashing, CSRF-Token). Alle Tests sollten mit
`Fehlgeschlagen: 0` und Exit-Code `0` durchlaufen.

## Sicherheitsmerkmale

- Passwörter als `password_hash()`-Hash (bcrypt) gespeichert, Prüfung über `password_verify()`.
- **CSRF-Schutz** für alle POST-Formulare (Token je Session, zeitkonstanter Vergleich).
- **XSS-Schutz:** alle Ausgaben über `e()` (htmlspecialchars).
- **SQL-Injection-Schutz:** ausschließlich vorbereitete PDO-Statements.
- Rollenbasierte Autorisierung zentral in `includes/auth.php`.
- Session-Härtung: `httponly`, `SameSite=Lax`, `session_regenerate_id` nach dem Login.
