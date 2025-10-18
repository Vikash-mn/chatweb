<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix File Room Associations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Fix File Room Associations</h1>
        <p>This script identifies and fixes files that may not be properly associated with rooms.</p>";

if (isset($_POST['check_orphaned'])) {
    echo "<h2>Checking for Orphaned Files</h2>";

    // Find files that don't belong to any existing room
    $query = "SELECT f.id, f.filename, f.roomname, f.filepath, f.username
              FROM files f
              LEFT JOIN rooms r ON f.roomname = r.roomname
              WHERE r.roomname IS NULL
              ORDER BY f.uploaded_at DESC";

    $result = mysqli_query($conn, $query);
    $orphaned = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $orphaned[] = $row;
        }
    }

    if (count($orphaned) > 0) {
        echo "<div class='warning'>Found " . count($orphaned) . " orphaned files (files not associated with existing rooms):</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Filename</th><th>Room</th><th>Username</th><th>Action</th></tr>";

        foreach ($orphaned as $file) {
            echo "<tr>";
            echo "<td>{$file['id']}</td>";
            echo "<td>{$file['filename']}</td>";
            echo "<td><span style='color: #dc3545;'>{$file['roomname']} (INVALID)</span></td>";
            echo "<td>{$file['username']}</td>";
            echo "<td><span style='color: #dc3545;'>Will be deleted</span></td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<br><form method='POST' style='display: inline;'>";
        echo "<button type='submit' name='delete_orphaned' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete " . count($orphaned) . " orphaned files?\")'>Delete Orphaned Files</button>";
        echo "</form>";
    } else {
        echo "<div class='success'>✅ No orphaned files found.</div>";
    }
}

if (isset($_POST['delete_orphaned'])) {
    $delete_query = "DELETE f FROM files f
                    LEFT JOIN rooms r ON f.roomname = r.roomname
                    WHERE r.roomname IS NULL";

    if (mysqli_query($conn, $delete_query)) {
        $affected = mysqli_affected_rows($conn);
        echo "<div class='success'>✅ Deleted $affected orphaned files from database.</div>";
    } else {
        echo "<div class='error'>❌ Failed to delete orphaned files: " . mysqli_error($conn) . "</div>";
    }
}

if (isset($_POST['check_room_distribution'])) {
    echo "<h2>File Distribution by Room</h2>";

    $query = "SELECT f.roomname, COUNT(*) as file_count, r.roomname as room_exists
              FROM files f
              LEFT JOIN rooms r ON f.roomname = r.roomname
              GROUP BY f.roomname
              ORDER BY file_count DESC";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>Room Name</th><th>File Count</th><th>Room Status</th></tr>";

        while ($row = mysqli_fetch_assoc($result)) {
            $status = $row['room_exists'] ? '<span style="color: #28a745;">✅ Room exists</span>' : '<span style="color: #dc3545;">❌ Room deleted</span>';
            echo "<tr>";
            echo "<td>{$row['roomname']}</td>";
            echo "<td>{$row['file_count']}</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No files found in database.</div>";
    }
}

if (isset($_POST['clear_all_files'])) {
    echo "<h2>⚠️ Clearing ALL Files</h2>";
    echo "<div class='warning'>This will delete ALL files from the database. Physical files on disk will remain.</div>";

    $delete_query = "DELETE FROM files";
    if (mysqli_query($conn, $delete_query)) {
        echo "<div class='success'>✅ Cleared all files from database (" . mysqli_affected_rows($conn) . " files deleted).</div>";
    } else {
        echo "<div class='error'>❌ Failed to clear files: " . mysqli_error($conn) . "</div>";
    }
}

echo "<h2>Available Actions</h2>";
echo "<form method='POST' style='display: inline;'>";
echo "<button type='submit' name='check_orphaned' class='btn'>Check Orphaned Files</button>";
echo "<button type='submit' name='check_room_distribution' class='btn'>Check Room Distribution</button>";
echo "<button type='submit' name='clear_all_files' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete ALL files?\")'>Clear ALL Files</button>";
echo "</form>";

echo "<br><br>";
echo "<a href='debug_room_issues.php' class='btn'>Back to Debug</a>";
echo "<a href='cleanup_files.php' class='btn'>Cleanup Files</a>";
echo "<a href='index.php' class='btn'>Back to Index</a>";

echo "    </div>
</body>
</html>";
?>