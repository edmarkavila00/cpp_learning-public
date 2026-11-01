@echo off
setlocal
cd /d "%~dp0.."

echo Creating the private runner token outside the web root...
node "%~dp0setup.js"
if errorlevel 1 goto :failed

echo Building the isolated C++ compiler image...
docker build --tag cppquest-cpp-runner:local "%~dp0"
if errorlevel 1 goto :failed

echo.
echo Runner setup complete. Start Docker Desktop, then run start-runner.bat.
exit /b 0

:failed
echo.
echo Runner setup failed. Make sure Node.js and Docker Desktop are installed and Docker is running.
exit /b 1
