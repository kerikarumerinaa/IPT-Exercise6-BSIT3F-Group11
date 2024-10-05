<?php
// Set response headers
header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Include database connection
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "validation";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Send response with a 500 status code if there's a connection error
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Handle POST requests for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $repeat_password = $_POST['repeatPassword'];
    $birthdate = $_POST['birthdate'];

    // Validate form data
    $errors = [];

    if (empty($first_name)) {
        $errors['first_name'] = "First name is required.";
    }

    if (empty($last_name)) {
        $errors['last_name'] = "Last name is required.";
    }

    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "Username can only contain letters, numbers, and underscores.";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }

    if ($password !== $repeat_password) {
        $errors['repeatPassword'] = "Passwords do not match.";
    }

    if (empty($birthdate)) {
        $errors['birthdate'] = "Birthdate is required.";
    } else {
        $age = date_diff(date_create($birthdate), date_create('today'))->y;
        if ($age < 18) {
            $errors['birthdate'] = "You must be at least 18 years old.";
        }
    }

    // If there are errors, return a 400 status code and the errors
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        $conn->close();
        exit();
    }

    // Validate if email or username already exists
    $sql_check = "SELECT * FROM infos WHERE email = ? OR username = ?";
    $stmt_check = $conn->prepare($sql_check);

    if ($stmt_check) {
        $stmt_check->bind_param("ss", $email, $username);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $row = $result_check->fetch_assoc();
            $errors = [];

            if ($row['email'] === $email) {
                $errors['email'] = "Email is already registered.";
            }
            if ($row['username'] === $username) {
                $errors['username'] = "Username is already taken.";
            }

            // Return 400 response with errors if either email or username is found
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(["success" => false, "errors" => $errors]);
                $stmt_check->close();
                $conn->close();
                exit();
            }
        }

        $stmt_check->close();
    } else {
        // Send response with a 500 status code for a query error
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database query error."]);
        $conn->close();
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert the user data into the database
    $sql = "INSERT INTO infos (first_name, last_name, username, email, password, birthdate) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ssssss", $first_name, $last_name, $username, $email, $hashed_password, $birthdate);

        if ($stmt->execute()) {
            // Return success response
            echo json_encode(["success" => true, "message" => "Registration successful."]);
        } else {
            // Send response with a 500 status code for insertion error
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to register."]);
        }

        $stmt->close();
    } else {
        // Send response with a 500 status code for query error
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Database query error."]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'input_click') {
    // Handle GET requests for input clicks
    $input = isset($_GET['input']) ? $_GET['input'] : '';
    
    // You can add any specific logic here for different input fields if needed
    $response = ["success" => true, "message" => "Clicked on $input"];
    
    echo json_encode($response);
} else {
    // Handle other unexpected requests
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method Not Allowed"]);
}

$conn->close();