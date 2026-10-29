<?php
require_once __DIR__ . '/auth.php';

$user = currentUser();
$title = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php"><?= e(APP_NAME) ?></a>

        <?php if ($user): ?>
            <nav class="nav">
                <a href="index.php">Projekte</a>
                <?php if (canManageProjects()): ?>
                    <a href="project_form.php">Neues Projekt</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <a href="admin.php">Benutzerverwaltung</a>
                <?php endif; ?>
            </nav>

            <div class="user-box">
                <span class="user-name"><?= e($user['username']) ?></span>
                <span class="role-badge"><?= e($user['role_name']) ?></span>
                <a class="button button-small" href="logout.php">Abmelden</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<main class="container">
    <?php showFlash(); ?>
