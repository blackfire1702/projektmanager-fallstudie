<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

if (!isPost())
{
    redirect('index.php');
}

checkCsrf();

$db = getDb();
$taskId = postId('task_id');
$status = postText('status');

$taskStmt = $db->prepare('SELECT * FROM tasks WHERE task_id = :id');
$taskStmt->execute(['id' => $taskId]);
$task = $taskStmt->fetch();

if (!$task)
{
    abortPage(404, 'Die Aufgabe wurde nicht gefunden.');
}

// Projekt laden und Arbeitsrechte pruefen.
$projectStmt = $db->prepare('SELECT * FROM projects WHERE project_id = :id');
$projectStmt->execute(['id' => (int) $task['project_id']]);
$project = $projectStmt->fetch();

if (!$project)
{
    abortPage(404, 'Das zugehoerige Projekt wurde nicht gefunden.');
}
if (!canWorkOnProject($project) || $project['status'] === 'archiviert')
{
    abortPage(403, 'Du darfst den Status dieser Aufgabe nicht aendern.');
}

if (!isValidTaskStatus($status))
{
    setFlash('Der gewaehlte Status ist ungueltig.', 'error');
    redirect('project.php?id=' . (int) $project['project_id']);
}

$update = $db->prepare("
    UPDATE tasks SET status = :s, updated_at = datetime('now') WHERE task_id = :id
");
$update->execute(['s' => $status, 'id' => $taskId]);

setFlash('Status der Aufgabe wurde auf "' . taskStatusLabel($status) . '" gesetzt.', 'success');
redirect('project.php?id=' . (int) $project['project_id']);
