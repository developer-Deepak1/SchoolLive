<?php
// ev_mark_absent_employees.php
// Run this script daily (via cron) to mark absent employees for the previous day (IST)

require __DIR__ . '/../vendor/autoload.php';

// Load database configuration file which returns an array.
// Old config file returns an array like:
// [ 'host'=>..., 'port'=>..., 'db_name'=>..., 'username'=>..., 'password'=>..., 'charset'=>... ]
$cfgFromFile = null;
if (file_exists(__DIR__ . '/../config/database.php')) {
    $cfgFromFile = require __DIR__ . '/../config/database.php';
}

// Normalize config values into $db array: host, port, database, username, password, charset
$db = [];
if (is_array($cfgFromFile)) {
    $db['host'] = $cfgFromFile['host'] ?? ($cfgFromFile['hostname'] ?? '127.0.0.1');
    $db['port'] = $cfgFromFile['port'] ?? 3306;
    // support 'db_name' or 'database'
    $db['database'] = $cfgFromFile['db_name'] ?? $cfgFromFile['database'] ?? null;
    $db['username'] = $cfgFromFile['username'] ?? $cfgFromFile['user'] ?? null;
    $db['password'] = $cfgFromFile['password'] ?? $cfgFromFile['pass'] ?? '';
    $db['charset'] = $cfgFromFile['charset'] ?? 'utf8mb4';
}

// If some essential values missing, fall back to constants or environment variables
if (empty($db['database']) || empty($db['username'])) {
    $db['host'] = $db['host'] ?? (defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? '127.0.0.1'));
    $db['port'] = $db['port'] ?? (defined('DB_PORT') ? DB_PORT : ($_ENV['DB_PORT'] ?? 3306));
    $db['database'] = $db['database'] ?? (defined('DB_NAME') ? DB_NAME : ($_ENV['DB_NAME'] ?? 'schoollive'));
    $db['username'] = $db['username'] ?? (defined('DB_USER') ? DB_USER : ($_ENV['DB_USER'] ?? 'root'));
    $db['password'] = $db['password'] ?? (defined('DB_PASS') ? DB_PASS : ($_ENV['DB_PASS'] ?? ''));
    $db['charset'] = $db['charset'] ?? (defined('DB_CHARSET') ? DB_CHARSET : ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));
}

// // Diagnostic: report resolved DB configuration (password masked)
// $cfgSource = is_array($cfgFromFile) ? 'config/database.php' : 'environment variables/constants';
// fwrite(STDOUT, "DB config source: {$cfgSource}\n");
// fwrite(STDOUT, "Resolved DB host={$db['host']} port={$db['port']} database={$db['database']} user={$db['username']} charset={$db['charset']}\n");

// // Validate minimal config presence before creating PDO
// if (empty($db['database']) || empty($db['username'])) {
//     fwrite(STDERR, "Database configuration incomplete. Checked: __DIR__ . '/../config/database.php'\n");
//     fwrite(STDERR, "Please ensure config returns an array with keys 'db_name' (or 'database') and 'username'.\n");
//     exit(1);
// }

// Create PDO connection
try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (\Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Compute target_date = previous day in IST
// Convert server current date (UTC assumed) to IST, then subtract 1 day
try {
    $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
    $nowIst = clone $nowUtc;
    $nowIst->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $targetDate = $nowIst->sub(new DateInterval('P1D'))->format('Y-m-d');

    // Get day of week as 1=Monday..7=Sunday (MySQL WEEKDAY +1)
    $dow = (int)$nowIst->format('N'); // 1 (Mon) to 7 (Sun)
} catch (\Exception $e) {
    fwrite(STDERR, "Date calculation failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Build and execute insert logic similar to SQL event
try {
    $sql = <<<'SQL'
INSERT INTO Tx_Employee_Attendance (
    EmployeeID,
    SchoolID,
    AcademicYearID,
    Date,
    Status,
    Remarks,
    CreatedBy,
    CreatedAt
)
SELECT
    e.EmployeeID,
    e.SchoolID,
    e.AcademicYearID,
    :target_date AS Date,
    'Leave' AS Status,
    'Auto-marked absent by system' AS Remarks,
    'System' AS CreatedBy,
    CONVERT_TZ(NOW(), '+00:00', '+05:30') AS CreatedAt
FROM Tx_Employees e
INNER JOIN Tm_AcademicYears ay ON e.AcademicYearID = ay.AcademicYearID
    AND ay.IsActive = TRUE
    AND :target_date BETWEEN ay.StartDate AND ay.EndDate
WHERE e.IsActive = TRUE
AND NOT EXISTS (
    SELECT 1 FROM Tx_Employee_Attendance ea
    WHERE ea.EmployeeID = e.EmployeeID
    AND ea.Date = :target_date
)
AND NOT EXISTS (
    SELECT 1 FROM Tx_Holidays h
    WHERE h.SchoolID = e.SchoolID
    AND h.AcademicYearID = e.AcademicYearID
    AND h.Date = :target_date
    AND h.Type = 'Holiday'
    AND h.IsActive = TRUE
)
AND NOT EXISTS (
    SELECT 1 FROM Tx_WeeklyOffs wo
    WHERE wo.SchoolID = e.SchoolID
    AND wo.AcademicYearID = e.AcademicYearID
    AND wo.DayOfWeek = :dow
    AND wo.IsActive = TRUE
)
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':target_date', $targetDate);
    $stmt->bindValue(':dow', $dow, PDO::PARAM_INT);

    $stmt->execute();
    $count = $stmt->rowCount();
    echo "Marked {$count} employee(s) absent for {$targetDate}\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Execution failed: " . $e->getMessage() . "\n");
    exit(1);
}
