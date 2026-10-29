<?php
// Zentrale Grundeinstellungen. Wird von fast allen anderen Dateien eingebunden.

// "secure" bleibt aus, weil die lokale XAMPP-Umgebung ohne HTTPS laeuft.
// Auf einem echten Server muesste dieser Wert auf true stehen.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => false
]);

if (session_status() === PHP_SESSION_NONE)
{
    session_start();
}

define('APP_NAME', 'ProjektManager');
define('DB_PATH', __DIR__ . '/../database/projektmanager.sqlite');

// Erlaubte Status-Werte an einer Stelle, damit Formulare und Pruefungen
// dieselbe Quelle nutzen.
const TASK_STATUSES = ['offen', 'in_bearbeitung', 'erledigt'];
const PROJECT_STATUSES = ['aktiv', 'archiviert'];
const ROLE_NAMES = ['Administrator', 'Projektleiter', 'Mitarbeiter'];
