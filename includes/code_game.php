<?php
function codeGameProblems(): array {
    return [
        'sum-two-numbers' => [
            'title' => 'Restore the Power Core',
            'difficulty' => 'Beginner',
            'description' => 'Read two integers from standard input and print their sum.',
            'objective' => 'The core accepts two signed whole numbers. Output exactly one number: their sum.',
            'sample_input' => "8 13\n",
            'sample_output' => "21\n",
            'starter_code' => "#include <iostream>\nusing namespace std;\n\nint main() {\n    // Read two integers and print their sum.\n    return 0;\n}\n",
            'tests' => [
                ['input' => "8 13\n", 'expected' => "21\n"],
                ['input' => "-9 4\n", 'expected' => "-5\n"],
                ['input' => "0 0\n", 'expected' => "0\n"],
                ['input' => "125 375\n", 'expected' => "500\n"],
            ],
        ],
        'count-even-numbers' => [
            'title' => 'Charge the Reactor',
            'difficulty' => 'Beginner',
            'description' => 'Count how many numbers in a list are even.',
            'objective' => 'The first input is n, followed by n integers. Print the number of even integers.',
            'sample_input' => "5\n2 7 8 3 10\n",
            'sample_output' => "3\n",
            'starter_code' => "#include <iostream>\nusing namespace std;\n\nint main() {\n    // Read n numbers and count the even values.\n    return 0;\n}\n",
            'tests' => [
                ['input' => "5\n2 7 8 3 10\n", 'expected' => "3\n"],
                ['input' => "4\n1 3 5 7\n", 'expected' => "0\n"],
                ['input' => "3\n-2 0 9\n", 'expected' => "2\n"],
                ['input' => "1\n42\n", 'expected' => "1\n"],
            ],
        ],
        'reverse-word' => [
            'title' => 'Decode the Signal',
            'difficulty' => 'Intermediate',
            'description' => 'Read one word and print its characters in reverse order.',
            'objective' => 'The input contains one word with no spaces. Print the reversed word.',
            'sample_input' => "quest\n",
            'sample_output' => "tseuq\n",
            'starter_code' => "#include <iostream>\n#include <string>\nusing namespace std;\n\nint main() {\n    // Read one word and print it in reverse.\n    return 0;\n}\n",
            'tests' => [
                ['input' => "quest\n", 'expected' => "tseuq\n"],
                ['input' => "Cplusplus\n", 'expected' => "sulpsulpC\n"],
                ['input' => "x\n", 'expected' => "x\n"],
                ['input' => "levelup\n", 'expected' => "pulevel\n"],
            ],
        ],
    ];
}
