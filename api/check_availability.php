<?php
// api/check_availability.php
// AJAX endpoint — returns JSON {available: bool, conflict?: string}

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$court_id    = (int) ($_GET['court_id']    ?? 0);
$booking_date= trim($_GET['booking_date']  ?? '');
$start_time  = trim($_GET['start_time']    ?? '');
$end_time    = trim($_GET['end_time']      ?? '');
$exclude_id  = (int) ($_GET['exclude_id']  ?? 0);   // for editing

if (!$court_id || !$booking_date || !$start_time || !$end_time) {
    echo json_encode(['available' => false, 'conflict' => 'Parameter tidak lengkap']);
    exit;
}

if ($start_time >= $end_time) {
    echo json_encode(['available' => false, 'conflict' => 'Jam mulai harus sebelum jam selesai']);
    exit;
}

try {
    $db = getDB();
    // Check for any confirmed reservation that overlaps the requested slot
    $sql = "SELECT renter_name, start_time, end_time
            FROM reservations
            WHERE court_id    = :court_id
              AND booking_date = :booking_date
              AND status       = 'confirmed'
              AND id           != :exclude_id
              AND start_time   < :end_time
              AND end_time     > :start_time
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':court_id'    => $court_id,
        ':booking_date'=> $booking_date,
        ':exclude_id'  => $exclude_id,
        ':end_time'    => $end_time,
        ':start_time'  => $start_time,
    ]);
    $conflict = $stmt->fetch();
    if ($conflict) {
        echo json_encode([
            'available' => false,
            'conflict'  => sprintf(
                '%s (%s–%s)',
                htmlspecialchars($conflict['renter_name']),
                substr($conflict['start_time'], 0, 5),
                substr($conflict['end_time'], 0, 5)
            )
        ]);
    } else {
        echo json_encode(['available' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['available' => false, 'conflict' => 'DB error: ' . $e->getMessage()]);
}
