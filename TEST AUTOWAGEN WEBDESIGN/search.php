<?php
$q = $_GET['q'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results</title>

    <link rel="stylesheet" href="CSS/style.css">
</head>

<body>

<div class="navbar">
    <div class="nav-left">
        <h2>AUTO WAGEN</h2>
    </div>
</div>

<div style="padding: 20px;">
    <h2>Results for: "<?php echo htmlspecialchars($q); ?>"</h2>

    <hr><br>

    <?php
    // TEMP DATA (we will replace with DB next)
    $parts = [
        "Toyota Corolla Headlight",
        "BMW E90 Engine",
        "VW Golf Door",
        "Ford Ranger Mirror",
        "Toyota Hilux Bumper",
        "Audi A4 Gearbox"
    ];

    $found = false;

    foreach ($parts as $part) {
        if (stripos($part, $q) !== false) {
            echo "<p>$part</p>";
            $found = true;
        }
    }

    if (!$found) {
        echo "<p>No parts found.</p>";
    }
    ?>
</div>

</body>
</html>