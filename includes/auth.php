<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Liefert den angemeldeten Nutzer oder null, wenn niemand eingeloggt ist.
 * Das Ergebnis wird pro Seitenaufruf zwischengespeichert, damit mehrfache
 * Rechtepruefungen nicht jedes Mal die Datenbank befragen.
 */
function currentUser(): ?array
{
    static $cachedUser = null;
    static $cachedForId = null;

    $userId = $_SESSION['user_id'] ?? null;

    if ($userId === null)
    {
        return null;
    }

    if ($cachedForId === $userId)
    {
        return $cachedUser;
    }

    $sql = "
        SELECT users.user_id,
               users.username,
               users.email,
               users.is_active,
               users.tenant_id,
               roles.name AS role_name
        FROM users
        JOIN roles ON roles.role_id = users.role_id
        WHERE users.user_id = :user_id
    ";

    $statement = getDb()->prepare($sql);
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    // Wer waehrend einer laufenden Session deaktiviert wird, fliegt beim naechsten Aufruf raus.
    if ($user && (int) $user['is_active'] === 0)
    {
        logoutUser();
        setFlash('Dieses Benutzerkonto wurde deaktiviert.', 'error');
        redirect('login.php');
    }

    $cachedForId = $userId;
    $cachedUser = $user ?: null;

    return $cachedUser;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function hasRole(string $roleName): bool
{
    $user = currentUser();

    return $user !== null && $user['role_name'] === $roleName;
}

function isAdmin(): bool
{
    return hasRole('Administrator');
}

/** Projekte anlegen/bearbeiten/archivieren duerfen Projektleiter und Administratoren. */
function canManageProjects(): bool
{
    return hasRole('Projektleiter') || isAdmin();
}

/** Schuetzt eine Seite, die nur angemeldeten Nutzern offensteht. */
function requireLogin(): void
{
    if (!isLoggedIn())
    {
        setFlash('Fuer diese Aktion ist eine Anmeldung erforderlich.', 'error');
        redirect('login.php');
    }
}

/** Schuetzt eine Seite, die nur Administratoren offensteht. */
function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin())
    {
        abortPage(403, 'Dieser Bereich ist Administratoren vorbehalten.');
    }
}

/** Schuetzt eine Seite, die nur Projektleiter oder Administratoren nutzen duerfen. */
function requireProjectManager(): void
{
    requireLogin();

    if (!canManageProjects())
    {
        abortPage(403, 'Fuer diese Aktion werden Projektleiter-Rechte benoetigt.');
    }
}

function loginUser(array $user): void
{
    // Neue Session-ID nach dem Login schuetzt gegen Session-Fixation.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
}

function logoutUser(): void
{
    $_SESSION = [];

    // Das Session-Cookie wird zusaetzlich mit abgelaufenem Datum ueberschrieben,
    // damit im Browser kein verwaister Verweis zurueckbleibt.
    if (ini_get('session.use_cookies'))
    {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/** Ist der aktuelle Nutzer Mitglied des Projekts? */
function isProjectMember(int $projectId): bool
{
    $user = currentUser();

    if (!$user)
    {
        return false;
    }

    $statement = getDb()->prepare(
        'SELECT 1 FROM project_members WHERE project_id = :p AND user_id = :u'
    );
    $statement->execute(['p' => $projectId, 'u' => (int) $user['user_id']]);

    return (bool) $statement->fetchColumn();
}

/**
 * Darf der aktuelle Nutzer dieses Projekt sehen?
 * Administratoren sehen alles; sonst nur eigene (angelegte) oder zugeordnete Projekte.
 * Setzt die User Story "nur die Projekte sehen, fuer die ich berechtigt bin" um.
 */
function canSeeProject(array $project): bool
{
    $user = currentUser();

    if (!$user)
    {
        return false;
    }

    if (isAdmin())
    {
        return true;
    }

    if ((int) $project['created_by'] === (int) $user['user_id'])
    {
        return true;
    }

    return isProjectMember((int) $project['project_id']);
}

/**
 * Projekt bearbeiten/archivieren darf der anlegende Projektleiter oder ein Administrator.
 */
function canModifyProject(array $project): bool
{
    $user = currentUser();

    if (!$user)
    {
        return false;
    }

    if (isAdmin())
    {
        return true;
    }

    return canManageProjects() && (int) $project['created_by'] === (int) $user['user_id'];
}

/**
 * Aufgaben in einem Projekt anlegen/bearbeiten darf, wer das Projekt verwalten
 * darf oder ihm als Mitarbeiter zugeordnet ist.
 */
function canWorkOnProject(array $project): bool
{
    return canModifyProject($project) || isProjectMember((int) $project['project_id']);
}
