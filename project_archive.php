<?php
require_once __DIR__ . '/includes/auth.php';

requireProjectManager();

if (!isPost())
{
    redirect('index.php');
}

checkCsrf();

$db = getDb();
$projectId = postId('project_id');

$statement = $db->prepare('SELECT * FROM projects WHERE project_id = :id');
$statement->execute(['id' => $projectId]);
$project = $statement->fetch();

if (!$project)
{
    abortPage(404, 'Das Projekt wurde nicht gefunden.');
}
if (!canModifyProject($project))
{
    abortPage(403, 'Du darfst dieses Projekt nicht aendern.');
}

// Status zwischen aktiv und archiviert umschalten.
$newStatus = $project['status'] === 'archiviert' ? 'aktiv' : 'archiviert';
$update = $db->prepare('UPDATE projects SET status = :s WHERE project_id = :id');
$update->execute(['s' => $newStatus, 'id' => $projectId]);

setFlash(
    $newStatus === 'archiviert' ? 'Projekt wurde archiviert.' : 'Projekt wurde reaktiviert.',
    'success'
);
redirect('project.php?id=' . $projectId);
