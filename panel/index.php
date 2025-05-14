<?php
require 'config.php';

$result = $db->query("SELECT * FROM apartments");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Квартиры</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function confirmDelete(id) {
            if (confirm("Вы уверены, что хотите удалить эту квартиру?")) {
                window.location.href = 'delete.php?id=' + id;
            }
        }
    </script>
</head>
<body class="container mt-4">
    <h2 class="mb-3">Список квартир</h2>
    <a href="add.php" class="btn btn-success mb-3">Добавить квартиру</a>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Realty ID</th>
                    <th>Адрес</th>
                    <th>Коды</th>
                    <th>WiFi имя</th>
                    <th>WiFi пароль</th>
                    <th>Залог</th>
                    <th>Уборка</th>
                    <th>Банк</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['realty_id']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['street']) ?>, <?= htmlspecialchars($row['house_number']) ?>, кв. <?= htmlspecialchars($row['apartment_number']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['gate_code']) ?> / <?= htmlspecialchars($row['intercom_code']) ?>
                        </td>
                        <td><?= htmlspecialchars($row['wifi_name']) ?></td>
                        <td><?= htmlspecialchars($row['wifi_password']) ?></td>
                        <td><?= htmlspecialchars($row['deposit_amount']) ?> руб.</td>
                        <td><?= htmlspecialchars($row['cleaning_fee']) ?> руб.</td>
                        <td><?= htmlspecialchars($row['bank']) ?> (<?= htmlspecialchars($row['recipient']) ?>)</td>
                        <td>
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">✏️</a>
                            <button onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-danger btn-sm">🗑️</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
