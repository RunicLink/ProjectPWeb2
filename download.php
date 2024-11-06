<?php
require_once 'config.php';
checkSession();

if (isset($_GET['task_id'])) {
    $conn = connectDB();
    $task_id = $_GET['task_id'];
    
    $sql = "SELECT file_name, file_path FROM tasks WHERE id = ? AND user_id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
                header('Content-Length: ' . filesize($row['file_path']));
                readfile($row['file_path']);
                exit;
            }
        }
        $stmt->close();
    }
    $conn->close();
}

header("Location: index.php");
exit;
?>