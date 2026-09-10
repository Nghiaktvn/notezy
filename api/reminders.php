<?php
require_once dirname(__DIR__) . '/includes/session.php';
/**
 * api/reminders.php — poll for due time-based reminders.
 *
 * Time-based only (notes.reminder_at). This intentionally does NOT do
 * location-based ("khi tôi đến địa điểm X") reminders — that needs a native
 * mobile geofencing API, which a PHP+browser web app cannot provide reliably
 * in the background. See AI.md "Roadmap" for what that would require.
 *
 * The client (js/reminders.js) polls this endpoint every ~30s while the app
 * tab is open and fires a browser Notification for each due reminder. This
 * only works while the tab is open — it is not a background/OS-level alarm.
 * A true "notify even when the app/browser is closed" alarm needs Web Push
 * (VAPID keys + a push subscription per device) wired into sw.js; that is
 * listed as a roadmap item rather than faked here.
 */
require_once 'db.php';
notezy_session_start();
header('Content-Type: application/json');

// Fix múi giờ: đặt session timezone về +07:00 (giờ Việt Nam)
// để NOW() trong MySQL khớp với giờ người dùng nhập vào
$conn->query("SET time_zone = '+07:00'");

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized — please login"]);
    exit();
}
$user_id = (int) $_SESSION['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    // ?upcoming=1 → trả về danh sách reminder chưa đến hạn (trong 24h tới) cho dropdown bell
    if (isset($_GET['upcoming'])) {
        $stmt = $conn->prepare(
            "SELECT note_id, title, reminder_at
             FROM notes
             WHERE user_id = ? AND reminder_at IS NOT NULL
               AND reminder_at > NOW()
               AND reminder_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
               AND reminder_sent = 0
             ORDER BY reminder_at ASC
             LIMIT 20"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $upcoming = [];
        while ($row = $res->fetch_assoc()) {
            // Format thời gian thân thiện
            $dt = new DateTime($row['reminder_at']);
            $row['reminder_at'] = $dt->format('H:i d/m/Y');
            $upcoming[] = [
                'note_id'     => (int) $row['note_id'],
                'title'       => $row['title'],
                'reminder_at' => $row['reminder_at'],
            ];
        }
        echo json_encode(["status" => "success", "upcoming" => $upcoming]);
        exit();
    }

    // Poll reminder đến hạn (reminder_at <= NOW())
    $stmt = $conn->prepare(
        "SELECT note_id, title, reminder_at
         FROM notes
         WHERE user_id = ? AND reminder_at IS NOT NULL
           AND reminder_at <= NOW() AND reminder_sent = 0
         ORDER BY reminder_at ASC
         LIMIT 20"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $due = [];
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $dt = new DateTime($row['reminder_at']);
        $due[] = [
            'note_id'     => (int) $row['note_id'],
            'title'       => $row['title'],
            'reminder_at' => $dt->format('H:i d/m/Y'),
        ];
        $ids[] = (int) $row['note_id'];
    }
    // Mark as sent so the same reminder doesn't fire on every poll.
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids)) . 'i';
        $sql = "UPDATE notes SET reminder_sent = 1 WHERE note_id IN ($placeholders) AND user_id = ?";
        $upd = $conn->prepare($sql);
        $upd->bind_param($types, ...[...$ids, $user_id]);
        $upd->execute();
    }
    echo json_encode(["status" => "success", "due" => $due]);
    exit();
}


if ($method === 'POST') {
    // Set or clear a reminder for one note: { note_id, reminder_at }
    $data = json_decode(file_get_contents("php://input"), true) ?: [];
    $note_id = (int) ($data['note_id'] ?? 0);
    if ($note_id <= 0) {
        echo json_encode(["status" => "error", "message" => "note_id required"]);
        exit();
    }
    $own = $conn->prepare("SELECT note_id FROM notes WHERE note_id = ? AND user_id = ?");
    $own->bind_param("ii", $note_id, $user_id);
    $own->execute();
    if ($own->get_result()->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit();
    }
    $reminder_at = isset($data['reminder_at']) && $data['reminder_at'] !== '' ? $data['reminder_at'] : null;
    $upd = $conn->prepare(
        "UPDATE notes SET reminder_at = ?, reminder_sent = 0 WHERE note_id = ? AND user_id = ?"
    );
    $upd->bind_param("sii", $reminder_at, $note_id, $user_id);
    if ($upd->execute()) {
        echo json_encode(["status" => "success", "note_id" => $note_id, "reminder_at" => $reminder_at]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to set reminder"]);
    }
    exit();
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
