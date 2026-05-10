# Locker-rental-using-Redis
 
Sistema web para la administración y renta de lockers mediante una fila virtual en tiempo real utilizando Redis y PHP.
 
---
 
## Características
 
- Fila virtual FIFO en tiempo real
- Gestión automática de turnos
- Selección interactiva de lockers
- Panel de administración
- Persistencia en MySQL
- Logs en JSON
- Cache y sincronización con Redis
---
 
## Arquitectura
 
El sistema sigue una arquitectura monolítica modular:
 
- **Frontend:** HTML, CSS, JavaScript, jQuery
- **Backend:** PHP
- **Base de datos:** MySQL
- **Cola y cache:** Redis
- **Servidor web:** Apache
---
 
## Stack Tecnológico
 
- PHP 7.4+
- MySQL
- Redis 6+
- Apache
- JavaScript
- jQuery 3.4.1
---
 
## Instalación
 
### 1. Clonar el repositorio
 
```bash
git clone ...
```
 
### 2. Configurar Apache
 
```bash
sudo chown -R www-data:www-data /var/www/html/panel_lockers
sudo chmod -R 775 /var/www/html/panel_lockers
```
 
### 3. Iniciar Redis
 
```bash
sudo systemctl start redis
```
 
### 4. Configurar MySQL
 
- Crear la base de datos
- Importar el script SQL
---
 
## Estructura del Proyecto
 
```
panel_lockers/
renta_lockers/
locker_logs/
```
 
---
 
## Funcionamiento General
 
1. El alumno inicia sesión
2. Entra a la fila virtual
3. Redis administra la cola FIFO
4. El alumno selecciona un locker
5. La reserva se guarda en MySQL
6. Los eventos se registran en JSON
---
 
## Estados en Redis
 
| Clave | Descripción |
|---|---|
| `config:estado_sistema` | Estado global del sistema |
| `contador:turno` | Contador de turnos asignados |
| `locker` | Estado de los lockers |
| `locker:seleccionando` | Locker en proceso de selección |
 
---
 
## Logs
 
Los eventos del sistema se registran en formato JSON dentro de:
 
```
locker_logs/
```
 
---
 
## Problemas Conocidos
 
- Problemas de permisos en Linux
- Cola automática detenida
- Archivos JSON sin permisos
 
