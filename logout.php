<?php
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('Du wurdest abgemeldet.', 'info');
redirect('login.php');
