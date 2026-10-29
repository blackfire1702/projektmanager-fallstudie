<?php
require_once __DIR__ . '/config.php';

/**
 * Liefert die PDO-Verbindung zur SQLite-Datenbank.
 * "static $db" sorgt dafuer, dass pro Seitenaufruf nur eine Verbindung entsteht.
 */
function getDb(): PDO
{
    static $db = null;

    if ($db === null)
    {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // SQLite prueft Fremdschluessel nur, wenn es ausdruecklich eingeschaltet wird.
        // Ohne diese Zeile greift ON DELETE CASCADE nicht.
        $db->exec('PRAGMA foreign_keys = ON');
    }

    return $db;
}
