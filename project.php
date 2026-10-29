<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db = getDb();
$projectId = getId('id');

$statement = $db->prepare('SELECT * FROM projects WHERE project_id = :id');
$statement->execute(['id' => $projectId]);
$project = $statement->fetch();

if (!$project)
{
    abortPage(404, 'Das Projekt wurde nicht gefunden.');
}

// Zugriffsschutz: nur berechtigte Nutzer sehen das Projekt.
if (!canSeeProject($project))
{
    abortPage(403, 'Du bist fuer dieses Projekt nicht berechtigt.');
}

$canModify = canModifyProject($project);
$canWork = canWorkOnProject($project);
$isArchived = $project['status'] === 'archiviert';
$progress = projectProgress($projectId);

// Aufgaben inkl. Name der zugeordneten Person
$tasks = $db->prepare("
    SELECT t.*, u.username AS assignee_name
    FROM tasks t
    LEFT JOIN users u ON u.user_id = t.assigned_to
    WHERE t.project_id = :p
    ORDER BY
        CASE t.status WHEN 'offen' THEN 0 WHEN 'in_bearbeitung' THEN 1 ELSE 2 END,
        t.created_at
");
$tasks->execute(['p' => $projectId]);
$tasks = $tasks->fetchAll();

// Mitglieder (kompakt fuer die Seitenleiste)
$members = $db->prepare("
    SELECT u.username, r.name AS role_name
    FROM project_members pm
    JOIN users u ON u.user_id = pm.user_id
    JOIN roles r ON r.role_id = u.role_id
    WHERE pm.project_id = :p
    ORDER BY u.username
");
$members->execute(['p' => $projectId]);
$members = $members->fetchAll();

$pageTitle = $project['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>
        <?= e($project['name']) ?>
        <?php if ($isArchived): ?><span class="status badge-archived">archiviert</span><?php endif; ?>
    </h1>
    <div class="form-actions">
        <?php if ($canModify): ?>
            <a class="button button-secondary" href="project_form.php?id=<?= (int) $projectId ?>">Bearbeiten</a>
            <a class="button button-secondary" href="project_members.php?id=<?= (int) $projectId ?>">Mitarbeitende</a>
            <form method="post" action="project_archive.php" class="inline-form">
                <?= csrfInput() ?>
                <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
                <button type="submit" class="button button-secondary">
                    <?= $isArchived ? 'Reaktivieren' : 'Archivieren' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="card">
    <p><?= e($project['description']) ?: '<span class="muted">Keine Beschreibung.</span>' ?></p>
    <div class="progress">
        <div class="progress-bar"><span style="width: <?= (int) $progress['percent'] ?>%;"></span></div>
        <div class="progress-label">
            Fortschritt: <?= (int) $progress['percent'] ?>%
            (<?= (int) $progress['done'] ?> von <?= (int) $progress['total'] ?> Aufgaben erledigt)
        </div>
    </div>
    <?php if ($members): ?>
        <p class="muted" style="margin-top:12px;">
            Mitarbeitende:
            <?php foreach ($members as $i => $m): ?>
                <?= $i > 0 ? ', ' : '' ?><?= e($m['username']) ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</section>

<div class="page-head">
    <h2>Aufgaben</h2>
    <?php if ($canWork && !$isArchived): ?>
        <a class="button" href="task_form.php?project_id=<?= (int) $projectId ?>">Neue Aufgabe</a>
    <?php endif; ?>
</div>

<?php if (!$tasks): ?>
    <section class="card"><p class="muted">Dieses Projekt hat noch keine Aufgaben.</p></section>
<?php else: ?>
    <section class="card">
        <table class="data-table">
            <thead>
                <tr><th>Aufgabe</th><th>Zugeordnet</th><th>Status</th><th>Aktionen</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td>
                        <strong><?= e($task['title']) ?></strong>
                        <?php if ($task['description'] !== ''): ?>
                            <br><span class="muted"><?= e($task['description']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $task['assignee_name'] ? e($task['assignee_name']) : '<span class="muted">&mdash;</span>' ?></td>
                    <td>
                        <?php if ($canWork && !$isArchived): ?>
                            <form method="post" action="task_status.php" class="inline-form">
                                <?= csrfInput() ?>
                                <input type="hidden" name="task_id" value="<?= (int) $task['task_id'] ?>">
                                <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
                                <select name="status" onchange="this.form.submit()" class="<?= taskStatusClass($task['status']) ?>">
                                    <?php foreach (TASK_STATUSES as $statusOption): ?>
                                        <option value="<?= e($statusOption) ?>" <?= $task['status'] === $statusOption ? 'selected' : '' ?>>
                                            <?= e(taskStatusLabel($statusOption)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <span class="status <?= taskStatusClass($task['status']) ?>"><?= e(taskStatusLabel($task['status'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($canWork && !$isArchived): ?>
                            <a class="button button-small button-secondary" href="task_form.php?id=<?= (int) $task['task_id'] ?>">Bearbeiten</a>
                            <form method="post" action="task_form.php" class="inline-form"
                                  onsubmit="return confirm('Diese Aufgabe wirklich loeschen?');">
                                <?= csrfInput() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= (int) $task['task_id'] ?>">
                                <button type="submit" class="button button-small button-danger">Loeschen</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer.php';
