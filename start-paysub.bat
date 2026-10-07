@echo off
cd /d "%~dp0paysub-api"
echo API local PaySub: http://127.0.0.1:8012
echo Abre otra terminal en paysub-web y ejecuta npm run dev -- --host 127.0.0.1 --port 5175
php -d extension=pdo_sqlite -S 127.0.0.1:8012 -t public
