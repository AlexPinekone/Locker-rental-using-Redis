# Locker-rental-using-Redis

A web-based system for managing and renting lockers through a real-time virtual queue using Redis and PHP.

---

## Features

- Real-time FIFO virtual queue
- Automatic turn management
- Interactive locker selection
- Administration panel
- MySQL persistence
- JSON logging
- Redis cache and synchronization

---

## Architecture

The system follows a modular monolithic architecture:

- **Frontend:** HTML, CSS, JavaScript, jQuery
- **Backend:** PHP
- **Database:** MySQL
- **Queue & cache:** Redis
- **Web server:** Apache

---

## Tech Stack

- PHP 7.4+
- MySQL
- Redis 6+
- Apache
- JavaScript
- jQuery 3.4.1

---

## Installation

### 1. Clone the repository

```bash
git clone ...
```

### 2. Configure Apache

```bash
sudo chown -R www-data:www-data /var/www/html/panel_lockers
sudo chmod -R 775 /var/www/html/panel_lockers
```

### 3. Start Redis

```bash
sudo systemctl start redis
```

### 4. Configure MySQL

- Create the database
- Import the SQL script

---

## Project Structure

```
panel_lockers/
renta_lockers/
locker_logs/
```

---

## General Workflow

1. The student logs in
2. Enters the virtual queue
3. Redis manages the FIFO queue
4. The student selects a locker
5. The reservation is saved in MySQL
6. Events are recorded in JSON

---

## Redis Keys

| Key | Description |
|---|---|
| `config:estado_sistema` | Global system state |
| `contador:turno` | Assigned turn counter |
| `locker` | Locker status |
| `locker:seleccionando` | Locker being selected |

---

## Logs

System events are recorded in JSON format inside:

```
locker_logs/
```

---

## Known Issues

- Permission issues on Linux
- Automatic queue stopped
- JSON files without write permissions
 
