<?php
require_once __DIR__ . '/includes/auth.php';

requireProjectManager();

$db = getDb();
$projectId = getId('id');

$statement = $db->prepare('SELECT * FROM projects WHERE project_id = :id');
$statement->execute(['id' => $projectId]);
$project = $statement->fetch();

if (!$project)
{
    abortPage(404, 'Das Projekt wurde nicht gefunden.');
}
if (!canModifyProject($project))
{
    abortPage(403, 'Du darfst die Mitglieder dieses Projekts nicht verwalten.');
}

if (isPost())
{
    checkCsrf();
    $action = postText('action');
    $userId = postId('user_id');

    if ($action === 'add' && $userId > 0)
    {
        // INSERT OR IGNORE verhindert doppelte Zuordnungen (Primaerschluessel).
        $insert = $db->prepare(
            'INSERT OR IGNORE INTO project_members (project_id, user_id) VALUES (:p, :u)'
        );
        $insert->execute(['p' => $projectId, 'u' => $userId]);
        setFlash('Mitarbeitende:r wurde dem Projekt zugeordnet.', 'success');
    }
    elseif ($action === 'remove' && $userId > 0)
    {
        $delete = $db->prepare(
            'DELETE FROM project_members WHERE project_id = :p AND user_id = :u'
        );
        $delete->execute(['p' => $projectId, 'u' => $userId]);
        setFlash('Zuordnung wurde entfernt.', 'success');
    }

    redirect('project_members.php?id=' . $projectId);
}

// Aktuelle Mitglieder
$members = $db->prepare("
    SELECT u.user_id, u.username, u.email, r.name AS role_name
    FROM project_members pm
    JOIN users u ON u.user_id = pm.user_id
    JOIN roles r ON r.role_id = u.role_id
    WHERE pm.project_id = :p
    ORDER BY u.username
");
$members->execute(['p' => $projectId]);
$members = $members->fetchAll();

// Zuordenbare Nutzer: aktive Konten, die noch nicht Mitglied sind.
$candidates = $db->prepare("
    SELECT u.user_id, u.username, r.name AS role_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.is_active = 1
      AND u.user_id NOT IN (
          SELECT user_id FROM project_members WHERE project_id = :p
      )
    ORDER BY u.username
");
$candidates->execute(['p' => $projectId]);
$candidates = $candidates->fetchAll();

$pageTitle = 'Mitarbeitende - ' . $project['name'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Mitarbeitende zuordnen</h1>
    <a class="button button-secondary" href="project.php?id=<?= (int) $projectId ?>">Zurueck zum Projekt</a>
</div>
<p class="muted">Projekt: <strong><?= e($project['name']) ?></strong></p>

<section class="card">
    <h2>Zugeordnete Mitarbeitende</h2>
    <?php if (!$members): ?>
        <p class="muted">Diesem Projekt sind noch keine Mitarbeitenden zugeordnet.</p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Benutzer</th><th>E-Mail</th><th>Rolle</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= e($m['username']) ?></td>
                    <td><?= e($m['email']) ?></td>
                    <td><?= e($m['role_name']) ?></td>
                    <td>
                        <form method="post" action="project_members.php?id=<?= (int) $projectId ?>" class="inline-form">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                            <button type="submit" class="button button-small button-danger">Entfernen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Mitarbeitende hinzufuegen</h2>
    <?php if (!$candidates): ?>
        <p class="muted">Es gibt keine weiteren Nutzer, die zugeordnet werden koennen.</p>
    <?php else: ?>
        <form method="post" action="project_members.php?id=<?= (int) $projectId ?>">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="user_id">Benutzer auswaehlen</label>
                <select id="user_id" name="user_id">
                    <?php foreach ($candidates as $c): ?>
                        <option value="<?= (int) $c['user_id'] ?>">
                            <?= e($c['username']) ?> (<?= e($c['role_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">Zuordnen</button>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php
require __DIR__ . '/includes/footer.php';
