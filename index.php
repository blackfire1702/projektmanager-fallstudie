<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db = getDb();
$me = currentUser();

// Sichtbarkeit: Administratoren sehen alle Projekte, alle anderen nur die
// selbst angelegten oder die, denen sie als Mitarbeitende zugeordnet sind.
if (isAdmin())
{
    $statement = $db->query("
        SELECT * FROM projects
        ORDER BY (status = 'archiviert'), name
    ");
    $projects = $statement->fetchAll();
}
else
{
    $statement = $db->prepare("
        SELECT p.*
        FROM projects p
        WHERE p.created_by = :me
           OR EXISTS (
               SELECT 1 FROM project_members pm
               WHERE pm.project_id = p.project_id AND pm.user_id = :me
           )
        ORDER BY (p.status = 'archiviert'), p.name
    ");
    $statement->execute(['me' => (int) $me['user_id']]);
    $projects = $statement->fetchAll();
}

$pageTitle = 'Projekte';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Projekte</h1>
    <?php if (canManageProjects()): ?>
        <a class="button" href="project_form.php">Neues Projekt</a>
    <?php endif; ?>
</div>

<?php if (!$projects): ?>
    <section class="card">
        <p class="muted">Es sind noch keine Projekte vorhanden, fuer die du berechtigt bist.</p>
    </section>
<?php else: ?>
    <div class="project-grid">
        <?php foreach ($projects as $project): ?>
            <?php $progress = projectProgress((int) $project['project_id']); ?>
            <section class="card project-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <h3><a href="project.php?id=<?= (int) $project['project_id'] ?>"><?= e($project['name']) ?></a></h3>
                    <?php if ($project['status'] === 'archiviert'): ?>
                        <span class="status badge-archived">archiviert</span>
                    <?php endif; ?>
                </div>
                <p class="desc"><?= e($project['description']) ?: '<span class="muted">Keine Beschreibung.</span>' ?></p>
                <div class="progress">
                    <div class="progress-bar"><span style="width: <?= (int) $progress['percent'] ?>%;"></span></div>
                    <div class="progress-label">
                        <?= (int) $progress['percent'] ?>% &middot;
                        <?= (int) $progress['done'] ?> von <?= (int) $progress['total'] ?> Aufgaben erledigt
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
require __DIR__ . '/includes/footer.php';
