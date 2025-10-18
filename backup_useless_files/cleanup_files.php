<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Cleanup Files</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; }
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
        <h1>🧹 Cleanup Files</h1>
        <p>This script helps clean up files that might be causing issues with room-specific file display.</p>";

if (isset($_POST['cleanup_orphaned'])) {
    echo "<h2>Cleaning up orphaned files...</h2>";

    // Find files that don't belong to any existing room
    $query = "SELECT f.id, f.filename, f.roomname, f.filepath
              FROM files f
              LEFT JOIN rooms r ON f.roomname = r.roomname
              WHERE r.roomname IS NULL";

    $result = mysqli_query($conn, $query);
    $orphaned = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $orphaned[] = $row;
        }
    }

    if (count($orphaned) > 0) {
        echo "<div class='warning'>Found " . count($orphaned) . " orphaned files:</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Filename</th><th>Room</th><th>Action</th></tr>";

        foreach ($orphaned as $file) {
            echo "<tr>";
            echo "<td>{$file['id']}</td>";
            echo "<td>{$file['filename']}</td>";
            echo "<td>{$file['roomname']}</td>";
            echo "<td><span style='color: #dc3545;'>Will be deleted</span></td>";
            echo "</tr>";
        }
        echo "</table>";

        // Delete orphaned files
        $delete_query = "DELETE f FROM files f
                        LEFT JOIN rooms r ON f.roomname = r.roomname
                        WHERE r.roomname IS NULL";

        if (mysqli_query($conn, $delete_query)) {
            echo "<div class='success'>✅ Deleted " . mysqli_affected_rows($conn) . " orphaned files from database.</div>";
        } else {
            echo "<div class='error'>❌ Failed to delete orphaned files: " . mysqli_error($conn) . "</div>";
        }
    } else {
        echo "<div class='success'>✅ No orphaned files found.</div>";
    }
}

if (isset($_POST['show_file_distribution'])) {
    echo "<h2>File Distribution by Room</h2>";

    $query = "SELECT roomname, COUNT(*) as file_count, SUM(filesize) as total_size
              FROM files
              GROUP BY roomname
              ORDER BY file_count DESC";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table>";
        echo "<tr><th>Room Name</th><th>File Count</th><th>Total Size (KB)</th></tr>";

        while ($row = mysqli_fetch_assoc($result)) {
            $total_kb = round($row['total_size'] / 1024, 1);
            echo "<tr>";
            echo "<td>{$row['roomname']}</td>";
            echo "<td>{$row['file_count']}</td>";
            echo "<td>{$total_kb}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No files found in any room.</div>";
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
echo "<button type='submit' name='show_file_distribution' class='btn'>Show File Distribution</button>";
echo "<button type='submit' name='cleanup_orphaned' class='btn'>Cleanup Orphaned Files</button>";
echo "<button type='submit' name='clear_all_files' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete ALL files?\")'>Clear ALL Files</button>";
echo "</form>";

echo "<br><br>";
echo "<a href='debug_room_issues.php' class='btn'>Back to Debug</a>";
echo "<a href='index.php' class='btn'>Back to Index</a>";

echo "    </div>
</body>
</html>";
?>