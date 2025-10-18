<?php
include("connection.php");

// Check rooms table structure
$result = mysqli_query($conn, "DESCRIBE rooms");
echo "<h2>Rooms Table Structure</h2>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check if created_at column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'created_at'");
if (mysqli_num_rows($result) == 0) {
    echo "<h3 style='color: red;'>❌ created_at column is missing!</h3>";

    // Add the missing column
    $sql = "ALTER TABLE rooms ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (mysqli_query($conn, $sql)) {
        echo "<h3 style='color: green;'>✅ Successfully added created_at column</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ Failed to add created_at column: " . mysqli_error($conn) . "</h3>";
    }
} else {
    echo "<h3 style='color: green;'>✅ created_at column exists</h3>";
}

// Check if creator column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'creator'");
if (mysqli_num_rows($result) == 0) {
    echo "<h3 style='color: red;'>❌ creator column is missing!</h3>";

    // Add the missing column
    $sql = "ALTER TABLE rooms ADD COLUMN creator VARCHAR(50) NOT NULL DEFAULT 'Unknown'";
    if (mysqli_query($conn, $sql)) {
        echo "<h3 style='color: green;'>✅ Successfully added creator column</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ Failed to add creator column: " . mysqli_error($conn) . "</h3>";
    }
} else {
    echo "<h3 style='color: green;'>✅ creator column exists</h3>";
}

// Check if display_photo column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'display_photo'");
if (mysqli_num_rows($result) == 0) {
    echo "<h3 style='color: red;'>❌ display_photo column is missing!</h3>";

    // Add the missing column
    $sql = "ALTER TABLE rooms ADD COLUMN display_photo VARCHAR(255) DEFAULT NULL";
    if (mysqli_query($conn, $sql)) {
        echo "<h3 style='color: green;'>✅ Successfully added display_photo column</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ Failed to add display_photo column: " . mysqli_error($conn) . "</h3>";
    }
} else {
    echo "<h3 style='color: green;'>✅ display_photo column exists</h3>";
}

echo "<br><a href='admin.php'>Go to Admin Portal</a>";
?>