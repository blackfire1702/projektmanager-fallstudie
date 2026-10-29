<?php
require_once __DIR__ . '/includes/auth.php';

requireProjectManager();

$db = getDb();
$me = currentUser();

$projectId = getId('id');
$project = null;

// Bearbeitungsmodus: bestehendes Projekt laden und Rechte pruefen.
if ($projectId > 0)
{
    $statement = $db->prepare('SELECT * FROM projects WHERE project_id = :id');
    $statement->execute(['id' => $projectId]);
    $project = $statement->fetch();

    if (!$project)
    {
        abortPage(404, 'Das Projekt wurde nicht gefunden.');
    }
    if (!canModifyProject($project))
    {
        abortPage(403, 'Du darfst dieses Projekt nicht bearbeiten.');
    }
}

if (isPost())
{
    checkCsrf();

    $name = postText('name');
    $description = postText('description');

    if ($name === '')
    {
        setFlash('Der Projektname darf nicht leer sein.', 'error');
        setOldInput(['name' => $name, 'description' => $description]);
        redirect($projectId > 0 ? 'project_form.php?id=' . $projectId : 'project_form.php');
    }

    if ($project)
    {
        $update = $db->prepare('UPDATE projects SET name = :n, description = :d WHERE project_id = :id');
        $update->execute(['n' => $name, 'd' => $description, 'id' => $projectId]);
        setFlash('Projekt wurde aktualisiert.', 'success');
        redirect('project.php?id=' . $projectId);
    }
    else
    {
        $insert = $db->prepare("
            INSERT INTO projects (name, description, created_by)
            VALUES (:n, :d, :by)
        ");
        $insert->execute(['n' => $name, 'd' => $description, 'by' => (int) $me['user_id']]);
        $newId = (int) $db->lastInsertId();
        setFlash('Projekt wurde angelegt.', 'success');
        redirect('project.php?id=' . $newId);
    }
}

$pageTitle = $project ? 'Projekt bearbeiten' : 'Neues Projekt';
$nameValue = $project ? $project['name'] : oldInput('name');
$descValue = $project ? $project['description'] : oldInput('description');

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= $project ? 'Projekt bearbeiten' : 'Neues Projekt' ?></h1>
</div>

<section class="card">
    <form method="post" action="<?= $project ? 'project_form.php?id=' . (int) $projectId : 'project_form.php' ?>">
        <?= csrfInput() ?>
        <div class="form-group">
            <label for="name">Projektname</label>
            <input type="text" id="name" name="name" value="<?= e($nameValue) ?>" autofocus required>
        </div>
        <div class="form-group">
            <label for="description">Beschreibung</label>
            <textarea id="description" name="description"><?= e($descValue) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="button"><?= $project ? 'Speichern' : 'Projekt anlegen' ?></button>
            <a class="button button-secondary" href="<?= $project ? 'project.php?id=' . (int) $projectId : 'index.php' ?>">Abbrechen</a>
        </div>
    </form>
</section>
<?php
require __DIR__ . '/includes/footer.php';
