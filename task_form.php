<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db = getDb();

/** Laedt ein Projekt oder bricht mit Fehlerseite ab. */
function loadProjectOr404(PDO $db, int $projectId): array
{
    $statement = $db->prepare('SELECT * FROM projects WHERE project_id = :id');
    $statement->execute(['id' => $projectId]);
    $project = $statement->fetch();

    if (!$project)
    {
        abortPage(404, 'Das zugehoerige Projekt wurde nicht gefunden.');
    }

    return $project;
}

/** Stellt sicher, dass in diesem Projekt gearbeitet werden darf. */
function guardTaskAccess(array $project): void
{
    if (!canWorkOnProject($project))
    {
        abortPage(403, 'Du darfst in diesem Projekt keine Aufgaben bearbeiten.');
    }
    if ($project['status'] === 'archiviert')
    {
        abortPage(403, 'In archivierten Projekten koennen keine Aufgaben geaendert werden.');
    }
}

// Loeschen kommt als eigenes POST (action=delete) und wird gleich vorab erledigt.

if (isPost() && postText('action') === 'delete')
{
    checkCsrf();

    $taskId = postId('task_id');
    $taskStmt = $db->prepare('SELECT * FROM tasks WHERE task_id = :id');
    $taskStmt->execute(['id' => $taskId]);
    $task = $taskStmt->fetch();

    if (!$task)
    {
        abortPage(404, 'Die Aufgabe wurde nicht gefunden.');
    }

    $project = loadProjectOr404($db, (int) $task['project_id']);
    guardTaskAccess($project);

    $db->prepare('DELETE FROM tasks WHERE task_id = :id')->execute(['id' => $taskId]);
    setFlash('Aufgabe wurde geloescht.', 'success');
    redirect('project.php?id=' . (int) $project['project_id']);
}

// Ab hier geht es ums Bearbeiten oder Neuanlegen - zuerst klaeren, welcher Fall vorliegt.

$task = null;
$taskId = isPost() ? postId('task_id') : getId('id');

if ($taskId > 0)
{
    $taskStmt = $db->prepare('SELECT * FROM tasks WHERE task_id = :id');
    $taskStmt->execute(['id' => $taskId]);
    $task = $taskStmt->fetch();

    if (!$task)
    {
        abortPage(404, 'Die Aufgabe wurde nicht gefunden.');
    }

    $projectId = (int) $task['project_id'];
}
else
{
    $projectId = isPost() ? postId('project_id') : getId('project_id');
}

$project = loadProjectOr404($db, $projectId);
guardTaskAccess($project);

// Zuordenbare Personen: Mitglieder des Projekts.
$assignable = $db->prepare("
    SELECT u.user_id, u.username
    FROM project_members pm
    JOIN users u ON u.user_id = pm.user_id
    WHERE pm.project_id = :p
    ORDER BY u.username
");
$assignable->execute(['p' => $projectId]);
$assignable = $assignable->fetchAll();
$assignableIds = array_map(fn($u) => (int) $u['user_id'], $assignable);

// Speichern: erst validieren, dann je nach Fall aktualisieren oder neu anlegen.

if (isPost() && postText('action') !== 'delete')
{
    checkCsrf();

    $title = postText('title');
    $description = postText('description');
    $status = postText('status');
    $assignedTo = postId('assigned_to'); // 0 = nicht zugewiesen

    $errors = [];
    if ($title === '')
    {
        $errors[] = 'Der Titel der Aufgabe darf nicht leer sein.';
    }
    if (!isValidTaskStatus($status))
    {
        $errors[] = 'Der gewaehlte Status ist ungueltig.';
    }
    if ($assignedTo !== 0 && !in_array($assignedTo, $assignableIds, true))
    {
        $errors[] = 'Die Aufgabe kann nur einem Projektmitglied zugeordnet werden.';
    }

    if ($errors)
    {
        setFlash(implode(' ', $errors), 'error');
        setOldInput(['title' => $title, 'description' => $description, 'status' => $status]);
        redirect($task ? 'task_form.php?id=' . $taskId : 'task_form.php?project_id=' . $projectId);
    }

    $assignedValue = $assignedTo === 0 ? null : $assignedTo;

    if ($task)
    {
        $update = $db->prepare("
            UPDATE tasks
            SET title = :t, description = :d, status = :s, assigned_to = :a,
                updated_at = datetime('now')
            WHERE task_id = :id
        ");
        $update->execute([
            't' => $title, 'd' => $description, 's' => $status,
            'a' => $assignedValue, 'id' => $taskId,
        ]);
        setFlash('Aufgabe wurde aktualisiert.', 'success');
    }
    else
    {
        $insert = $db->prepare("
            INSERT INTO tasks (project_id, title, description, status, assigned_to, created_by)
            VALUES (:p, :t, :d, :s, :a, :by)
        ");
        $insert->execute([
            'p' => $projectId, 't' => $title, 'd' => $description, 's' => $status,
            'a' => $assignedValue, 'by' => (int) currentUser()['user_id'],
        ]);
        setFlash('Aufgabe wurde angelegt.', 'success');
    }

    redirect('project.php?id=' . $projectId);
}

$pageTitle = $task ? 'Aufgabe bearbeiten' : 'Neue Aufgabe';
$titleValue = $task ? $task['title'] : oldInput('title');
$descValue = $task ? $task['description'] : oldInput('description');
$statusValue = $task ? $task['status'] : (oldInput('status') ?: 'offen');
$assignedValue = $task ? (int) ($task['assigned_to'] ?? 0) : 0;

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= $task ? 'Aufgabe bearbeiten' : 'Neue Aufgabe' ?></h1>
</div>
<p class="muted">Projekt: <strong><?= e($project['name']) ?></strong></p>

<section class="card">
    <form method="post" action="<?= $task ? 'task_form.php?id=' . (int) $taskId : 'task_form.php?project_id=' . (int) $projectId ?>">
        <?= csrfInput() ?>
        <input type="hidden" name="task_id" value="<?= $task ? (int) $taskId : 0 ?>">
        <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">

        <div class="form-group">
            <label for="title">Titel</label>
            <input type="text" id="title" name="title" value="<?= e($titleValue) ?>" autofocus required>
        </div>
        <div class="form-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description"><?= e($descValue) ?></textarea>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (TASK_STATUSES as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= $statusValue === $statusOption ? 'selected' : '' ?>>
                        <?= e(taskStatusLabel($statusOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="assigned_to">Zugeordnet an</label>
            <select id="assigned_to" name="assigned_to">
                <option value="0">Nicht zugewiesen</option>
                <?php foreach ($assignable as $u): ?>
                    <option value="<?= (int) $u['user_id'] ?>" <?= $assignedValue === (int) $u['user_id'] ? 'selected' : '' ?>>
                        <?= e($u['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$assignable): ?>
                <p class="muted" style="font-size:0.85rem;margin-top:6px;">
                    Diesem Projekt sind noch keine Mitarbeitenden zugeordnet.
                </p>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="button"><?= $task ? 'Speichern' : 'Aufgabe anlegen' ?></button>
            <a class="button button-secondary" href="project.php?id=<?= (int) $projectId ?>">Abbrechen</a>
        </div>
    </form>
</section>
<?php
require __DIR__ . '/includes/footer.php';
