# Asistente de Datos con Gemini + Laravel

Asistente inteligente que permite realizar consultas, reportes y gráficos sobre una base de datos MySQL usando lenguaje natural, impulsado por Google Gemini.

## Requisitos

- XAMPP (PHP 8.1+ y MySQL)
- Composer
- API Key de Google Gemini: https://aistudio.google.com/apikey

## Cómo ejecutarlo

### 1. Abrir el proyecto

```bash
cd reportes-gemini-api
2. Instalar dependenciasBashcomposer install3. Configurar el archivo .envenvDB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_de_tu_base
DB_USERNAME=root
DB_PASSWORD=

GEMINI_API_KEY=tu_api_key_aqui
Ejecutar:
php artisan config:clear

4. Encender MySQL
Abrir XAMPP Control Panel y asegurarse de que MySQL esté en Running.

5. Iniciar el servidor
php artisan serve

6. Abrir el chat
Entrar a: http://127.0.0.1:8000/chat


