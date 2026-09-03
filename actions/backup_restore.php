<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_platform_owner();
require_permission('backup.manage');

function remove_tree_contents(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

function copy_tree_contents(string $fromDir, string $toDir): int
{
    if (!is_dir($fromDir)) {
        return 0;
    }

    if (!is_dir($toDir) && !mkdir($toDir, 0775, true) && !is_dir($toDir)) {
        throw new RuntimeException('Failed to prepare restore directory.');
    }

    $copied = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fromDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        $sourcePath = $item->getPathname();
        $relative = substr($sourcePath, strlen($fromDir));
        if ($relative === false) {
            continue;
        }
        $relative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
        $targetPath = $toDir . DIRECTORY_SEPARATOR . $relative;

        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                throw new RuntimeException('Failed to create restore subdirectory.');
            }
            continue;
        }

        $targetParent = dirname($targetPath);
        if (!is_dir($targetParent) && !mkdir($targetParent, 0775, true) && !is_dir($targetParent)) {
            throw new RuntimeException('Failed to prepare restore file directory.');
        }

        if (!@copy($sourcePath, $targetPath)) {
            throw new RuntimeException('Failed to restore backup files.');
        }
        $copied++;
    }

    return $copied;
}

const RESTORE_MAX_SQL_BYTES = 157286400; // 150MB
const RESTORE_MAX_UPLOAD_BYTES = 524288000; // 500MB uncompressed
const RESTORE_MAX_UPLOAD_FILES = 10000;
const RESTORE_MAX_UPLOAD_ENTRY_BYTES = 26214400; // 25MB per uploaded file

function restore_sql_statements(string $sql): array
{
    $statements = [];
    $statement = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        $statement .= $char;

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $statement .= $next;
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== null) {
            if (($quote === '\'' || $quote === '"') && $char === '\\' && $next !== '') {
                $statement .= $next;
                $i++;
                continue;
            }

            if ($quote === '`' && $char === '`' && $next === '`') {
                $statement .= $next;
                $i++;
                continue;
            }

            if (($quote === '\'' || $quote === '"') && $char === $quote && $next === $quote) {
                $statement .= $next;
                $i++;
                continue;
            }

            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === '-' && $next === '-') {
            $statement .= $next;
            $i++;
            $lineComment = true;
            continue;
        }

        if ($char === '#') {
            $lineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $statement .= $next;
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($statement);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $statement = '';
        }
    }

    $trimmed = trim($statement);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

function restore_sql_strip_leading_comments(string $statement): string
{
    $statement = trim($statement);

    while ($statement !== '') {
        if (str_starts_with($statement, '--') || str_starts_with($statement, '#')) {
            $newlinePos = strpos($statement, "\n");
            if ($newlinePos === false) {
                return '';
            }
            $statement = trim(substr($statement, $newlinePos + 1));
            continue;
        }

        if (str_starts_with($statement, '/*')) {
            $endPos = strpos($statement, '*/');
            if ($endPos === false) {
                return '';
            }
            $statement = trim(substr($statement, $endPos + 2));
            continue;
        }

        break;
    }

    return rtrim($statement, "; \t\r\n");
}

function validate_restore_sql(string $sql): void
{
    if (strlen($sql) > RESTORE_MAX_SQL_BYTES) {
        throw new RuntimeException('SQL backup is too large to restore.');
    }

    $hasRestoreStatement = false;
    foreach (restore_sql_statements($sql) as $statement) {
        $statement = restore_sql_strip_leading_comments($statement);
        if ($statement === '') {
            continue;
        }

        if (preg_match('/^SET\s+(?:SQL_MODE|FOREIGN_KEY_CHECKS)\s*=/i', $statement) === 1) {
            continue;
        }

        if (preg_match('/^DROP\s+TABLE\s+IF\s+EXISTS\s+`[^`]+`\s*$/i', $statement) === 1) {
            $hasRestoreStatement = true;
            continue;
        }

        if (preg_match('/^CREATE\s+TABLE\s+`[^`]+`\s*\(/i', $statement) === 1) {
            if (preg_match('/\)\s+SELECT\b/i', $statement) === 1) {
                throw new RuntimeException('Unsupported SQL detected. Restore only accepts app-generated backup files.');
            }
            $hasRestoreStatement = true;
            continue;
        }

        if (preg_match('/^INSERT\s+INTO\s+`[^`]+`\s*\(.+\)\s+VALUES\s*\(/is', $statement) === 1) {
            $hasRestoreStatement = true;
            continue;
        }

        throw new RuntimeException('Unsupported SQL detected. Restore only accepts app-generated backup files.');
    }

    if (!$hasRestoreStatement) {
        throw new RuntimeException('No restorable SQL statements were found.');
    }
}

function restore_zip_entry_is_safe_upload(string $relative): bool
{
    $extension = strtolower((string) pathinfo($relative, PATHINFO_EXTENSION));
    return !in_array($extension, ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp'], true);
}

function copy_zip_entry_with_limit($stream, string $targetAbs, int $entryMaxBytes, int $remainingMaxBytes): int
{
    $out = @fopen($targetAbs, 'wb');
    if ($out === false) {
        fclose($stream);
        throw new RuntimeException('Failed to prepare restore file.');
    }

    $bytesWritten = 0;
    try {
        while (!feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                throw new RuntimeException('Failed to read ZIP backup file.');
            }
            if ($chunk === '') {
                continue;
            }

            $bytesWritten += strlen($chunk);
            if ($bytesWritten > $entryMaxBytes || $bytesWritten > $remainingMaxBytes) {
                throw new RuntimeException('ZIP backup expands beyond the allowed restore size.');
            }

            if (fwrite($out, $chunk) === false) {
                throw new RuntimeException('Failed to write restore file.');
            }
        }
    } finally {
        fclose($stream);
        fclose($out);
    }

    return $bytesWritten;
}

function run_restore_sql(string $sql): void
{
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException('mysqli extension is required for restore.');
    }

    $mysqli = mysqli_init();
    if ($mysqli === false) {
        throw new RuntimeException('Failed to initialize database restore connection.');
    }

    $connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
    if (!$connected) {
        throw new RuntimeException('Database connection failed for restore.');
    }

    $mysqli->set_charset('utf8mb4');
    $script = "SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";

    if (!$mysqli->multi_query($script)) {
        $errorMsg = $mysqli->error;
        $mysqli->close();
        throw new RuntimeException('Restore failed: ' . $errorMsg);
    }

    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        if ($mysqli->errno) {
            $errorMsg = $mysqli->error;
            $mysqli->close();
            throw new RuntimeException('Restore failed: ' . $errorMsg);
        }
    } while ($mysqli->more_results() && $mysqli->next_result());

    $mysqli->close();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/backup.php');
}
require_csrf('pages/backup.php');

$confirmed = (string) ($_POST['confirm_replace'] ?? '') === '1';
if (!$confirmed) {
    set_flash('error', 'Please confirm restore before uploading.');
    redirect('pages/backup.php');
}

if (!isset($_FILES['backup_file']) || !is_array($_FILES['backup_file'])) {
    set_flash('error', 'Backup file is required.');
    redirect('pages/backup.php');
}

$file = $_FILES['backup_file'];
$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error !== UPLOAD_ERR_OK) {
    set_flash('error', 'Upload failed. Please try again.');
    redirect('pages/backup.php');
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$fileName = (string) ($file['name'] ?? '');
$size = (int) ($file['size'] ?? 0);

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    set_flash('error', 'Invalid uploaded file.');
    redirect('pages/backup.php');
}

if ($size <= 0 || $size > 150 * 1024 * 1024) {
    set_flash('error', 'Backup file size must be between 1 byte and 150MB.');
    redirect('pages/backup.php');
}

$extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
if (!in_array($extension, ['sql', 'zip'], true)) {
    set_flash('error', 'Only .sql or .zip backup files are allowed.');
    redirect('pages/backup.php');
}

@set_time_limit(0);

try {
    $sql = '';
    $restoredUploads = 0;
    $restoreMode = 'sql';
    $fullBackupTempDir = '';
    $fullBackupUploadsDir = '';
    $restoredUploadBytes = 0;
    $zip = null;

    if ($extension === 'sql') {
        $sqlRaw = file_get_contents($tmpName);
        if ($sqlRaw === false || trim($sqlRaw) === '') {
            throw new RuntimeException('Unable to read uploaded SQL file.');
        }
        $sql = (string) $sqlRaw;
    } else {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is required to restore .zip backups.');
        }

        $restoreMode = 'full';
        $zip = new ZipArchive();
        $openRes = $zip->open($tmpName);
        if ($openRes !== true) {
            throw new RuntimeException('Unable to open uploaded ZIP backup.');
        }

        $sqlEntryName = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $lower = strtolower($name);
            if ($lower === 'database/backup.sql' || $lower === 'backup.sql') {
                $sqlEntryName = $name;
                break;
            }
            if ($sqlEntryName === '' && str_ends_with($lower, '.sql')) {
                $sqlEntryName = $name;
            }
        }

        if ($sqlEntryName === '') {
            $zip->close();
            throw new RuntimeException('No SQL file found inside ZIP backup.');
        }

        $sqlStat = $zip->statName($sqlEntryName);
        $sqlEntrySize = is_array($sqlStat) ? (int) ($sqlStat['size'] ?? 0) : 0;
        if ($sqlEntrySize <= 0 || $sqlEntrySize > RESTORE_MAX_SQL_BYTES) {
            $zip->close();
            throw new RuntimeException('SQL backup inside ZIP is too large to restore.');
        }

        $sqlRaw = $zip->getFromName($sqlEntryName);
        if ($sqlRaw === false || trim((string) $sqlRaw) === '') {
            $zip->close();
            throw new RuntimeException('SQL content inside ZIP backup is empty or unreadable.');
        }
        $sql = (string) $sqlRaw;

        $fullBackupTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'loan_restore_' . bin2hex(random_bytes(6));
        $fullBackupUploadsDir = $fullBackupTempDir . DIRECTORY_SEPARATOR . 'uploads';
        if (!mkdir($fullBackupUploadsDir, 0775, true) && !is_dir($fullBackupUploadsDir)) {
            $zip->close();
            throw new RuntimeException('Failed to prepare temporary restore directory.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = (string) $zip->getNameIndex($i);
            if ($entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            $normalized = str_replace('\\', '/', $entryName);
            if (!str_starts_with($normalized, 'uploads/')) {
                continue;
            }

            $relative = substr($normalized, strlen('uploads/'));
            if ($relative === false || $relative === '') {
                continue;
            }

            if (str_contains($relative, '../') || str_starts_with($relative, '/')) {
                continue;
            }

            if (!restore_zip_entry_is_safe_upload($relative)) {
                throw new RuntimeException('Backup ZIP contains an executable upload file.');
            }

            $entryStat = $zip->statIndex($i);
            $entrySize = is_array($entryStat) ? (int) ($entryStat['size'] ?? 0) : 0;
            if ($entrySize < 0 || $entrySize > RESTORE_MAX_UPLOAD_ENTRY_BYTES) {
                throw new RuntimeException('Backup ZIP contains an upload file that is too large to restore.');
            }

            if ($restoredUploads + 1 > RESTORE_MAX_UPLOAD_FILES || $restoredUploadBytes + $entrySize > RESTORE_MAX_UPLOAD_BYTES) {
                throw new RuntimeException('Backup ZIP expands beyond the allowed restore size.');
            }

            $targetAbs = $fullBackupUploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $targetDir = dirname($targetAbs);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new RuntimeException('Failed to prepare restore file directory.');
            }

            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                throw new RuntimeException('Failed to read ZIP backup file.');
            }

            $bytesWritten = copy_zip_entry_with_limit(
                $stream,
                $targetAbs,
                RESTORE_MAX_UPLOAD_ENTRY_BYTES,
                RESTORE_MAX_UPLOAD_BYTES - $restoredUploadBytes
            );
            if ($entrySize > 0 && $bytesWritten !== $entrySize) {
                @unlink($targetAbs);
                throw new RuntimeException('Backup ZIP upload file size mismatch.');
            }

            $restoredUploadBytes += $bytesWritten;
            $restoredUploads++;
        }

        $zip->close();
        $zip = null;
    }

    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    validate_restore_sql($sql);

    run_restore_sql($sql);

    if ($restoreMode === 'full') {
        $projectRoot = dirname(__DIR__);
        $uploadsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';
        $uploadsSnapshotDir = $fullBackupTempDir . DIRECTORY_SEPARATOR . 'uploads_snapshot';

        if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0775, true) && !is_dir($uploadsRoot)) {
            throw new RuntimeException('Failed to prepare uploads directory.');
        }

        if (is_dir($uploadsRoot)) {
            copy_tree_contents($uploadsRoot, $uploadsSnapshotDir);
        }

        try {
            remove_tree_contents($uploadsRoot);
            copy_tree_contents($fullBackupUploadsDir, $uploadsRoot);
            ensure_customer_docs_guard_file($uploadsRoot . DIRECTORY_SEPARATOR . 'customer_docs');
            ensure_public_upload_guard_file($uploadsRoot . DIRECTORY_SEPARATOR . 'profile_avatars');
            ensure_public_upload_guard_file($uploadsRoot . DIRECTORY_SEPARATOR . 'business_icons');
        } catch (Throwable $fileRestoreError) {
            remove_tree_contents($uploadsRoot);
            if (is_dir($uploadsSnapshotDir)) {
                copy_tree_contents($uploadsSnapshotDir, $uploadsRoot);
            }
            throw new RuntimeException('Database restored, but file restore failed. Previous files were restored.');
        }
    }

    log_activity($pdo, 'backup.restore', 'Backup restored successfully.', [
        'mode' => $restoreMode,
        'file_name' => $fileName,
        'file_size' => $size,
        'uploads_restored' => $restoredUploads ?? 0,
        'upload_bytes_restored' => $restoredUploadBytes ?? 0,
    ]);
    set_flash('success', $restoreMode === 'full'
        ? 'Full backup restored successfully (database + files).'
        : 'Database backup restored successfully.');
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (str_starts_with($message, 'Restore failed:')) {
        $message = 'Restore failed. Please verify the backup file format and data consistency.';
    }
    log_activity($pdo, 'backup.restore_failed', 'Backup restore failed.', [
        'file_name' => $fileName ?? '',
        'reason' => $e->getMessage(),
    ]);
    set_flash('error', $message);
} finally {
    if (isset($zip) && $zip instanceof ZipArchive) {
        @$zip->close();
    }

    if (isset($fullBackupTempDir) && $fullBackupTempDir !== '' && is_dir($fullBackupTempDir)) {
        remove_tree_contents($fullBackupTempDir);
        @rmdir($fullBackupTempDir);
    }
}
redirect('pages/backup.php');
