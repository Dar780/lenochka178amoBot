<?php
require 'config.php';

if (!isset($_GET['id'])) {
    die("Ошибка: Не указан ID квартиры.");
}

$id = $_GET['id'];
$stmt = $db->prepare("SELECT * FROM apartments WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$apartment = $result->fetch_assoc();

if (!$apartment) {
    die("Ошибка: Квартира не найдена.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $db->prepare("UPDATE apartments SET realty_id=?, street=?, house_number=?, apartment_number=?, gate_code=?, intercom_code=?, keybox_code=?, entrance_number=?, floor_number=?, deposit_amount=?, cleaning_fee=?, bank=?, recipient=?, wifi_name=?, wifi_password=? WHERE id=?");
    $stmt->bind_param("sssssssssssssssi", 
        $_POST['realty_id'], 
        $_POST['street'], 
        $_POST['house_number'], 
        $_POST['apartment_number'], 
        $_POST['gate_code'], 
        $_POST['intercom_code'], 
        $_POST['keybox_code'],
        $_POST['entrance_number'],
        $_POST['floor_number'],
        $_POST['deposit_amount'], 
        $_POST['cleaning_fee'], 
        $_POST['bank'], 
        $_POST['recipient'],
        $_POST['wifi_name'],
        $_POST['wifi_password'],
        $id
    );
    $stmt->execute();
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать квартиру</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h2>Редактировать квартиру</h2>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Realty ID</label>
            <input type="text" name="realty_id" class="form-control" value="<?= htmlspecialchars($apartment['realty_id']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Улица</label>
            <input type="text" name="street" class="form-control" value="<?= htmlspecialchars($apartment['street']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Номер дома</label>
            <input type="text" name="house_number" class="form-control" value="<?= htmlspecialchars($apartment['house_number']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Номер квартиры</label>
            <input type="text" name="apartment_number" class="form-control" value="<?= htmlspecialchars($apartment['apartment_number']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Код калитки</label>
            <input type="text" name="gate_code" class="form-control" value="<?= htmlspecialchars($apartment['gate_code']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Код домофона</label>
            <input type="text" name="intercom_code" class="form-control" value="<?= htmlspecialchars($apartment['intercom_code']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Кейбокс</label>
            <input type="text" name="keybox_code" class="form-control" value="<?= htmlspecialchars($apartment['keybox_code']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Подъезд</label>
            <input type="text" name="entrance_number" class="form-control" value="<?= htmlspecialchars($apartment['entrance_number']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Этаж</label>
            <input type="text" name="floor_number" class="form-control" value="<?= htmlspecialchars($apartment['floor_number']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Залог (руб.)</label>
            <input type="text" name="deposit_amount" class="form-control" value="<?= htmlspecialchars($apartment['deposit_amount']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Стоимость уборки (руб.)</label>
            <input type="text" name="cleaning_fee" class="form-control" value="<?= htmlspecialchars($apartment['cleaning_fee']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Банк</label>
            <input type="text" name="bank" class="form-control" value="<?= htmlspecialchars($apartment['bank']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Получатель</label>
            <input type="text" name="recipient" class="form-control" value="<?= htmlspecialchars($apartment['recipient']) ?>" required>
        </div>
        <!-- Новые поля -->
        <div class="mb-3">
            <label class="form-label">WiFi имя</label>
            <input type="text" name="wifi_name" class="form-control" value="<?= htmlspecialchars($apartment['wifi_name']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">WiFi пароль</label>
            <input type="text" name="wifi_password" class="form-control" value="<?= htmlspecialchars($apartment['wifi_password']) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="index.php" class="btn btn-secondary">Назад</a>
    </form>
</body>
</html>
