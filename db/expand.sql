USE cpp_learning;

-- ─────────────────────────────────────────────
-- Fix hints: explain the concept, not the answer
-- ─────────────────────────────────────────────
UPDATE challenges SET hint = 'std::cout is the standard output stream. The << operator sends data to it, and string literals are printed character-by-character exactly as written.' WHERE order_num = 1;
UPDATE challenges SET hint = 'Trace through the variable declarations. What values are stored in a and b? The + operator works the same as in math.' WHERE order_num = 2;
UPDATE challenges SET hint = 'Multiple << operators chain output together with no separator. Each piece is printed immediately after the previous one.' WHERE order_num = 3;
UPDATE challenges SET hint = 'A for loop runs its body while the condition is true. Trace i from its initial value, incrementing each iteration, and note each value printed.' WHERE order_num = 4;
UPDATE challenges SET hint = 'A while loop checks its condition before each iteration. Trace x from 10 and watch what the -- operator does to it each time.' WHERE order_num = 5;
UPDATE challenges SET hint = 'The % operator returns the remainder after integer division. Think about what 7 % 2 equals, then decide which branch of the if-else executes.' WHERE order_num = 6;
UPDATE challenges SET hint = 'Trace the loop step by step. sum starts at 0 and += adds each element in order. There are 5 elements.' WHERE order_num = 7;
UPDATE challenges SET hint = 'When a function is called, substitute the argument into the parameter. The function body then computes and returns a result.' WHERE order_num = 8;
UPDATE challenges SET hint = 'The & operator gives the address of a variable. The * operator before a pointer dereferences it — reading the value stored at that memory address.' WHERE order_num = 9;
UPDATE challenges SET hint = 'When an object is created, its member variables are initialized with their default values. Calling a method executes that method''s code with access to those members.' WHERE order_num = 10;
UPDATE challenges SET hint = 'endl flushes the buffer and moves the cursor to the next line. The next cout starts printing from that new line.' WHERE order_num = 11;
UPDATE challenges SET hint = 'In C++, dividing two integers performs integer division — the result is truncated toward zero, discarding any fractional part.' WHERE order_num = 12;
UPDATE challenges SET hint = 'The % (modulo) operator gives the remainder of a division. For example, if you divide A by B, the remainder is what is left over.' WHERE order_num = 13;
UPDATE challenges SET hint = 'A char variable holds a single character. When printed with cout, it displays the character itself, not its numeric ASCII value.' WHERE order_num = 14;
UPDATE challenges SET hint = 'In C++, bool values are stored as 0 (false) or 1 (true). When printed with cout, they show their integer representation by default.' WHERE order_num = 15;
UPDATE challenges SET hint = 'Trace both loops simultaneously. The outer loop controls the first factor, the inner loop controls the second. What expression produces each printed value?' WHERE order_num = 16;
UPDATE challenges SET hint = 'A do-while loop always executes its body at least once before checking the condition. Trace n from 0 and count how many iterations satisfy the condition.' WHERE order_num = 17;
UPDATE challenges SET hint = 'The ternary operator evaluates the condition first. If it is true, the expression returns the first value; if false, the second value.' WHERE order_num = 18;
UPDATE challenges SET hint = 'A switch statement compares the expression against each case label. When a match is found, that case runs until a break or the end of the switch.' WHERE order_num = 19;
UPDATE challenges SET hint = 'The .length() method (or .size()) counts the number of characters in a string, not including any null terminator.' WHERE order_num = 20;
UPDATE challenges SET hint = 'The + operator on two std::string objects concatenates them — appending the right string directly to the end of the left one.' WHERE order_num = 21;
UPDATE challenges SET hint = 'The algorithm tracks a "current maximum". Starting at index 0, it checks each subsequent element and updates if a larger one is found.' WHERE order_num = 22;
UPDATE challenges SET hint = 'Passing by reference (&) means the function receives an alias to the original variable, not a copy. Any changes inside the function affect the caller.' WHERE order_num = 23;
UPDATE challenges SET hint = 'Trace the recursion by hand: fact(4) calls fact(3), which calls fact(2), and so on until the base case. Multiply the results back up the call chain.' WHERE order_num = 24;
UPDATE challenges SET hint = 'Adding 1 to a pointer moves it forward by one element (the size of the pointed-to type). *(p+1) dereferences the element one position after p.' WHERE order_num = 25;
UPDATE challenges SET hint = 'A default parameter is used automatically when the caller does not provide an argument for that parameter. Check whether greet() passes any argument.' WHERE order_num = 26;
UPDATE challenges SET hint = 'A constructor runs automatically when an object is created. It initializes the object''s member variables using the arguments passed at construction time.' WHERE order_num = 27;
UPDATE challenges SET hint = 'When a derived class defines a method with the same name as the base class, calling that method on a derived object uses the derived class''s version.' WHERE order_num = 28;
UPDATE challenges SET hint = 'push_back adds one element to the end of a vector, increasing its size by 1. size() returns the current number of elements.' WHERE order_num = 29;
UPDATE challenges SET hint = 'A lambda expression is an anonymous function. The [] capture list, (params) parameter list, and { body } define it. It can be stored in auto and called like a regular function.' WHERE order_num = 30;

-- ─────────────────────────────────────────────
-- 15 new challenges  (order_num 31–45)
-- ─────────────────────────────────────────────
INSERT INTO challenges (title, code, expected_output, difficulty, xp_reward, hint, category, order_num) VALUES

-- BEGINNER (5)
('Range-Based For',
'#include<iostream>\nusing namespace std;\nint main(){\n    int arr[] = {2, 4, 6};\n    for(auto x : arr)\n        cout << x << " ";\n    return 0;\n}',
'2 4 6 ',
'beginner', 15,
'A range-based for loop iterates over every element in a container or array in order. auto deduces the element type automatically.',
'loops', 31),

('Post-Increment',
'#include<iostream>\nusing namespace std;\nint main(){\n    int x = 5;\n    cout << x++ << " " << x;\n    return 0;\n}',
'5 6',
'beginner', 15,
'The post-increment operator x++ returns the current value of x, then increments it. By the time the second << runs, x has already been incremented.',
'basics', 32),

('Type Casting',
'#include<iostream>\nusing namespace std;\nint main(){\n    double x = 9.7;\n    cout << (int)x;\n    return 0;\n}',
'9',
'beginner', 15,
'A C-style cast (int)x truncates the decimal part — it does not round. The fractional portion is simply discarded.',
'basics', 33),

('Compound Assignment',
'#include<iostream>\nusing namespace std;\nint main(){\n    int x = 10;\n    x += 5;\n    x *= 2;\n    cout << x;\n    return 0;\n}',
'30',
'beginner', 15,
'Compound assignment operators modify the variable in place. Trace x step by step: first += applies, then *= applies to the updated value.',
'basics', 34),

('String Substr',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    string s = "Hello World";\n    cout << s.substr(6);\n    return 0;\n}',
'World',
'beginner', 15,
'substr(pos) returns the substring starting at position pos through the end. String positions are zero-indexed.',
'strings', 35),

-- INTERMEDIATE (6)
('Struct Fields',
'#include<iostream>\nusing namespace std;\nstruct Point { int x, y; };\nint main(){\n    Point p = {3, 4};\n    cout << p.x + p.y;\n    return 0;\n}',
'7',
'intermediate', 20,
'A struct groups related variables under one name. Access individual fields with the . (dot) operator.',
'basics', 36),

('Static Variable',
'#include<iostream>\nusing namespace std;\nvoid tick(){\n    static int n = 0;\n    n++;\n    cout << n << " ";\n}\nint main(){\n    tick(); tick(); tick();\n    return 0;\n}',
'1 2 3 ',
'intermediate', 20,
'A static local variable is initialized only once and retains its value between function calls — unlike a regular local variable which resets each call.',
'functions', 37),

('Function Overloading',
'#include<iostream>\nusing namespace std;\nint add(int a, int b){ return a+b; }\ndouble add(double a, double b){ return a+b; }\nint main(){\n    cout << add(2,3) << " " << add(1.5,2.5);\n    return 0;\n}',
'5 4',
'intermediate', 20,
'C++ selects the correct overload based on argument types. Trace which overload is called for each pair of arguments.',
'functions', 38),

('Map Lookup',
'#include<iostream>\n#include<map>\nusing namespace std;\nint main(){\n    map<string,int> m;\n    m["cpp"] = 30;\n    m["py"]  = 20;\n    cout << m["cpp"] - m["py"];\n    return 0;\n}',
'10',
'intermediate', 25,
'std::map stores key-value pairs. Subscript with m[key] to read or write a value. Arithmetic on the retrieved integers works as usual.',
'stl', 39),

('Vector Element',
'#include<iostream>\n#include<vector>\nusing namespace std;\nint main(){\n    vector<int> v = {1, 2, 3};\n    v.push_back(4);\n    cout << v[3];\n    return 0;\n}',
'4',
'intermediate', 20,
'push_back appends an element to the end of the vector. Vector indices are zero-based, so the fourth element is at index 3.',
'stl', 40),

('Pair',
'#include<iostream>\n#include<utility>\nusing namespace std;\nint main(){\n    pair<int,string> p = {1, "one"};\n    cout << p.first << " " << p.second;\n    return 0;\n}',
'1 one',
'intermediate', 20,
'std::pair holds exactly two values of potentially different types. Access them with .first and .second.',
'stl', 41),

-- ADVANCED (4)
('Template Function',
'#include<iostream>\nusing namespace std;\ntemplate<typename T>\nT maxVal(T a, T b){ return a > b ? a : b; }\nint main(){\n    cout << maxVal(3,7) << " " << maxVal(2.5,1.5);\n    return 0;\n}',
'7 2.5',
'advanced', 35,
'A function template generates a version of the function for each type it is called with. The compiler deduces T from the argument types at each call site.',
'modern-cpp', 42),

('Virtual Dispatch',
'#include<iostream>\nusing namespace std;\nclass Shape{\npublic:\n    virtual string name(){ return "Shape"; }\n};\nclass Circle : public Shape{\npublic:\n    string name(){ return "Circle"; }\n};\nint main(){\n    Shape* s = new Circle();\n    cout << s->name();\n    delete s;\n    return 0;\n}',
'Circle',
'advanced', 35,
'virtual enables runtime polymorphism. Even though s is a Shape*, the actual object is a Circle, so the Circle::name() override is called.',
'oop', 43),

('unique_ptr',
'#include<iostream>\n#include<memory>\nusing namespace std;\nint main(){\n    unique_ptr<int> p = make_unique<int>(99);\n    cout << *p;\n    return 0;\n}',
'99',
'advanced', 35,
'make_unique creates a heap-allocated object wrapped in a unique_ptr that owns it. Dereference with * just like a raw pointer.',
'modern-cpp', 44),

('Lambda Capture',
'#include<iostream>\nusing namespace std;\nint main(){\n    int base = 10;\n    auto add = [base](int x){ return base + x; };\n    cout << add(5);\n    return 0;\n}',
'15',
'advanced', 35,
'The capture list [base] captures the local variable by value, making it available inside the lambda body. The lambda is then called with argument 5.',
'modern-cpp', 45);
