/*
session_start();
include 'config.php';

// Security Guard Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['client_name'];
    $email = $_POST['client_email'];
    $user_id = $_SESSION['user_id']; // The link to the logged-in user

    try {
        // Store it in the memory
        $sql = "INSERT INTO clients (user_id, client_name, client_email) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $name, $email]);

        // SUCCESS: Jump back to the dashboard and tell it we succeeded
        header("Location: index.php?success=1");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>
<?php
session_start();
include 'config.php';

// Security Guard: kick unauthenticated requests back to the entrance
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>CRM Dashboard</title>
</head>
<body>

    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
    <a href="logout.php">Logout</a>
    <hr>

    <h3>Add a New Client</h3>
    
    <?php if (isset($_GET['success'])): ?>
        <p style="color: green; font-weight: bold;">✔ Client added successfully!</p>
    <?php endif; ?>

    <form action="add_client.php" method="POST">
        <input type="text" name="client_name" placeholder="Client Name" required>
        <br><br>
        <input type="email" name="client_email" placeholder="Client Email" required>
        <br><br>
        <button type="submit">Save Client</button>
    </form>

    <hr>

    <h3>Your Saved Clients</h3>
    <ul>
        <?php
        // Fetch only clients tied directly to this active account ID
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$current_user_id]);
        $clients = $stmt->fetchAll();

        if ($clients) {
            foreach ($clients as $client) {
                echo "<li>";
                echo "<strong>" . htmlspecialchars($client['client_name']) . "</strong> (" . htmlspecialchars($client['client_email']) . ")";
                echo "</li>";
            }
        } else {
            echo "<li>No clients saved yet. Add your first one above!</li>";
        }
        ?>
    </ul>

</body>
</html>