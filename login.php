<?php
require_once __DIR__ . '/includes/auth.php';

// Wer bereits angemeldet ist, braucht die Anmeldeseite nicht.
if (isLoggedIn())
{
    redirect('index.php');
}

if (isPost())
{
    checkCsrf();

    $username = postText('username');
    $password = $_POST['password'] ?? '';

    $statement = getDb()->prepare('SELECT * FROM users WHERE username = :username');
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    // Eine einzige, neutrale Fehlermeldung, damit nicht verraten wird,
    // ob der Benutzername existiert.
    if (!$user || !password_verify($password, $user['password_hash']))
    {
        setFlash('Benutzername oder Passwort ist falsch.', 'error');
        setOldInput(['username' => $username]);
        redirect('login.php');
    }

    if ((int) $user['is_active'] === 0)
    {
        setFlash('Dieses Benutzerkonto ist deaktiviert.', 'error');
        redirect('login.php');
    }

    loginUser($user);
    setFlash('Willkommen zurueck, ' . $user['username'] . '.', 'success');
    redirect('index.php');
}

$pageTitle = 'Anmeldung';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <section class="card">
        <h1>Anmeldung</h1>
        <form method="post" action="login.php">
            <?= csrfInput() ?>
            <div class="form-group">
                <label for="username">Benutzername</label>
                <input type="text" id="username" name="username"
                       value="<?= e(oldInput('username')) ?>" autofocus required>
            </div>
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="button">Anmelden</button>
            </div>
        </form>
        <p class="muted" style="margin-top:16px;font-size:0.85rem;">
            Demo: admin / admin123 &middot; leiterin / leiter123 &middot; m.schmidt / mita123
        </p>
    </section>
</div>
<?php
require __DIR__ . '/includes/footer.php';
