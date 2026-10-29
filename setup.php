<?php
/**
 * Einmaliges Einrichtungsskript: legt das Datenbankschema an und befuellt es
 * mit Demodaten. Aufruf im Browser: http://localhost/projektmanager/setup.php
 *
 * Mit ?reset=1 wird die bestehende Datenbank vorher vollstaendig geleert.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDb();
$reset = isset($_GET['reset']) && $_GET['reset'] === '1';

if ($reset)
{
    $db->exec('DROP TABLE IF EXISTS tasks');
    $db->exec('DROP TABLE IF EXISTS project_members');
    $db->exec('DROP TABLE IF EXISTS projects');
    $db->exec('DROP TABLE IF EXISTS users');
    $db->exec('DROP TABLE IF EXISTS roles');
}

// Tabellen anlegen - IF NOT EXISTS, damit ein zweiter Aufruf nicht scheitert.

$db->exec("
    CREATE TABLE IF NOT EXISTS roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT,
        name    TEXT NOT NULL UNIQUE
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        user_id       INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT NOT NULL UNIQUE,
        email         TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role_id       INTEGER NOT NULL,
        is_active     INTEGER NOT NULL DEFAULT 1,
        tenant_id     INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (role_id) REFERENCES roles(role_id)
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS projects (
        project_id  INTEGER PRIMARY KEY AUTOINCREMENT,
        name        TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        status      TEXT NOT NULL DEFAULT 'aktiv',
        created_by  INTEGER NOT NULL,
        tenant_id   INTEGER NOT NULL DEFAULT 1,
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (created_by) REFERENCES users(user_id)
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS project_members (
        project_id INTEGER NOT NULL,
        user_id    INTEGER NOT NULL,
        PRIMARY KEY (project_id, user_id),
        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE CASCADE
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS tasks (
        task_id     INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id  INTEGER NOT NULL,
        title       TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        status      TEXT NOT NULL DEFAULT 'offen',
        assigned_to INTEGER,
        created_by  INTEGER NOT NULL,
        created_at  TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (project_id)  REFERENCES projects(project_id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(user_id),
        FOREIGN KEY (created_by)  REFERENCES users(user_id)
    )
");

// Demodaten einspielen, aber nur wenn die Datenbank noch leer ist.

$alreadySeeded = (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn() > 0;

if (!$alreadySeeded)
{
    // Rollen
    $insertRole = $db->prepare('INSERT INTO roles (name) VALUES (:name)');
    foreach (ROLE_NAMES as $roleName)
    {
        $insertRole->execute(['name' => $roleName]);
    }

    $roleIds = [];
    foreach ($db->query('SELECT role_id, name FROM roles') as $row)
    {
        $roleIds[$row['name']] = (int) $row['role_id'];
    }

    // Demo-Benutzer (Passwoerter nur fuer die lokale Demo)
    $insertUser = $db->prepare("
        INSERT INTO users (username, email, password_hash, role_id)
        VALUES (:username, :email, :hash, :role_id)
    ");

    $demoUsers = [
        ['admin',       'admin@example.com',       'admin123',  'Administrator'],
        ['leiterin',    'leiterin@example.com',    'leiter123', 'Projektleiter'],
        ['m.schmidt',   'schmidt@example.com',     'mita123',   'Mitarbeiter'],
        ['a.yilmaz',    'yilmaz@example.com',      'mita123',   'Mitarbeiter'],
    ];

    $userIds = [];
    foreach ($demoUsers as [$username, $email, $password, $role])
    {
        $insertUser->execute([
            'username' => $username,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $roleIds[$role],
        ]);
        $userIds[$username] = (int) $db->lastInsertId();
    }

    // Projekte (angelegt von der Projektleiterin)
    $insertProject = $db->prepare("
        INSERT INTO projects (name, description, status, created_by)
        VALUES (:name, :description, :status, :created_by)
    ");

    $insertProject->execute([
        'name' => 'Website-Relaunch Kunde A',
        'description' => 'Neugestaltung des Kundenportals inklusive Responsive Design.',
        'status' => 'aktiv',
        'created_by' => $userIds['leiterin'],
    ]);
    $projectA = (int) $db->lastInsertId();

    $insertProject->execute([
        'name' => 'Migration Warenwirtschaft',
        'description' => 'Ueberfuehrung der Altdaten in das neue ERP-System.',
        'status' => 'aktiv',
        'created_by' => $userIds['leiterin'],
    ]);
    $projectB = (int) $db->lastInsertId();

    // Mitgliederzuordnung
    $insertMember = $db->prepare(
        'INSERT INTO project_members (project_id, user_id) VALUES (:p, :u)'
    );
    $insertMember->execute(['p' => $projectA, 'u' => $userIds['m.schmidt']]);
    $insertMember->execute(['p' => $projectA, 'u' => $userIds['a.yilmaz']]);
    $insertMember->execute(['p' => $projectB, 'u' => $userIds['m.schmidt']]);

    // Aufgaben mit verschiedenen Status, damit der Fortschritt sichtbar wird
    $insertTask = $db->prepare("
        INSERT INTO tasks (project_id, title, description, status, assigned_to, created_by)
        VALUES (:project_id, :title, :description, :status, :assigned_to, :created_by)
    ");

    $tasks = [
        [$projectA, 'Anforderungen aufnehmen', 'Workshops mit dem Kunden durchfuehren.', 'erledigt',       $userIds['m.schmidt']],
        [$projectA, 'Wireframes erstellen',    'Entwuerfe fuer Start- und Detailseite.', 'erledigt',       $userIds['a.yilmaz']],
        [$projectA, 'Frontend umsetzen',       'HTML/CSS auf Basis der Wireframes.',     'in_bearbeitung', $userIds['m.schmidt']],
        [$projectA, 'Testphase',               'Cross-Browser-Tests und Abnahme.',       'offen',          null],
        [$projectB, 'Datenanalyse Altsystem',  'Datenstrukturen des Altsystems sichten.', 'erledigt',      $userIds['m.schmidt']],
        [$projectB, 'Mapping-Konzept',         'Feldzuordnung alt zu neu definieren.',    'offen',         null],
    ];

    foreach ($tasks as [$pid, $title, $desc, $status, $assignee])
    {
        $insertTask->execute([
            'project_id' => $pid,
            'title' => $title,
            'description' => $desc,
            'status' => $status,
            'assigned_to' => $assignee,
            'created_by' => $userIds['leiterin'],
        ]);
    }
}

// Kleine Erfolgsseite im Browser ausgeben.

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Einrichtung - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container">
    <section class="card">
        <h1>Einrichtung abgeschlossen</h1>
        <?php if ($alreadySeeded && !$reset): ?>
            <p>Die Datenbank war bereits vorhanden. Das Schema wurde geprueft, es wurden keine
               Demodaten erneut angelegt.</p>
            <p>Mit <a href="setup.php?reset=1">setup.php?reset=1</a> kannst du die Datenbank
               zuruecksetzen und neu befuellen.</p>
        <?php else: ?>
            <p>Datenbankschema und Demodaten wurden angelegt.</p>
            <h2>Demo-Zugaenge</h2>
            <table class="data-table">
                <thead><tr><th>Rolle</th><th>Benutzername</th><th>Passwort</th></tr></thead>
                <tbody>
                    <tr><td>Administrator</td><td>admin</td><td>admin123</td></tr>
                    <tr><td>Projektleiterin</td><td>leiterin</td><td>leiter123</td></tr>
                    <tr><td>Mitarbeiter</td><td>m.schmidt</td><td>mita123</td></tr>
                    <tr><td>Mitarbeiter</td><td>a.yilmaz</td><td>mita123</td></tr>
                </tbody>
            </table>
        <?php endif; ?>
        <p><a class="button" href="login.php">Zur Anmeldung</a></p>
    </section>
</main>
</body>
</html>
