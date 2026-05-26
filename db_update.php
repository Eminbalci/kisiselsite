<?php
require_once 'includes/db.php';
$updates = [
    'UI/UX Tasarım' => 'UI/UX Design',
    'Diğer Araçlar' => 'Other Tools',
    'Dil' => 'Languages',
    'Veri Tabanı' => 'Database',
    '3d Modelleme' => '3D Modeling',
    'Frontend' => 'Frontend',
    'Backend' => 'Backend'
];
foreach ($updates as $tr => $en) {
    $pdo->prepare("UPDATE skill_categories SET name_en = ? WHERE name = ?")->execute([$en, $tr]);
}

// Update missing skills name_en just in case
$pdo->prepare("UPDATE skills SET name_en = 'C#' WHERE name = 'C#'")->execute();
$pdo->prepare("UPDATE skills SET name_en = 'Flutter' WHERE name = 'Flutter'")->execute();

echo "Database updated.\n";
