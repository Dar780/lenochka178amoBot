<?php
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $db->prepare("INSERT INTO apartments (realty_id, street, house_number, apartment_number, gate_code, intercom_code, keybox_code, entrance_number, floor_number, deposit_amount, cleaning_fee, bank, recipient, wifi_name, wifi_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssssss", 
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
        $_POST['wifi_password']
    );
    $stmt->execute();
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить квартиру</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h2>Добавить квартиру</h2>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Realty ID</label>
            <input type="text" name="realty_id" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Улица</label>
            <input type="text" name="street" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Номер дома</label>
            <input type="text" name="house_number" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Номер квартиры</label>
            <input type="text" name="apartment_number" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Код калитки</label>
            <input type="text" name="gate_code" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Код домофона</label>
            <input type="text" name="intercom_code" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Кейбокс</label>
            <input type="text" name="keybox_code" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Подъезд</label>
            <input type="text" name="entrance_number" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Этаж</label>
            <input type="text" name="floor_number" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Залог (руб.)</label>
            <input type="text" name="deposit_amount" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Стоимость уборки (руб.)</label>
            <input type="text" name="cleaning_fee" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Банк</label>
            <input type="text" name="bank" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Получатель</label>
            <input type="text" name="recipient" class="form-control" required>
        </div>
        <!-- Новые поля -->
        <div class="mb-3">
            <label class="form-label">WiFi имя</label>
            <input type="text" name="wifi_name" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">WiFi пароль</label>
            <input type="text" name="wifi_password" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Добавить</button>
    </form>
</body>
</html>
