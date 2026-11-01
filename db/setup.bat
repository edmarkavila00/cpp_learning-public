@echo off
setlocal
echo Setting up CppQuest for XAMPP...
echo.

set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
set "USER=root"
set "PASS="
set "DB=cpp_learning"
set "DIR=%~dp0"
set "MYSQL_ARGS=-u%USER%"
if not "%PASS%"=="" set "MYSQL_ARGS=%MYSQL_ARGS% -p%PASS%"

if not exist "%MYSQL%" (
    echo ERROR: MySQL client not found at "%MYSQL%".
    echo Update MYSQL in this script to match your XAMPP installation.
    pause
    exit /b 1
)

"%MYSQL%" %MYSQL_ARGS% --execute="CREATE DATABASE IF NOT EXISTS %DB%"
if errorlevel 1 goto :failed

for /f "delims=" %%c in ('%MYSQL% %MYSQL_ARGS% --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '%DB%' AND table_name = 'challenges'"') do set "TABLE_EXISTS=%%c"
if not defined TABLE_EXISTS (
    echo ERROR: Could not read the challenge table. Check that MySQL is running and credentials are correct.
    pause
    exit /b 1
)
if "%TABLE_EXISTS%"=="0" goto :load_data

for /f "delims=" %%c in ('%MYSQL% %MYSQL_ARGS% --batch --skip-column-names --execute="SELECT COUNT(*) FROM %DB%.challenges"') do set "CHALLENGE_COUNT=%%c"
if not defined CHALLENGE_COUNT (
    echo ERROR: Could not read challenges from the database.
    pause
    exit /b 1
)
if not "%CHALLENGE_COUNT%"=="0" goto :already_seeded

:load_data
echo Loading schema and challenge data without deleting existing accounts...
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%schema.sql"
if errorlevel 1 goto :failed
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%more_challenges.sql"
if errorlevel 1 goto :failed
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%expand.sql"
if errorlevel 1 goto :failed
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%more_levels.sql"
if errorlevel 1 goto :failed
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%hints_layered.sql"
if errorlevel 1 goto :failed
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%challenges_30each.sql"
if errorlevel 1 goto :failed

:upgrade_features
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%features.sql"
if errorlevel 1 goto :failed

echo.
"%MYSQL%" %MYSQL_ARGS% --table %DB% --execute="SELECT difficulty, COUNT(*) AS challenges FROM challenges GROUP BY difficulty ORDER BY FIELD(difficulty, 'beginner', 'intermediate', 'advanced')"
echo.
echo Setup complete. Existing user accounts and progress were preserved.
pause
exit /b 0

:already_seeded
echo The database already contains %CHALLENGE_COUNT% challenges. Skipping seed import to avoid duplicates.
for /f "delims=" %%c in ('%MYSQL% %MYSQL_ARGS% --batch --skip-column-names --execute="SELECT COALESCE(MAX(order_num), 0) FROM %DB%.challenges"') do set "MAX_ORDER_NUM=%%c"
if not defined MAX_ORDER_NUM (
    echo ERROR: Could not read the highest challenge order.
    pause
    exit /b 1
)
if "%MAX_ORDER_NUM%"=="10" goto :upgrade_from_10
if "%MAX_ORDER_NUM%"=="30" goto :upgrade_from_30
if "%MAX_ORDER_NUM%"=="45" goto :upgrade_from_45
if "%MAX_ORDER_NUM%"=="75" goto :upgrade_from_75
goto :upgrade_features

:upgrade_from_10
echo Completing the challenge catalog from order 11...
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%more_challenges.sql"
if errorlevel 1 goto :failed

:upgrade_from_30
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%expand.sql"
if errorlevel 1 goto :failed

:upgrade_from_45
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%more_levels.sql"
if errorlevel 1 goto :failed

:upgrade_from_75
"%MYSQL%" %MYSQL_ARGS% --default-character-set=utf8mb4 %DB% < "%DIR%challenges_30each.sql"
if errorlevel 1 goto :failed
goto :upgrade_features

:failed
echo.
echo ERROR: Database setup failed. Review the MySQL error above.
pause
exit /b 1
