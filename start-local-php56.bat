@echo off
cd /d "%~dp0"
echo Menjalankan IPAK SKM di http://127.0.0.1:8001 (PHP 5.6)
C:\xampp\php\php.exe -c C:\xampp\php\php.ini -S 127.0.0.1:8001 router.php