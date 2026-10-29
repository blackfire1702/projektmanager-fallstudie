<?php
/**
 * Leichter, abhaengigkeitsfreier Test-Harness (kein Composer/PHPUnit noetig).
 * Aufruf auf der Kommandozeile:
 *     C:\xampp\php\php.exe tests\run_tests.php
 *
 * Getestet werden die rein fachlichen Funktionen (Logik ohne Datenbank), damit
 * die Kernregeln der Anwendung automatisiert nachpruefbar sind.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$passed = 0;
$failed = 0;

/** Prueft eine Bedingung und protokolliert das Ergebnis. */
function check(string $name, bool $condition): void
{
    global $passed, $failed;

    if ($condition)
    {
        $passed++;
        echo "  [OK]   $name\n";
    }
    else
    {
        $failed++;
        echo "  [FAIL] $name\n";
    }
}

echo "ProjektManager - Testlauf\n";
echo str_repeat('-', 50) . "\n";

echo "Fortschrittsberechnung:\n";
check('Keine Aufgaben ergibt 0 %', calculateProgress(0, 0) === 0);
check('2 von 4 ergibt 50 %', calculateProgress(4, 2) === 50);
check('1 von 3 rundet auf 33 %', calculateProgress(3, 1) === 33);
check('2 von 3 rundet auf 67 %', calculateProgress(3, 2) === 67);
check('Alle erledigt ergibt 100 %', calculateProgress(3, 3) === 100);
check('Keine erledigt ergibt 0 %', calculateProgress(10, 0) === 0);

echo "Aufgaben-Status:\n";
check('"offen" ist gueltig', isValidTaskStatus('offen') === true);
check('"in_bearbeitung" ist gueltig', isValidTaskStatus('in_bearbeitung') === true);
check('"erledigt" ist gueltig', isValidTaskStatus('erledigt') === true);
check('"unbekannt" ist ungueltig', isValidTaskStatus('unbekannt') === false);
check('Leerer Status ist ungueltig', isValidTaskStatus('') === false);
check('Label fuer in_bearbeitung', taskStatusLabel('in_bearbeitung') === 'In Bearbeitung');

echo "XSS-Schutz (e()):\n";
check('Script-Tag wird maskiert', e('<script>') === '&lt;script&gt;');
check('Anfuehrungszeichen werden maskiert', e('"\'') === '&quot;&#039;');
check('Null wird zu leerem String', e(null) === '');

echo "E-Mail-Normalisierung:\n";
check('Gross/Klein + Leerzeichen', normalizeEmail('  Max.Muster@Mail.DE ') === 'max.muster@mail.de');

echo "Passwort-Hashing:\n";
$hash = password_hash('geheim123', PASSWORD_DEFAULT);
check('Hash unterscheidet sich vom Klartext', $hash !== 'geheim123');
check('Korrektes Passwort verifiziert', password_verify('geheim123', $hash) === true);
check('Falsches Passwort scheitert', password_verify('falsch', $hash) === false);

echo "CSRF-Token:\n";
$_SESSION = [];
$token = csrfToken();
check('Token ist 64 Hex-Zeichen lang', (bool) preg_match('/^[a-f0-9]{64}$/', $token));
check('Token bleibt pro Session stabil', csrfToken() === $token);
check('Gueltiger Vergleich besteht', hash_equals($token, csrfToken()) === true);
check('Falscher Token wird abgelehnt', hash_equals($token, str_repeat('0', 64)) === false);

echo str_repeat('-', 50) . "\n";
echo "Bestanden: $passed  |  Fehlgeschlagen: $failed\n";

exit($failed === 0 ? 0 : 1);
