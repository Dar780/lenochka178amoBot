# Соответствие полей AmoCRM и их field_id

## 🚨 ВАЖНО! 
**Если какие-то поля отсутствуют в AmoCRM, вебхук будет падать с ошибкой!**

## Обязательные поля в AmoCRM (должны быть созданы):

### 💰 Финансовые расчёты:
- **field_id: 850717** - Общая сумма брони (`amount` из JSON)
- **field_id: 848327** - Внесённая оплата (`prepaid_amount` из JSON) 
- **field_id: 850719** - Остаток к доплате (расчётное: amount - prepaid_amount)

### 🏠 Данные квартиры (из локальной БД):
- **field_id: 852841** - Улица (`street`)
- **field_id: 852843** - Номер дома (`house_number`)
- **field_id: 852845** - Номер квартиры (`apartment_number`)
- **field_id: 852847** - Код калитки (`gate_code`)
- **field_id: 852849** - Код домофона (`intercom_code`)
- **field_id: 852851** - Размер депозита (`deposit_amount`)
- **field_id: 852853** - Стоимость уборки (`cleaning_fee`)
- **field_id: 852855** - Банк (`bank`)
- **field_id: 852857** - Получатель (`recipient`)

### 📶 WiFi данные:
- **field_id: 873617** - Название WiFi (`wifi_name`)
- **field_id: 873619** - Пароль WiFi (`wifi_password`)

### 🆕 Новые поля (добавлены в последнем коммите):
- **field_id: 977279** - Код кейбокса (`keybox_code`) → 'RCS кейбокс'
- **field_id: 977281** - Номер подъезда (`entrance_number`) → 'RCS подъезд'
- **field_id: 977283** - Номер этажа (`floor_number`) → 'RCS этаж'

## 📋 Образец JSON от RealtyCalendar API:

```json
{
    "id": 102431903,
    "begin_date": "2025-03-04",
    "end_date": "2025-03-07", 
    "status": "booked",
    "apartment_id": 258337,
    "amount": 6000,
    "prepaid_amount": 100,
    "client": {
        "fio": "Дарья",
        "phone": "+7 962 702-66-44"
    },
    "lead_id": 33158599
}
```

## ❌ Что происходит если поля нет в AmoCRM:

1. AmoCRM API вернёт ошибку 400/422
2. handler.php не сможет обновить сделку
3. Вебхук "зависнет" без ответа
4. Расчёты не попадут в сделку

## ✅ Как проверить и создать поля:

1. **Зайти в AmoCRM** → Настройки → Поля → Сделки
2. **Проверить наличие всех field_id** из списка выше
3. **Создать недостающие поля** с правильными типами:
   - Финансовые поля → Число
   - Адресные поля → Текст
   - Коды → Текст

## 🔧 Для диагностики:

```bash
# Проверить последние ошибки в логах:
tail -f amo/handler_log.txt
tail -f amo/php.log

# Проверить структуру БД:
mysql -u admin -p apartments -e "DESCRIBE apartments;"
``` 