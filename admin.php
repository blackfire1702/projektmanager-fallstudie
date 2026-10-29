<?php
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$db = getDb();
$me = currentUser();

// Rollen fuer die Auswahlfelder
$roles = $db->query('SELECT role_id, name FROM roles ORDER BY role_id')->fetchAll();

if (isPost())
{
    checkCsrf();
    $action = postText('action');

    if ($action === 'create')
    {
        $username = postText('username');
        $email = normalizeEmail(postText('email'));
        $password = $_POST['password'] ?? '';
        $roleId = postId('role_id');

        $errors = [];

        if ($username === '' || $email === '' || $password === '')
        {
            $errors[] = 'Benutzername, E-Mail und Passwort sind Pflichtfelder.';
        }
        if (mb_strlen($password) < 6)
        {
            $errors[] = 'Das Passwort muss mindestens 6 Zeichen lang sein.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            $errors[] = 'Die E-Mail-Adresse ist ungueltig.';
        }

        // Rolle muss existieren
        $validRole = false;
        foreach ($roles as $r)
        {
            if ((int) $r['role_id'] === $roleId)
            {
                $validRole = true;
                break;
            }
        }
        if (!$validRole)
        {
            $errors[] = 'Bitte eine gueltige Rolle waehlen.';
        }

        // Benutzername/E-Mail duerfen nicht doppelt sein
        if (!$errors)
        {
            $check = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e');
            $check->execute(['u' => $username, 'e' => $email]);
            if ((int) $check->fetchColumn() > 0)
            {
                $errors[] = 'Benutzername oder E-Mail wird bereits verwendet.';
            }
        }

        if ($errors)
        {
            setFlash(implode(' ', $errors), 'error');
            setOldInput(['username' => $username, 'email' => $email, 'role_id' => $roleId]);
        }
        else
        {
            $insert = $db->prepare("
                INSERT INTO users (username, email, password_hash, role_id)
                VALUES (:u, :e, :h, :r)
            ");
            $insert->execute([
                'u' => $username,
                'e' => $email,
                'h' => password_hash($password, PASSWORD_DEFAULT),
                'r' => $roleId,
            ]);
            setFlash('Benutzerkonto "' . $username . '" wurde angelegt.', 'success');
        }

        redirect('admin.php');
    }

    if ($action === 'set_role')
    {
        $userId = postId('user_id');
        $roleId = postId('role_id');

        // Die eigene Rolle laesst sich nicht aendern (Schutz vor Selbst-Aussperrung).
        if ($userId === (int) $me['user_id'])
        {
            setFlash('Die eigene Rolle kann nicht geaendert werden.', 'error');
        }
        else
        {
            $update = $db->prepare('UPDATE users SET role_id = :r WHERE user_id = :u');
            $update->execute(['r' => $roleId, 'u' => $userId]);
            setFlash('Rolle wurde aktualisiert.', 'success');
        }

        redirect('admin.php');
    }

    if ($action === 'toggle_active')
    {
        $userId = postId('user_id');

        if ($userId === (int) $me['user_id'])
        {
            setFlash('Das eigene Konto kann nicht deaktiviert werden.', 'error');
        }
        else
        {
            $update = $db->prepare('UPDATE users SET is_active = 1 - is_active WHERE user_id = :u');
            $update->execute(['u' => $userId]);
            setFlash('Der Kontostatus wurde geaendert.', 'success');
        }

        redirect('admin.php');
    }
}

// Benutzerliste
$users = $db->query("
    SELECT users.user_id, users.username, users.email, users.is_active,
           users.role_id, roles.name AS role_name
    FROM users
    JOIN roles ON roles.role_id = users.role_id
    ORDER BY users.username
")->fetchAll();

$pageTitle = 'Benutzerverwaltung';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Benutzerverwaltung</h1>
</div>

<section class="card">
    <h2>Neues Benutzerkonto</h2>
    <form method="post" action="admin.php">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" value="<?= e(oldInput('username')) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">E-Mail</label>
            <input type="email" id="email" name="email" value="<?= e(oldInput('email')) ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Passwort (mind. 6 Zeichen)</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="role_id">Rolle</label>
            <select id="role_id" name="role_id">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['role_id'] ?>"
                        <?= (oldInput('role_id') !== '' && (int) oldInput('role_id') === (int) $r['role_id']) ? 'selected' : '' ?>>
                        <?= e($r['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="button">Konto anlegen</button>
        </div>
    </form>
</section>

<section class="card">
    <h2>Benutzerkonten</h2>
    <table class="data-table">
        <thead>
            <tr><th>Benutzer</th><th>E-Mail</th><th>Rolle</th><th>Status</th><th>Aktionen</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['username']) ?><?= (int) $u['user_id'] === (int) $me['user_id'] ? ' <span class="muted">(du)</span>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <form method="post" action="admin.php" class="inline-form">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="set_role">
                        <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                        <select name="role_id" onchange="this.form.submit()"
                            <?= (int) $u['user_id'] === (int) $me['user_id'] ? 'disabled' : '' ?>>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['role_id'] ?>" <?= (int) $r['role_id'] === (int) $u['role_id'] ? 'selected' : '' ?>>
                                    <?= e($r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <?php if ((int) $u['is_active'] === 1): ?>
                        <span class="status status-done">aktiv</span>
                    <?php else: ?>
                        <span class="status badge-archived">deaktiviert</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $u['user_id'] !== (int) $me['user_id']): ?>
                        <form method="post" action="admin.php" class="inline-form">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                            <button type="submit" class="button button-small button-secondary">
                                <?= (int) $u['is_active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?>
                            </button>
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
<?php
require __DIR__ . '/includes/footer.php';
