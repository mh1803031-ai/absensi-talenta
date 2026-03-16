<?php
require_once __DIR__ . '/../config/database.php';

function generateToken(int $length = 6): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

function generateUniqueToken(): string {
    do {
        $token = generateToken();
        $stmt = db()->prepare("SELECT id FROM attendance_tokens WHERE token = ?");
        $stmt->execute([$token]);
    } while ($stmt->fetch());
    return $token;
}

function validateToken(string $token, int $studentId): array {
    $today = date('Y-m-d');
    $now   = date('Y-m-d H:i:s');
    $stmt  = db()->prepare(
        "SELECT t.*, c.name AS class_name
         FROM attendance_tokens t
         LEFT JOIN classes c ON c.id = t.class_id
         WHERE t.token = ? AND t.valid_date = ? AND t.expired_at >= ? AND t.is_active = 1"
    );
    $stmt->execute([$token, $today, $now]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) return ['success' => false, 'message' => 'Token tidak valid atau sudah kedaluwarsa.'];

    if ($tokenRow['class_id']) {
        $userStmt = db()->prepare("SELECT class_id FROM users WHERE id = ?");
        $userStmt->execute([$studentId]);
        $user = $userStmt->fetch();
        if ($user && $user['class_id'] != $tokenRow['class_id']) {
            return ['success' => false, 'message' => 'Token tidak untuk kelas Anda.'];
        }
    }

    $existStmt = db()->prepare(
        "SELECT id FROM attendance_records WHERE student_id = ? AND token_id = ?"
    );
    $existStmt->execute([$studentId, $tokenRow['id']]);
    if ($existStmt->fetch()) {
        return ['success' => false, 'message' => 'Anda sudah absen menggunakan token ini.'];
    }

    $insStmt = db()->prepare(
        "INSERT INTO attendance_records (student_id, token_id, status) VALUES (?, ?, 'hadir')"
    );
    $insStmt->execute([$studentId, $tokenRow['id']]);
    $attendanceId = db()->lastInsertId();

    $_SESSION['attendance_id'] = $attendanceId;
    $_SESSION['attended_today'] = true;

    return ['success' => true, 'message' => 'Absensi berhasil! Selamat datang.', 'attendance_id' => $attendanceId];
}

function hasAttendedToday(int $studentId): bool {
    $today = date('Y-m-d');
    $stmt  = db()->prepare(
        "SELECT r.id FROM attendance_records r
         JOIN attendance_tokens t ON t.id = r.token_id
         WHERE r.student_id = ? AND t.valid_date = ?"
    );
    $stmt->execute([$studentId, $today]);
    return (bool)$stmt->fetch();
}

function getTodayAttendanceId(int $studentId): ?int {
    $today = date('Y-m-d');
    $stmt  = db()->prepare(
        "SELECT r.id FROM attendance_records r
         JOIN attendance_tokens t ON t.id = r.token_id
         WHERE r.student_id = ? AND t.valid_date = ? LIMIT 1"
    );
    $stmt->execute([$studentId, $today]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function hasSubmittedJournalToday(int $studentId): bool {
    $attendanceId = getTodayAttendanceId($studentId);
    if (!$attendanceId) return false;
    $stmt = db()->prepare("SELECT id FROM journals WHERE student_id = ? AND attendance_id = ?");
    $stmt->execute([$studentId, $attendanceId]);
    return (bool)$stmt->fetch();
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function clean(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function countTable(string $table, string $where = '', array $params = []): int {
    $sql  = "SELECT COUNT(*) FROM $table" . ($where ? " WHERE $where" : '');
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function formatDate(string $date): string {
    return date('d M Y', strtotime($date));
}

function formatDateTime(string $dt): string {
    return date('d M Y H:i', strtotime($dt));
}

function statusBadge(string $status): string {
    $map = [
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'reviewed' => '<span class="badge badge-info">Ditinjau</span>',
        'approved' => '<span class="badge badge-success">Disetujui</span>',
        'revision' => '<span class="badge badge-danger">Revisi</span>',
        'hadir'    => '<span class="badge badge-success">Hadir</span>',
        'izin'     => '<span class="badge badge-info">Izin</span>',
        'alpha'    => '<span class="badge badge-danger">Alpha</span>',
    ];
    return $map[$status] ?? "<span class='badge'>$status</span>";
}

function roleBadge(string $role): string {
    $map = [
        'admin'      => '<span class="badge badge-danger">Admin</span>',
        'guru'       => '<span class="badge badge-primary">Guru</span>',
        'instruktur' => '<span class="badge badge-info">Instruktur</span>',
        'siswa'      => '<span class="badge badge-success">Siswa</span>',
    ];
    return $map[$role] ?? "<span class='badge'>$role</span>";
}

function avatarInitials(string $name): string {
    $words = explode(' ', trim($name));
    $init  = strtoupper(substr($words[0], 0, 1));
    if (isset($words[1])) $init .= strtoupper(substr($words[1], 0, 1));
    return $init;
}

function getDashboardLink(): string {
    $role = currentRole();
    return match($role) {
        'admin'      => '/TUGASPAKDANIL/ABSENSITALENTA/admin/dashboard.php',
        'guru'       => '/TUGASPAKDANIL/ABSENSITALENTA/guru/dashboard.php',
        'instruktur' => '/TUGASPAKDANIL/ABSENSITALENTA/instruktur/dashboard.php',
        'siswa'      => '/TUGASPAKDANIL/ABSENSITALENTA/siswa/dashboard.php',
        default      => '/TUGASPAKDANIL/ABSENSITALENTA/login.php',
    };
}