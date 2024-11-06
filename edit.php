<?php
require_once 'config.php';
checkSession();

function checkUploadDirectory() {
    $upload_dir = "uploads/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    return $upload_dir;
}

function handleFileUpload($file) {
    $upload_dir = checkUploadDirectory();
    $errors = array();
    
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('success' => true);
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errors[] = "File exceeds upload_max_filesize in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "File exceeds MAX_FILE_SIZE in HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "File was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = "Temporary folder not found";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errors[] = "Upload stopped by PHP extension";
                break;
            default:
                $errors[] = "Unknown upload error";
                break;
        }
        return array('success' => false, 'errors' => $errors);
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $errors[] = "File size too large (max 5MB)";
        return array('success' => false, 'errors' => $errors);
    }
    
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt');
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = "File type not allowed";
        return array('success' => false, 'errors' => $errors);
    }
    
    $file_name = basename($file['name']);
    $safe_filename = preg_replace("/[^a-zA-Z0-9\.]/", "_", $file_name);
    $unique_filename = uniqid() . '_' . $safe_filename;
    $file_path = $upload_dir . $unique_filename;
    
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        $errors[] = "Failed to move uploaded file";
        return array('success' => false, 'errors' => $errors);
    }
    
    return array(
        'success' => true,
        'file_name' => $file_name,
        'file_path' => $file_path
    );
}

$conn = connectDB();
$task = null;
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch task data
if ($task_id > 0) {
    $sql = "SELECT * FROM tasks WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($task = $result->fetch_assoc()) {
            // Task found
        } else {
            $_SESSION['error_message'] = "Task not found or access denied.";
            header("Location: index.php");
            exit;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error_message'] = "Invalid task ID.";
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = sanitize($conn, $_POST['title']);
    $description = sanitize($conn, $_POST['description']);
    $due_date = sanitize($conn, $_POST['due_date']);
    $status = sanitize($conn, $_POST['status']);
    
    // Handle file deletion
    if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
        if (!empty($task['file_path']) && file_exists($task['file_path'])) {
            unlink($task['file_path']);
        }
        $sql = "UPDATE tasks SET file_name = '', file_path = '' WHERE id = ? AND user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
        $task['file_name'] = '';
        $task['file_path'] = '';
    }
    
    // Handle new file upload
    if (isset($_FILES['task_file']) && $_FILES['task_file']['size'] > 0) {
        $upload_result = handleFileUpload($_FILES['task_file']);
        
        if (!$upload_result['success'] && isset($upload_result['errors'])) {
            $_SESSION['error_message'] = implode(", ", $upload_result['errors']);
            header("Location: edit.php?id=" . $task_id);
            exit;
        }
        
        if ($upload_result['success'] && isset($upload_result['file_name'])) {
            // Delete old file if exists
            if (!empty($task['file_path']) && file_exists($task['file_path'])) {
                unlink($task['file_path']);
            }
            
            $sql = "UPDATE tasks SET title = ?, description = ?, due_date = ?, 
                    status = ?, file_name = ?, file_path = ? 
                    WHERE id = ? AND user_id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssssssii", $title, $description, $due_date, 
                                $status, $upload_result['file_name'], 
                                $upload_result['file_path'], $task_id, 
                                $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Task updated successfully!";
                    header("Location: index.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Failed to update task: " . $conn->error;
                }
                $stmt->close();
            }
        }
    } else {
        // Update without file change
        $sql = "UPDATE tasks SET title = ?, description = ?, due_date = ?, 
                status = ? WHERE id = ? AND user_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssssii", $title, $description, $due_date, 
                            $status, $task_id, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Task updated successfully!";
                header("Location: index.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Failed to update task: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Task - Todo List</title>
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
            max-width: 600px;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
        }

        /* Header Styling */
        header {
            text-align: center;
            margin-bottom: 20px;
        }
        header h1 {
            font-size: 28px;
            margin: 0;
        }
        .header-actions a {
            margin: 10px 5px;
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

        /* Form Styling */
        .task-form {
            width: 100%;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #b3b3b3;
        }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background-color: #333333;
            color: #e0e0e0;
            border: 1px solid #444444;
            border-radius: 4px;
            outline: none;
        }
        .form-group textarea {
            resize: vertical;
            height: 100px;
        }

        /* Button Styling */
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        .btn-submit,
        .btn-cancel {
            padding: 10px 20px;
            background-color: #0078d7;
            color: #e0e0e0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #005bb5;
        }
        .btn-cancel {
            background-color: #f44336;
        }
        .btn-cancel:hover {
            background-color: #d32f2f;
        }

        /* Alert Styling */
        .alert {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .error {
            background-color: #f44336;
        }
        .success {
            background-color: #4CAF50;
        }

        /* File Handling */
        .current-file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #2a2a2a;
            padding: 8px;
            border-radius: 5px;
            color: #b3b3b3;
        }
        .btn-download {
            padding: 5px 10px;
            background-color: #0078d7;
            color: #e0e0e0;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .btn-download:hover {
            background-color: #005bb5;
        }
        .delete-file label {
            margin-left: 10px;
            color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Edit Task</h1>
            <p class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </header>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="task-form">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $task_id); ?>" 
                  method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description"><?php echo htmlspecialchars($task['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Due Date:</label>
                    <input type="date" name="due_date" value="<?php echo $task['due_date']; ?>">
                </div>
                <div class="form-group">
                    <label>Status:</label>
                    <select name="status">
                        <option value="pending" <?php echo $task['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current Attachment:</label>
                    <?php if (!empty($task['file_name'])): ?>
                        <div class="current-file">
                            <span><?php echo htmlspecialchars($task['file_name']); ?></span>
                            <a href="download.php?task_id=<?php echo $task_id; ?>" class="btn-download">Download</a>
                            <label class="delete-file">
                                <input type="checkbox" name="delete_file" value="1">
                                Delete current file
                            </label>
                        </div>
                    <?php else: ?>
                        <p>No file attached</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>New Attachment:</label>
                    <input type="file" name="task_file">
                    <small>Max file size: 5MB. Allowed types: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Update Task</button>
                    <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
