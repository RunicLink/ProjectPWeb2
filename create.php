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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = sanitize($conn, $_POST['title']);
    $description = sanitize($conn, $_POST['description']);
    $due_date = sanitize($conn, $_POST['due_date']);
    $status = sanitize($conn, $_POST['status']);
    $user_id = $_SESSION['user_id'];
    
    $file_name = $file_path = "";
    if (isset($_FILES['task_file'])) {
        $upload_result = handleFileUpload($_FILES['task_file']);
        
        if (!$upload_result['success'] && isset($upload_result['errors'])) {
            $_SESSION['error_message'] = implode(", ", $upload_result['errors']);
            header("Location: create.php");
            exit;
        }
        
        if ($upload_result['success'] && isset($upload_result['file_name'])) {
            $file_name = $upload_result['file_name'];
            $file_path = $upload_result['file_path'];
        }
    }
    
    $sql = "INSERT INTO tasks (user_id, title, description, due_date, status, file_name, file_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("issssss", $user_id, $title, $description, $due_date, $status, $file_name, $file_path);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Task added successfully!";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to add task: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Task - Todo List</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #121212;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            width: 400px;
            padding: 20px;
            background-color: #1e1e1e;
            border-radius: 8px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.5);
        }

        header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            width: 100%;
            align-items: center;
        }

        .header-content h1 {
            font-size: 24px;
            color: #ffffff;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn-back, .btn-logout {
            background-color: #007bff;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-back:hover, .btn-logout:hover {
            background-color: #0056b3;
        }

        .welcome-message {
            font-size: 14px;
            color: #b3b3b3;
            margin-top: 10px;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
        }

        .error {
            background-color: #dc3545;
            color: #ffffff;
        }

        .task-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: #b3b3b3;
            margin-bottom: 5px;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea,
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            color: #ffffff;
            background-color: #333333;
            border: 1px solid #444444;
            border-radius: 4px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            color: #b3b3b3;
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .btn-submit, .btn-cancel {
            width: 48%;
            padding: 10px;
            font-size: 16px;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-submit {
            background-color: #28a745;
        }

        .btn-submit:hover {
            background-color: #218838;
        }

        .btn-cancel {
            background-color: #dc3545;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-cancel:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1>Create New Task</h1>
            </div>
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                  method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>Due Date:</label>
                    <input type="date" name="due_date">
                </div>
                <div class="form-group">
                    <label>Status:</label>
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Attachment:</label>
                    <input type="file" name="task_file">
                    <small>Max file size: 5MB. Allowed types: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Create Task</button>
                    <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
