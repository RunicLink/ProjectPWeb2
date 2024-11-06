<?php
require_once 'config.php';
checkSession();

$conn = connectDB();

if (isset($_GET['task_id'])) {
    $task_id = intval($_GET['task_id']);
    $user_id = $_SESSION['user_id'];

    $sql = "SELECT * FROM tasks WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode($row);
        } else {
            echo json_encode(["error" => "Task not found"]);
        }

        $stmt->close();
    }
}

$conn->close();
?>
