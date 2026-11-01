import json
import os
import resource
import subprocess
import sys
import tempfile

MAX_SOURCE_BYTES = 16_000
MAX_INPUT_BYTES = 512
MAX_OUTPUT_BYTES = 8_192
MAX_DIAGNOSTIC_BYTES = 6_000
MAX_TESTS = 8


def fail(message, kind="runner_error"):
    print(json.dumps({"kind": kind, "error": message}))
    raise SystemExit(0)


def decode_request():
    raw = sys.stdin.buffer.read(MAX_SOURCE_BYTES + MAX_TESTS * MAX_INPUT_BYTES + 16_384)
    if not raw:
        fail("No code was submitted.")
    try:
        request = json.loads(raw)
    except (UnicodeDecodeError, json.JSONDecodeError):
        fail("The runner received invalid request data.")

    source = request.get("source")
    tests = request.get("tests")
    if not isinstance(source, str) or not source.strip() or len(source.encode("utf-8")) > MAX_SOURCE_BYTES:
        fail("Code must contain between 1 and 16,000 UTF-8 bytes.")
    if not isinstance(tests, list) or not tests or len(tests) > MAX_TESTS:
        fail("The test suite is invalid.")
    for test in tests:
        if (
            not isinstance(test, dict)
            or not isinstance(test.get("input"), str)
            or not isinstance(test.get("expected"), str)
            or len(test["input"].encode("utf-8")) > MAX_INPUT_BYTES
            or len(test["expected"].encode("utf-8")) > MAX_OUTPUT_BYTES
        ):
            fail("A test case is invalid.")
    return source, tests


def normalized_output(value):
    return value.replace("\r\n", "\n").replace("\r", "\n").strip()


def limit_child_resources():
    resource.setrlimit(resource.RLIMIT_CPU, (2, 2))
    resource.setrlimit(resource.RLIMIT_FSIZE, (MAX_OUTPUT_BYTES, MAX_OUTPUT_BYTES))
    resource.setrlimit(resource.RLIMIT_AS, (128 * 1024 * 1024, 128 * 1024 * 1024))
    resource.setrlimit(resource.RLIMIT_NOFILE, (32, 32))
    resource.setrlimit(resource.RLIMIT_CORE, (0, 0))


def main():
    source, tests = decode_request()
    environment = {"PATH": "/usr/bin:/bin", "HOME": "/tmp", "LANG": "C.UTF-8"}

    with tempfile.TemporaryDirectory(prefix="cppq-") as workdir:
        source_path = os.path.join(workdir, "main.cpp")
        binary_path = os.path.join(workdir, "program")
        with open(source_path, "w", encoding="utf-8") as source_file:
            source_file.write(source)

        try:
            compilation = subprocess.run(
                ["g++", "-std=c++17", "-O0", "-pipe", "-Wall", "-Wextra", source_path, "-o", binary_path],
                cwd=workdir,
                env=environment,
                capture_output=True,
                text=True,
                timeout=15,
                check=False,
            )
        except subprocess.TimeoutExpired:
            fail("Compilation took too long. Simplify the program and try again.", "compile_timeout")

        if compilation.returncode != 0:
            diagnostic = (compilation.stderr or compilation.stdout)[-MAX_DIAGNOSTIC_BYTES:]
            fail(diagnostic or "Compilation failed. Check your C++ syntax.", "compile_error")

        results = []
        for index, test in enumerate(tests, start=1):
            output_path = os.path.join(workdir, f"output-{index}.txt")
            try:
                with open(output_path, "wb") as output_file:
                    process = subprocess.Popen(
                        [binary_path],
                        cwd=workdir,
                        env=environment,
                        stdin=subprocess.PIPE,
                        stdout=output_file,
                        stderr=subprocess.DEVNULL,
                        preexec_fn=limit_child_resources,
                    )
                    process.communicate(test["input"].encode("utf-8"), timeout=2)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
                fail(f"Test {index} timed out. Check for an infinite loop.", "runtime_error")
            except OSError:
                fail(f"Test {index} exceeded the output or process limits.", "runtime_error")

            if process.returncode != 0:
                fail(f"Your program exited unexpectedly on test {index}.", "runtime_error")
            if os.path.getsize(output_path) > MAX_OUTPUT_BYTES:
                fail(f"Test {index} produced too much output.", "runtime_error")
            with open(output_path, "r", encoding="utf-8", errors="replace") as output_file:
                actual = output_file.read(MAX_OUTPUT_BYTES + 1)

            passed = normalized_output(actual) == normalized_output(test["expected"])
            results.append({"passed": passed, "test": index, "actual": actual[-MAX_OUTPUT_BYTES:]})

        passed_count = sum(result["passed"] for result in results)
        print(json.dumps({
            "kind": "results",
            "passed": passed_count == len(tests),
            "passed_count": passed_count,
            "total_tests": len(tests),
            "results": results,
        }))


if __name__ == "__main__":
    main()
