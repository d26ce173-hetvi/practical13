<?php
header("Content-Type: application/json; charset=utf-8");

function respond($success, $message, $data = null, $status = 200)
{
    http_response_code($status);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);

    exit;
}

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "studenthub"
);

if ($conn->connect_error) {
    respond(false, "Database connection failed.", null, 500);
}

$conn->set_charset("utf8mb4");

$action = $_GET["action"] ?? "";

/* READ: Display all events */
if ($action === "list") {

    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        respond(false, "GET method required.", null, 405);
    }

    $sql = "SELECT event_id, event_name, event_date, venue
            FROM events
            ORDER BY event_id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        respond(
            false,
            "Unable to load events. Check the events table.",
            null,
            500
        );
    }

    $events = [];

    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }

    respond(true, "Events loaded successfully.", $events);
}

/* Other operations require POST */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "POST method required.", null, 405);
}

/* CREATE and UPDATE input validation */
if ($action === "create" || $action === "update") {

    $eventName = trim($_POST["event_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $venue = trim($_POST["venue"] ?? "");

    if ($eventName === "" || $eventDate === "" || $venue === "") {
        respond(false, "All fields are required.", null, 400);
    }

    $dateParts = explode("-", $eventDate);

    if (
        !preg_match("/^\d{4}-\d{2}-\d{2}$/", $eventDate) ||
        count($dateParts) !== 3 ||
        !checkdate(
            (int)$dateParts[1],
            (int)$dateParts[2],
            (int)$dateParts[0]
        )
    ) {
        respond(false, "Please enter a valid event date.", null, 400);
    }
}

/* CREATE: Insert a new event */
if ($action === "create") {

    $stmt = $conn->prepare(
        "INSERT INTO events (event_name, event_date, venue)
         VALUES (?, ?, ?)"
    );

    if (!$stmt) {
        respond(false, "Unable to prepare insert query.", null, 500);
    }

    $stmt->bind_param("sss", $eventName, $eventDate, $venue);

    if ($stmt->execute()) {
        respond(true, "Event added successfully.");
    }

    respond(false, "Unable to add event.", null, 500);
}

/* UPDATE: Modify an existing event */
if ($action === "update") {

    $eventId = (int)($_POST["event_id"] ?? 0);

    if ($eventId <= 0) {
        respond(false, "Invalid event ID.", null, 400);
    }

    $stmt = $conn->prepare(
        "UPDATE events
         SET event_name = ?, event_date = ?, venue = ?
         WHERE event_id = ?"
    );

    if (!$stmt) {
        respond(false, "Unable to prepare update query.", null, 500);
    }

    $stmt->bind_param(
        "sssi",
        $eventName,
        $eventDate,
        $venue,
        $eventId
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            respond(true, "Event updated successfully.");
        }

        $check = $conn->prepare(
            "SELECT event_id FROM events WHERE event_id = ?"
        );
        $check->bind_param("i", $eventId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            respond(true, "Event information is unchanged.");
        }

        respond(false, "Event not found.", null, 404);
    }

    respond(false, "Unable to update event.", null, 500);
}

/* DELETE: Remove an event */
if ($action === "delete") {

    $eventId = (int)($_POST["event_id"] ?? 0);

    if ($eventId <= 0) {
        respond(false, "Invalid event ID.", null, 400);
    }

    $stmt = $conn->prepare(
        "DELETE FROM events WHERE event_id = ?"
    );

    if (!$stmt) {
        respond(false, "Unable to prepare delete query.", null, 500);
    }

    $stmt->bind_param("i", $eventId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            respond(true, "Event deleted successfully.");
        }

        respond(false, "Event not found.", null, 404);
    }

    respond(false, "Unable to delete event.", null, 500);
}

/* Unknown API action */
respond(false, "Invalid API action.", null, 400);
?>

