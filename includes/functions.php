<?php

/** Maskiert HTML-Sonderzeichen und schuetzt damit vor XSS. */
function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Liest einen POST-Wert als Text ohne umgebende Leerzeichen. */
function postText(string $key): string
{
    return trim($_POST[$key] ?? '');
}

function getId(string $key): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : 0;
}

function postId(string $key): int
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : 0;
}

/** Vereinheitlicht eine E-Mail-Adresse vor dem Speichern oder Suchen. */
function normalizeEmail(string $email): string
{
    return mb_strtolower(trim($email));
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']))
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Gibt das versteckte Formularfeld aus, das jedes POST-Formular braucht. */
function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Bricht die Anfrage ab, wenn das Formular nicht aus dieser Session stammt.
 * hash_equals vergleicht zeitkonstant und verhindert so Timing-Angriffe.
 */
function checkCsrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!$submittedToken || !$sessionToken || !hash_equals($sessionToken, $submittedToken))
    {
        abortPage(403, 'Das Sicherheitstoken des Formulars ist ungueltig. Bitte lade die Seite neu.');
    }
}

/** Merkt eine kurze Meldung fuer die Seite nach der naechsten Weiterleitung vor. */
function setFlash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

/** Gibt die vorgemerkte Meldung einmalig aus. */
function showFlash(): void
{
    if (empty($_SESSION['flash']))
    {
        return;
    }

    $message = $_SESSION['flash']['message'];
    $type = $_SESSION['flash']['type'];

    echo '<div class="flash flash-' . e($type) . '">' . e($message) . '</div>';

    unset($_SESSION['flash']);
}

/** Merkt Formulareingaben fuer den Fall eines Fehlers vor. Passwoerter gehoeren hier nicht hinein. */
function setOldInput(array $values): void
{
    $_SESSION['old_input'] = $values;
}

function oldInput(string $key): string
{
    return (string) ($_SESSION['old_input'][$key] ?? '');
}

/** Wird vom Footer aufgerufen, sobald die Formulare der Seite gerendert sind. */
function clearOldInput(): void
{
    unset($_SESSION['old_input']);
}

function formatDate(?string $date): string
{
    if (!$date)
    {
        return '';
    }

    return date('d.m.Y H:i', strtotime($date));
}

/**
 * Zeigt eine einheitliche Fehlerseite und beendet die Anfrage.
 * Alle Zugriffs- und Nicht-gefunden-Faelle laufen hierueber, damit sie gleich aussehen.
 */
function abortPage(int $statusCode, string $message): void
{
    http_response_code($statusCode);

    $pageTitle = 'Fehler ' . $statusCode;
    require __DIR__ . '/header.php';
    ?>
    <section class="card">
        <h1>Fehler <?= (int) $statusCode ?></h1>
        <p><?= e($message) ?></p>
        <a class="button" href="index.php">Zur Startseite</a>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/**
 * Berechnet den Fortschritt eines Projekts aus den erledigten Aufgaben.
 * Rein rechnerisch gehalten, damit die Funktion ohne DB testbar ist.
 */
function calculateProgress(int $totalTasks, int $doneTasks): int
{
    if ($totalTasks <= 0)
    {
        return 0;
    }

    return (int) round($doneTasks / $totalTasks * 100);
}

/** Liest die Aufgabenzahlen eines Projekts und liefert Gesamt, Erledigt und Prozent. */
function projectProgress(int $projectId): array
{
    $sql = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN status = 'erledigt' THEN 1 ELSE 0 END), 0) AS done
        FROM tasks
        WHERE project_id = :project_id
    ";

    $statement = getDb()->prepare($sql);
    $statement->execute(['project_id' => $projectId]);
    $row = $statement->fetch();

    $total = (int) $row['total'];
    $done = (int) $row['done'];

    return [
        'total' => $total,
        'done' => $done,
        'percent' => calculateProgress($total, $done)
    ];
}

/** Gibt den Status einer Aufgabe menschenlesbar aus. */
function taskStatusLabel(string $status): string
{
    return match ($status)
    {
        'offen' => 'Offen',
        'in_bearbeitung' => 'In Bearbeitung',
        'erledigt' => 'Erledigt',
        default => $status,
    };
}

/** CSS-Klassenkuerzel fuer die farbige Status-Markierung. */
function taskStatusClass(string $status): string
{
    return match ($status)
    {
        'offen' => 'status-open',
        'in_bearbeitung' => 'status-progress',
        'erledigt' => 'status-done',
        default => '',
    };
}

function isValidTaskStatus(string $status): bool
{
    return in_array($status, TASK_STATUSES, true);
}
