<?php
function adventureQuestion(array $challenge, int $round): array {
    $questions = [
        'beginner' => [
            [
                'task' => 'Restore the output spell',
                'prompt' => 'Choose the stream operator that sends this text to the console.',
                'code' => 'cout ___ "Portal open";',
                'choices' => ['<<', '>>', '==', '&&'],
                'answer' => '<<',
                'explanation' => 'The insertion operator << sends a value to cout.',
                'hint' => 'For console output, the stream operator points from cout toward the value being printed.',
            ],
            [
                'task' => 'Repair the loop engine',
                'prompt' => 'Make the counter advance after each pass through the loop.',
                'code' => 'for (int step = 0; step < 4; step___) { ... }',
                'choices' => ['++', '--', '==', '!='],
                'answer' => '++',
                'explanation' => 'The increment operator ++ increases step by one.',
                'hint' => 'Use the operator that increases a number by one after each loop pass.',
            ],
            [
                'task' => 'Unlock the remainder rune',
                'prompt' => 'Pick the operator that gives the remainder of integer division.',
                'code' => 'int remainder = 17 ___ 5;',
                'choices' => ['%', '/', '*', '+'],
                'answer' => '%',
                'explanation' => 'The modulo operator % returns the division remainder.',
                'hint' => 'Integer division remainder has its own arithmetic operator in C++.',
            ],
            [
                'task' => 'Set the condition gate',
                'prompt' => 'The gate should open when score is at least 10.',
                'code' => 'if (score ___ 10) { openGate(); }',
                'choices' => ['>=', '<=', '==', '!='],
                'answer' => '>=',
                'explanation' => 'The >= operator includes values equal to 10 and greater.',
                'hint' => 'The phrase “at least” includes equality as well as larger values.',
            ],
            [
                'task' => 'Find the first array rune',
                'prompt' => 'C++ arrays start at index zero. Choose the first element index.',
                'code' => 'int firstRune = runes[___];',
                'choices' => ['0', '1', '-1', 'size()'],
                'answer' => '0',
                'explanation' => 'The first element in a C++ array is at index 0.',
                'hint' => 'C++ uses zero-based indexing, so count the first slot as zero.',
            ],
            [
                'task' => 'Finish the function',
                'prompt' => 'The function should send its computed value back to its caller.',
                'code' => 'int reward() { ___ 25; }',
                'choices' => ['return', 'break', 'continue', 'cout'],
                'answer' => 'return',
                'explanation' => 'return passes a function result back to its caller.',
                'hint' => 'A function sends a computed value back with a control-flow keyword.',
            ],
            [
                'task' => 'Add a string rune',
                'prompt' => 'Choose the method that adds text to the end of a string.',
                'code' => 'name.___(" Coder");',
                'choices' => ['append', 'remove', 'insert_at_end', 'length'],
                'answer' => 'append',
                'explanation' => 'std::string::append adds characters to the end of a string.',
                'hint' => 'Look for the string method whose name describes adding text after the current contents.',
            ],
            [
                'task' => 'Choose the loop boundary',
                'prompt' => 'Run the loop while index is less than the array length.',
                'code' => 'for (int i = 0; i ___ length; ++i)',
                'choices' => ['<', '>', '==', '='],
                'answer' => '<',
                'explanation' => 'The < condition stops before i reaches length.',
                'hint' => 'The loop should stop before the index becomes equal to the container length.',
            ],
        ],
        'intermediate' => [
            [
                'task' => 'Rebuild the function result',
                'prompt' => 'Make this function return the product of its two inputs.',
                'code' => 'return left ___ right;',
                'choices' => ['*', '+', '/', '%'],
                'answer' => '*',
                'explanation' => 'The multiplication operator * produces the product.',
                'hint' => 'The product is the result of multiplying the two inputs.',
            ],
            [
                'task' => 'Power the reference link',
                'prompt' => 'Update the original value through this reference.',
                'code' => 'void charge(int& energy) { energy ___= 5; }',
                'choices' => ['+', '-', '*', '/'],
                'answer' => '+',
                'explanation' => 'The += operator adds to the referenced variable in place.',
                'hint' => 'Use the compound assignment that adds a value to the existing one.',
            ],
            [
                'task' => 'Traverse the container',
                'prompt' => 'Choose the range-for separator used between the item and container.',
                'code' => 'for (const auto& item ___ inventory) { ... }',
                'choices' => [':', ';', '::', '->'],
                'answer' => ':',
                'explanation' => 'A range-based for loop uses a colon before the container.',
                'hint' => 'In a range-for loop, a punctuation mark separates the item from its range.',
            ],
            [
                'task' => 'Expand the vector',
                'prompt' => 'Add a new element to the end of this vector.',
                'code' => 'inventory.___(42);',
                'choices' => ['push_back', 'pop_back', 'front', 'clear'],
                'answer' => 'push_back',
                'explanation' => 'push_back appends one element to a vector.',
                'hint' => 'Choose the vector method that places a new element at its end.',
            ],
            [
                'task' => 'Follow the pointer path',
                'prompt' => 'Move the pointer forward by one integer element.',
                'code' => 'int* next = current ___ 1;',
                'choices' => ['+', '-', '*', '&'],
                'answer' => '+',
                'explanation' => 'Adding one to an int pointer advances by one int element.',
                'hint' => 'Pointer arithmetic advances to the next element by adding an offset.',
            ],
            [
                'task' => 'Bind the search result',
                'prompt' => 'Use the iterator type returned by find in this declaration.',
                'code' => '___ found = std::find(items.begin(), items.end(), target);',
                'choices' => ['auto', 'int', 'bool', 'void'],
                'answer' => 'auto',
                'explanation' => 'auto deduces the iterator type returned by std::find.',
                'hint' => 'Let the compiler infer the iterator type returned by the search.',
            ],
            [
                'task' => 'Count the characters',
                'prompt' => 'Choose the string method that reports how many characters it contains.',
                'code' => 'std::size_t count = message.___();',
                'choices' => ['size', 'append', 'clear', 'substr'],
                'answer' => 'size',
                'explanation' => 'std::string::size returns the number of characters.',
                'hint' => 'Use the string member that reports the number of stored characters.',
            ],
            [
                'task' => 'Capture the caller value',
                'prompt' => 'Capture amount by reference so the lambda can modify it.',
                'code' => 'auto spend = [___] (int cost) { amount -= cost; };',
                'choices' => ['&amount', 'amount', 'cost', 'this'],
                'answer' => '&amount',
                'explanation' => 'Capturing &amount lets the lambda modify the original variable.',
                'hint' => 'A lambda needs a reference capture to modify the caller’s original variable.',
            ],
        ],
        'advanced' => [
            [
                'task' => 'Connect the inheritance chain',
                'prompt' => 'Make Wizard publicly inherit the Character interface.',
                'code' => 'class Wizard ___ Character { ... };',
                'choices' => [': public', '->', 'extends', '::'],
                'answer' => ': public',
                'explanation' => 'C++ uses : public to declare public inheritance.',
                'hint' => 'C++ places a colon and an access level between a derived and base class.',
            ],
            [
                'task' => 'Make the spell polymorphic',
                'prompt' => 'Allow derived classes to override this base-class method.',
                'code' => '___ void cast() { ... }',
                'choices' => ['virtual', 'static', 'friend', 'inline'],
                'answer' => 'virtual',
                'explanation' => 'A virtual method supports dynamic dispatch and overriding.',
                'hint' => 'The base-class method needs the keyword that enables runtime dispatch.',
            ],
            [
                'task' => 'Forge a generic type',
                'prompt' => 'Complete the template declaration with its type parameter keyword.',
                'code' => 'template <___ T> T combine(T a, T b);',
                'choices' => ['typename', 'virtual', 'mutable', 'namespace'],
                'answer' => 'typename',
                'explanation' => 'typename declares a type parameter in a template.',
                'hint' => 'A template type parameter is introduced with a type-declaration keyword.',
            ],
            [
                'task' => 'Guard the resource',
                'prompt' => 'Choose the smart pointer that exclusively owns one object.',
                'code' => 'std::___<Artifact> artifact = std::make_unique<Artifact>();',
                'choices' => ['unique_ptr', 'shared_ptr', 'weak_ptr', 'raw_ptr'],
                'answer' => 'unique_ptr',
                'explanation' => 'unique_ptr represents exclusive ownership of an object.',
                'hint' => 'Choose the smart pointer whose ownership is exclusive rather than shared.',
            ],
            [
                'task' => 'Sort with a comparator',
                'prompt' => 'Make the comparator order a before b in ascending order.',
                'code' => 'return a.score ___ b.score;',
                'choices' => ['<', '>', '==', '!='],
                'answer' => '<',
                'explanation' => 'An ascending comparator returns true when a is less than b.',
                'hint' => 'Ascending order places a before b when a’s score is smaller.',
            ],
            [
                'task' => 'Move the heavy artifact',
                'prompt' => 'Transfer ownership of source into destination.',
                'code' => 'auto destination = std::___(source);',
                'choices' => ['move', 'copy', 'forward_to', 'swap_out'],
                'answer' => 'move',
                'explanation' => 'std::move enables move semantics for the source object.',
                'hint' => 'Use the standard-library utility that enables transfer of resources.',
            ],
            [
                'task' => 'Handle the missing value',
                'prompt' => 'Return an empty optional when the quest item is unavailable.',
                'code' => 'return ___;',
                'choices' => ['std::nullopt', 'nullptr', 'std::optional', 'std::empty'],
                'answer' => 'std::nullopt',
                'explanation' => 'std::nullopt represents an optional with no value.',
                'hint' => 'An empty optional is represented by a named standard-library constant.',
            ],
            [
                'task' => 'Choose the constant expression',
                'prompt' => 'Mark this function so constant inputs can be evaluated at compile time.',
                'code' => '___ int square(int value) { return value * value; }',
                'choices' => ['constexpr', 'volatile', 'extern', 'register'],
                'answer' => 'constexpr',
                'explanation' => 'constexpr permits compile-time evaluation when its inputs allow it.',
                'hint' => 'Choose the function specifier that permits compile-time evaluation.',
            ],
        ],
    ];

    $difficulty = $challenge['difficulty'] ?? '';
    if (!isset($questions[$difficulty]) || $round < 0) {
        throw new InvalidArgumentException('No portal puzzle exists for this challenge.');
    }

    $bank = $questions[$difficulty];
    $orderNumber = max(1, (int)($challenge['order_num'] ?? 1));
    $index = ($orderNumber - 1 + ($round * 3)) % count($bank);
    return $bank[$index];
}

function publicAdventureQuestion(array $challenge, int $round): array {
    $question = adventureQuestion($challenge, $round);
    unset($question['answer'], $question['explanation'], $question['hint']);
    return $question;
}
