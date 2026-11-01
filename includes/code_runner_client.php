<?php
function codeRunnerConfig(): array {
    $configPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'cpp_learning_runner.json';
    if (!is_file($configPath)) {
        throw new RuntimeException('The C++ runner is not configured. Run code_runner\\setup-runner.bat and start the runner service.');
    }

    $config = json_decode((string)file_get_contents($configPath), true);
    if (!is_array($config) || !isset($config['token']) || !is_string($config['token']) || strlen($config['token']) < 32) {
        throw new RuntimeException('The C++ runner configuration is invalid. Run code_runner\\setup-runner.bat again.');
    }

    return $config;
}

function runCodeGameTests(string $source, array $tests): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required to connect to the local C++ runner.');
    }

    $config = codeRunnerConfig();
    $handle = curl_init('http://127.0.0.1:8787/run');
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the C++ runner connection.');
    }

    $payload = json_encode(['source' => $source, 'tests' => $tests], JSON_THROW_ON_ERROR);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['token'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
    ]);

    $responseBody = curl_exec($handle);
    $curlError = curl_error($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if ($responseBody === false) {
        throw new RuntimeException('The local C++ runner is unavailable. Start it with code_runner\\start-runner.bat.');
    }
    if ($status < 200 || $status >= 300) {
        $response = json_decode($responseBody, true);
        $message = is_array($response) && isset($response['error']) && is_string($response['error'])
            ? $response['error']
            : ($curlError !== '' ? $curlError : 'The C++ runner returned an unexpected response.');
        throw new RuntimeException($message);
    }

    $result = json_decode($responseBody, true);
    if (!is_array($result) || !isset($result['passed'], $result['passed_count'], $result['total_tests'])) {
        throw new RuntimeException('The C++ runner returned an invalid response.');
    }

    return $result;
}
