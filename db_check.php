<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM skill_categories");
echo "Categories:\n";
print_r($stmt->fetchAll());

$stmt = $pdo->query("SELECT * FROM skills");
echo "\nSkills:\n";
print_r($stmt->fetchAll());
