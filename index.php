<?php
require_once 'config.php';
checkSession();

$conn = connectDB();

// Fetch tasks
$sql = "SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC";
$tasks = [];

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
}

// Handle delete action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $task_id = $_POST['task_id'];
    
    // First get the file path if exists
    $sql = "SELECT file_path FROM tasks WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                unlink($row['file_path']);
            }
        }
        $stmt->close();
    }
    
    // Then delete the task
    $sql = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Task deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to delete task";
        }
        $stmt->close();
    }
    
    header("Location: index.php");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Todo List</title>
    <style>
        /* General Dark Theme */
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 90%;
            max-width: 800px;
        }

        /* Header Styling */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 2px solid #333;
        }
        .header-content h1 {
            margin: 0;
            font-size: 28px;
        }
        .welcome-message {
            font-size: 18px;
        }
        .header-actions a {
            margin-left: 10px;
            padding: 8px 12px;
            background-color: #0078d7;
            color: #e0e0e0;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .header-actions a:hover {
            background-color: #005bb5;
        }

        /* Alerts */
        .alert {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        .success {
            background-color: #4CAF50;
        }
        .error {
            background-color: #f44336;
        }

        /* Task List */
        .task-list {
            margin-top: 20px;
        }
        .task-list h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .no-tasks {
            color: #b3b3b3;
            text-align: center;
            margin-top: 10px;
        }

        /* Task Item */
        .task-item {
            background-color: #1e1e1e;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .task-item h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }
        .task-item p {
            margin: 5px 0;
            color: #b3b3b3;
        }
        .task-status {
            font-weight: bold;
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 14px;
            color: #e0e0e0;
        }
        .status-badge.completed { background-color: #4CAF50; }
        .status-badge.pending { background-color: #f39c12; }

        /* Task Actions */
        .task-actions {
            margin-top: 10px;
        }
        .task-actions a, .task-actions button {
            display: inline-block;
            margin-right: 10px;
            padding: 8px 12px;
            background-color: #0078d7;
            color: #e0e0e0;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .task-actions a:hover, .task-actions button:hover {
            background-color: #005bb5;
        }
        .btn-delete {
            background-color: #f44336;
        }
        .btn-delete:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1>Todo List</h1>
                <p class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            </div>
            <div class="header-actions">
                <a href="create.php" class="btn-create">Create New Task</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Task List -->
        <div class="task-list">
            <h2>Your Tasks</h2>
            <?php if (empty($tasks)): ?>
                <p class="no-tasks">You haven't created any tasks yet.</p>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item <?php echo $task['status']; ?>">
                        <h3><?php echo htmlspecialchars($task['title']); ?></h3>
                        <p><?php echo htmlspecialchars($task['description']); ?></p>
                        <p>Due: <?php echo $task['due_date']; ?></p>
                        <p class="task-status">
                            Status: 
                            <span class="status-badge <?php echo $task['status']; ?>">
                                <?php 
                                    $status_text = str_replace('_', ' ', ucfirst($task['status']));
                                    echo htmlspecialchars($status_text); 
                                ?>
                            </span>
                        </p>
                        
                        <?php if (!empty($task['file_name'])): ?>
                            <p>Attachment: 
                                <a href="download.php?task_id=<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['file_name']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <div class="task-actions">
                            <a href="edit.php?id=<?php echo $task['id']; ?>" class="btn-edit">Edit</a>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                                method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this task?')">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
