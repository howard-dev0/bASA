<?php
// api/system/backup_db.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo =$GLOBALS['pdo'];

require_role(['super_admin']);

$fetch_num   = constant('PDO::FETCH_NUM');$fetch_assoc = constant('PDO::FETCH_ASSOC');

$tables = [];
$query =$pdo->query("SHOW TABLES");
while ($row = $query->fetch($fetch_num)) {
    $tables[] =$row[0];
}

$sql_dump  = "-- bASA Library SQL Backup\n";
$sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as$table) {
    $res =$pdo->query("SHOW CREATE TABLE `$table`")->fetch($fetch_num);$sql_dump .= "DROP TABLE IF EXISTS `$table`;\n" . $res[1] . ";\n\n";

    $rows =$pdo->query("SELECT * FROM `$table`")->fetchAll($fetch_assoc);
    foreach ($rows as $row) {$fields = array_map(fn($val) => is_null($val) ? 'NULL' : $pdo->quote($val), array_values($row));$sql_dump .= "INSERT INTO `$table` VALUES (" . implode(', ', $fields) . ");\n";
    }
    $sql_dump .= "\n";
}
$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

log_action($pdo,$_SESSION['user_id'], 'DB_BACKUP', 'Downloaded full SQL database backup.');

$filename = 'basa_backup_' . date('Y_m_d_His') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $sql_dump;
exit();