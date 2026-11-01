# CppQuest C++ Code Lab runner

The Code Lab compiles and tests submitted C++17 programs in disposable Docker containers. The runner is a local Node.js service; it listens only on `127.0.0.1`, and the PHP app sends it a private token stored outside the web root.

## Requirements

- Docker Desktop using Linux containers
- Node.js 18 or newer
- XAMPP PHP with the cURL extension enabled

## Setup on Windows/XAMPP

1. Start Docker Desktop and wait for it to finish starting.
2. Run `code_runner\setup-runner.bat` once from Explorer or a terminal. It creates `C:\xampp\cpp_learning_runner.json` (outside `htdocs`) and builds the isolated compiler image.
3. Run `db\setup.bat` to apply the `code_game_progress` database migration. Existing accounts and challenge progress are preserved.
4. Start XAMPP Apache and MySQL.
5. Run `code_runner\start-runner.bat` and leave its terminal open while playing.
6. Open **Code Lab** from the game navigation or dashboard.

If this project is installed somewhere other than `C:\xampp\htdocs\cpp_learning`, update the config path in `includes\code_runner_client.php`, `code_runner\setup.js`, and `code_runner\server.js` so they all point to the same JSON file outside the web root.

To rotate the private token, stop the runner, remove the config file, rerun setup, and restart the runner.

## Isolation limits

Each run gets a fresh container with no network, a read-only root filesystem, a small executable temporary filesystem, an unprivileged user, no Linux capabilities, a default seccomp profile, and CPU, memory, process, time, and output limits. Do not expose the runner port publicly or mount host files into the compiler container.
