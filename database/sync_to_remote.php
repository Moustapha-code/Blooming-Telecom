<?php
/**
 * Sync the local bloowing_db to a remote MySQL server (Aiven, TiDB, Railway...).
 *
 * Copies structure + data for the ten tables the app uses, recreates the
 * installations triggers, then verifies row counts on both sides.
 * Credentials are read from the environment so nothing is ever committed.
 *
 * Usage (from the project root):
 *   DB_HOST=... DB_PORT=... DB_USER=... DB_PASSWORD=... DB_NAME=bloowing_db \
 *     php database/sync_to_remote.php
 *
 * Optional:
 *   LOCAL_DB=bloowing_db          source database (default bloowing_db)
 *   MYSQLDUMP=/path/to/mysqldump  default: C:/xampp/mysql/bin/mysqldump.exe
 *
 * WARNING: this REPLACES the listed tables on the remote server.
 */

const TABLES = [
    'admin_users', 'technicians', 'zones', 'driver', 'car',
    'car_maintenance', 'installations', 'attendance',
    'materials', 'technician_materials',
];

const TRIGGERS = [
    'trg_installations_before_insert' => "CREATE TRIGGER `trg_installations_before_insert`
BEFORE INSERT ON `installations` FOR EACH ROW
BEGIN
  IF NEW.etat IS NULL OR NEW.etat = '' THEN
    SET NEW.etat = 'encoure';
  END IF;
END",
    'trg_update_cloture_on_status_change' => "CREATE TRIGGER `trg_update_cloture_on_status_change`
BEFORE UPDATE ON `installations` FOR EACH ROW
BEGIN
    IF OLD.etat = 'encoure' AND NEW.etat <> 'encoure' THEN
        IF NEW.etat = 'retard' THEN
            SET NEW.date_de_cloture = NEW.date_realise;
            SET NEW.temp_de_cloture = NEW.temp_de_realise;
        ELSE
            SET NEW.date_de_cloture = CURDATE();
            SET NEW.temp_de_cloture = CURTIME();
        END IF;
    END IF;
END",
];

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

$host = env('DB_HOST');
$port = (int) env('DB_PORT', '3306');
$user = env('DB_USER');
$pass = env('DB_PASSWORD', '');
$name = env('DB_NAME', 'bloowing_db');
$localDb = env('LOCAL_DB', 'bloowing_db');
$mysqldump = env('MYSQLDUMP', 'C:/xampp/mysql/bin/mysqldump.exe');

if (!$host || !$user) {
    fwrite(STDERR, "DB_HOST and DB_USER are required. See the header of this file.\n");
    exit(1);
}

echo "Source : $localDb (local)\n";
echo "Target : $user@$host:$port/$name\n\n";

// 1. Dump the local tables (structure + data, no triggers: they are
//    recreated below because DELIMITER syntax does not survive an API import)
$dumpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bloowing_sync_' . getmypid() . '.sql';
register_shutdown_function(static function () use ($dumpFile) {
    if (is_file($dumpFile)) {
        @unlink($dumpFile);  // never leave real data lying in temp
    }
});
// --net-buffer-length caps the size of each INSERT so no single statement
// can exceed the remote server's max_allowed_packet.
$cmd = sprintf(
    '%s -u root --add-drop-table --skip-comments --skip-triggers --net-buffer-length=65536 %s %s > %s',
    escapeshellarg($mysqldump),
    escapeshellarg($localDb),
    implode(' ', array_map('escapeshellarg', TABLES)),
    escapeshellarg($dumpFile)
);
exec($cmd, $out, $code);
if ($code !== 0 || !is_file($dumpFile) || filesize($dumpFile) === 0) {
    fwrite(STDERR, "mysqldump failed (exit $code). Is MYSQLDUMP correct?\n");
    exit(1);
}
printf("Dumped %s tables, %.1f MB\n", count(TABLES), filesize($dumpFile) / 1048576);

// 2. Push it to the remote server
// Managed hosts (Aiven, TiDB…) require TLS; a plain local server refuses it,
// so try TLS first and fall back.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$m = null;
foreach ([MYSQLI_CLIENT_SSL, 0] as $flags) {
    $try = mysqli_init();
    if ($flags) {
        $try->ssl_set(null, null, null, null, null);
    }
    try {
        $try->real_connect($host, $user, $pass, $name, $port, null, $flags);
        $m = $try;
        echo 'Connected (MySQL ' . $m->server_info . ', TLS ' . ($flags ? 'on' : 'off') . ")\n";
        break;
    } catch (mysqli_sql_exception $e) {
        $lastError = $e->getMessage();
    }
}
if (!$m) {
    fwrite(STDERR, "Connection failed: $lastError\n");
    @unlink($dumpFile);
    exit(1);
}

// Execute statement by statement rather than as one blob: a 1 MB+ dump
// sent in a single call trips max_allowed_packet. mysqldump escapes
// newlines inside string literals, so a line ending in ';' always ends
// a statement.
$fh = fopen($dumpFile, 'r');
$statement = '';
$executed = 0;
$m->query('SET FOREIGN_KEY_CHECKS=0');
try {
    while (($line = fgets($fh)) !== false) {
        $trimmed = rtrim($line, "\r\n");
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $statement .= $line;
        if (substr($trimmed, -1) === ';') {
            $m->query($statement);
            $statement = '';
            if (++$executed % 25 === 0) {
                echo "  ...$executed statements\r";
            }
        }
    }
} catch (mysqli_sql_exception $e) {
    fclose($fh);
    @unlink($dumpFile);
    fwrite(STDERR, "\nImport failed after $executed statements: " . $e->getMessage() . "\n");
    exit(1);
}
fclose($fh);
@unlink($dumpFile);
$m->query('SET FOREIGN_KEY_CHECKS=1');
echo "Data imported ($executed statements)   \n";

// 3. Recreate the triggers
foreach (TRIGGERS as $trigger => $ddl) {
    $m->query("DROP TRIGGER IF EXISTS `$trigger`");
    $m->query($ddl);
}
echo "Triggers recreated (" . count(TRIGGERS) . ")\n\n";

// 4. Verify both sides agree
$local = new PDO("mysql:host=localhost;dbname=$localDb;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$mismatch = false;
foreach (TABLES as $t) {
    $l = (int) $local->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $r = (int) $m->query("SELECT COUNT(*) AS c FROM `$t`")->fetch_assoc()['c'];
    $ok = $l === $r;
    $mismatch = $mismatch || !$ok;
    printf("  %-22s local %6d | remote %6d  %s\n", $t, $l, $r, $ok ? 'OK' : 'MISMATCH');
}

echo "\n" . ($mismatch ? "FINISHED WITH MISMATCHES - check the rows above\n" : "SYNC COMPLETE\n");
exit($mismatch ? 1 : 0);
