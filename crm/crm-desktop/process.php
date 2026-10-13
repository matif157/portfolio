<?php
// Replace these variables with your actual database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "quiz";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quizInput = $_POST["quizInput"];

    echo  $quizInput."<br/>";
    // Split input into questions and options
    $questionsAndOptions = explode("\n\n", $quizInput);

    // Insert each question and options into the database
    foreach ($questionsAndOptions as $questionAndOptions) {
        list($questionText, $optionsText) = explode("\n", $questionAndOptions, 2);

        echo $questionText;
        // Insert question into the questions table
        $stmt = $conn->prepare("INSERT INTO questions (question_text) VALUES (?)");
        $stmt->bind_param("s", $questionText);
        $stmt->execute();

        $questionId = $stmt->insert_id;

        // Insert options into the questions table
        $options = explode("\n", $optionsText);
        
        // Insert options into op1, op2, op3, and op4 columns
        $stmt = $conn->prepare("UPDATE questions SET op1 = ?, op2 = ?, op3 = ?, op4 = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $options[0], $options[1], $options[2], $options[3], $questionId);
        $stmt->execute();
    }

    echo "Questions and options have been successfully stored in the database.";
}

// Close the connection
$conn->close();
?>
