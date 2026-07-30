@echo off
cd /d "%~dp0"
echo Menjalankan IPAK SKM di http://127.0.0.1:8001
C:\xampp\php\php.exe -d extension_dir=C:\xampp\php\ext -S 127.0.0.1:8001 router.php
