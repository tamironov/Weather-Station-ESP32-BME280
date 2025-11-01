<?php
// --- CONFIGURATION & SETUP ---
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Jerusalem');

// --- DATABASE CONFIGURATION ---
// ⚠️ IMPORTANT: Replace the placeholders below with your own values
$servername = getenv('DB_HOST') ?: 'localhost';
$username   = getenv('DB_USER') ?: 'your_username';
$password   = getenv('DB_PASS') ?: 'your_password';
$dbname     = getenv('DB_NAME') ?: 'your_database_name';

// --- DATABASE CONNECTION ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- DATA FETCHING LOGIC ---
$range = $_GET['range'] ?? 'today';
$selected_date = $_GET['date'] ?? null;
$time_filter_sql = "";
$filter_display_text = 'TODAY';
$is_long_range = false;

if ($selected_date) {
    $safe_date = $conn->real_escape_string($selected_date);
    $time_filter_sql = "WHERE DATE(created_at) = '$safe_date'";
    $filter_display_text = 'Day: ' . htmlspecialchars($selected_date);
} else {
    switch ($range) {
        case '12h':
            $time_filter_sql = "WHERE created_at >= NOW() - INTERVAL 12 HOUR";
            $filter_display_text = 'LAST 12 HOURS';
            break;
        case '24h':
            $time_filter_sql = "WHERE created_at >= NOW() - INTERVAL 24 HOUR";
            $filter_display_text = 'LAST 24 HOURS';
            break;
        case 'thisweek':
            $time_filter_sql = "WHERE YEARWEEK(created_at, 0) = YEARWEEK(CURDATE(), 0)";
            $filter_display_text = 'THIS WEEK';
            $is_long_range = true;
            break;
        case 'thismonth':
            $time_filter_sql = "WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            $filter_display_text = 'THIS MONTH';
            $is_long_range = true;
            break;
        case '1y':
            $time_filter_sql = "WHERE created_at >= NOW() - INTERVAL 1 YEAR";
            $filter_display_text = 'LAST YEAR';
            $is_long_range = true;
            break;
        case 'today':
        default:
            $time_filter_sql = "WHERE DATE(created_at) = CURDATE()";
            $filter_display_text = 'TODAY';
            break;
    }
}

// --- DATA QUERIES ---
$current_result = $conn->query("SELECT temperature, humidity, pressure, created_at FROM measurements ORDER BY id DESC LIMIT 1");
$current_data = $current_result->fetch_assoc() ?? ['temperature' => null, 'humidity' => null, 'pressure' => null, 'created_at' => null];

$display_time = 'N/A';
if (!empty($current_data['created_at'])) {
    try {
        $dateTime = new DateTime($current_data['created_at']);
        $display_time = $dateTime->format('H:i:s');
    } catch (Exception $e) {}
}

$summary_sql = "SELECT
    MAX(temperature) AS max_temp, MIN(temperature) AS min_temp,
    MAX(humidity) AS max_hum, MIN(humidity) AS min_hum,
    MAX(pressure) AS max_pres, MIN(pressure) AS min_pres
    FROM measurements " . $time_filter_sql;
$summary_result = $conn->query($summary_sql);
$summary_data = $summary_result->fetch_assoc() ?? [
    'max_temp' => null, 'min_temp' => null,
    'max_hum' => null, 'min_hum' => null,
    'max_pres' => null, 'min_pres' => null
];

$delta_temp = is_numeric($summary_data['max_temp']) && is_numeric($summary_data['min_temp']) ? $summary_data['max_temp'] - $summary_data['min_temp'] : null;
$delta_hum  = is_numeric($summary_data['max_hum']) && is_numeric($summary_data['min_hum']) ? $summary_data['max_hum'] - $summary_data['min_hum'] : null;
$delta_pres = is_numeric($summary_data['max_pres']) && is_numeric($summary_data['min_pres']) ? $summary_data['max_pres'] - $summary_data['min_pres'] : null;

$history_sql = "SELECT created_at, temperature, humidity, pressure FROM measurements " . $time_filter_sql . " ORDER BY id ASC";
$history_result = $conn->query($history_sql);
$timestamps = []; $temperatures = []; $humidities = []; $pressures = [];
while ($row = $history_result->fetch_assoc()) {
    $timestamps[] = $row['created_at'];
    $temperatures[] = $row['temperature'];
    $humidities[] = $row['humidity'];
    $pressures[] = $row['pressure'];
}
$conn->close();

// --- ALTITUDE CALCULATION ---
$P  = $current_data['pressure'];
$P0 = 1013.25;
$altitude_est = (is_numeric($P) && $P > 0) ? 44330 * (1 - pow($P / $P0, 1 / 5.255)) : null;

// --- AJAX RESPONSE ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'filter_display_text' => $filter_display_text,
        'current_data' => $current_data,
        'summary_data' => $summary_data,
        'display_time' => $display_time,
        'delta' => [
            'temp' => $delta_temp,
            'hum'  => $delta_hum,
            'pres' => $delta_pres
        ],
        'altitude_est' => $altitude_est,
        'charts' => [
            'labels'   => $timestamps,
            'tempData' => array_map('floatval', $temperatures),
            'humData'  => array_map('floatval', $humidities),
            'presData' => array_map('floatval', $pressures),
            'isLongRange' => $is_long_range
        ]
    ]);
    exit;
}
?>
<!-- The HTML and JS parts remain the same -->
