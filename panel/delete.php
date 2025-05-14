<?php
require 'config.php';

if (isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM apartments WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
}

header("Location: index.php");
?>
