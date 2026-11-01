USE cpp_learning;

-- 20 more challenges
INSERT INTO challenges (title, code, expected_output, difficulty, xp_reward, hint, category, order_num) VALUES

-- BEGINNER
('Newline Output',
'#include<iostream>\nusing namespace std;\nint main(){\n    cout << "Hello" << endl;\n    cout << "World";\n    return 0;\n}',
'Hello\nWorld', 'beginner', 10, 'endl moves to the next line', 'basics', 11),

('Integer Division',
'#include<iostream>\nusing namespace std;\nint main(){\n    cout << 7 / 2;\n    return 0;\n}',
'3', 'beginner', 10, 'Integer division truncates the decimal', 'basics', 12),

('Modulo Operator',
'#include<iostream>\nusing namespace std;\nint main(){\n    cout << 10 % 3;\n    return 0;\n}',
'1', 'beginner', 10, 'Modulo gives the remainder after division', 'basics', 13),

('Char Output',
'#include<iostream>\nusing namespace std;\nint main(){\n    char c = \'A\';\n    cout << c;\n    return 0;\n}',
'A', 'beginner', 10, 'A char stores a single character', 'basics', 14),

('Boolean Output',
'#include<iostream>\nusing namespace std;\nint main(){\n    bool x = true;\n    cout << x;\n    return 0;\n}',
'1', 'beginner', 10, 'true prints as 1, false as 0', 'basics', 15),

('Nested Loop',
'#include<iostream>\nusing namespace std;\nint main(){\n    for(int i=1;i<=2;i++)\n        for(int j=1;j<=2;j++)\n            cout << i*j << " ";\n    return 0;\n}',
'1 2 2 4 ', 'beginner', 15, 'Outer loop runs twice, inner loop runs twice each time', 'loops', 16),

('Do While',
'#include<iostream>\nusing namespace std;\nint main(){\n    int n = 0;\n    do {\n        cout << n << " ";\n        n++;\n    } while(n < 3);\n    return 0;\n}',
'0 1 2 ', 'beginner', 15, 'do-while always runs at least once', 'loops', 17),

('Ternary Operator',
'#include<iostream>\nusing namespace std;\nint main(){\n    int x = 10;\n    cout << (x > 5 ? "big" : "small");\n    return 0;\n}',
'big', 'beginner', 15, 'condition ? value_if_true : value_if_false', 'conditions', 18),

('Switch Case',
'#include<iostream>\nusing namespace std;\nint main(){\n    int day = 2;\n    switch(day){\n        case 1: cout << "Mon"; break;\n        case 2: cout << "Tue"; break;\n        default: cout << "Other";\n    }\n    return 0;\n}',
'Tue', 'beginner', 15, 'switch matches the value to a case', 'conditions', 19),

-- INTERMEDIATE
('String Length',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    string s = "Hello";\n    cout << s.length();\n    return 0;\n}',
'5', 'intermediate', 20, 'length() returns the number of characters', 'strings', 20),

('String Concat',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    string a = "C++";\n    string b = " Rocks";\n    cout << a + b;\n    return 0;\n}',
'C++ Rocks', 'intermediate', 20, 'Strings can be joined with the + operator', 'strings', 21),

('Array Max',
'#include<iostream>\nusing namespace std;\nint main(){\n    int a[] = {3,7,2,9,4};\n    int mx = a[0];\n    for(int i=1;i<5;i++)\n        if(a[i]>mx) mx=a[i];\n    cout << mx;\n    return 0;\n}',
'9', 'intermediate', 20, 'Track the largest value seen so far', 'arrays', 22),

('Pass by Reference',
'#include<iostream>\nusing namespace std;\nvoid addTen(int &n){ n += 10; }\nint main(){\n    int x = 5;\n    addTen(x);\n    cout << x;\n    return 0;\n}',
'15', 'intermediate', 20, 'Passing by reference modifies the original variable', 'functions', 23),

('Recursive Factorial',
'#include<iostream>\nusing namespace std;\nint fact(int n){ return n<=1 ? 1 : n*fact(n-1); }\nint main(){\n    cout << fact(4);\n    return 0;\n}',
'24', 'intermediate', 25, '4! = 4 * 3 * 2 * 1', 'functions', 24),

('Pointer Arithmetic',
'#include<iostream>\nusing namespace std;\nint main(){\n    int arr[] = {10,20,30};\n    int* p = arr;\n    cout << *(p+1);\n    return 0;\n}',
'20', 'intermediate', 25, 'p+1 moves to the next element in the array', 'pointers', 25),

('Default Parameter',
'#include<iostream>\nusing namespace std;\nvoid greet(string name="World"){\n    cout << "Hello " << name;\n}\nint main(){\n    greet();\n    return 0;\n}',
'Hello World', 'intermediate', 20, 'Default parameters are used when no argument is passed', 'functions', 26),

-- ADVANCED
('Constructor',
'#include<iostream>\nusing namespace std;\nclass Car{\npublic:\n    string brand;\n    Car(string b){ brand=b; }\n};\nint main(){\n    Car c("Toyota");\n    cout << c.brand;\n    return 0;\n}',
'Toyota', 'advanced', 30, 'Constructor is called when the object is created', 'oop', 27),

('Inheritance',
'#include<iostream>\nusing namespace std;\nclass Animal{\npublic:\n    void speak(){ cout << "..."; }\n};\nclass Cat : public Animal{\npublic:\n    void speak(){ cout << "Meow"; }\n};\nint main(){\n    Cat c;\n    c.speak();\n    return 0;\n}',
'Meow', 'advanced', 30, 'Cat overrides the speak() method from Animal', 'oop', 28),

('Vector Size',
'#include<iostream>\n#include<vector>\nusing namespace std;\nint main(){\n    vector<int> v = {1,2,3,4,5};\n    v.push_back(6);\n    cout << v.size();\n    return 0;\n}',
'6', 'advanced', 30, 'push_back adds an element to the end', 'stl', 29),

('Lambda',
'#include<iostream>\nusing namespace std;\nint main(){\n    auto square = [](int x){ return x*x; };\n    cout << square(7);\n    return 0;\n}',
'49', 'advanced', 35, 'Lambda is an anonymous function — [](params){ body }', 'modern-cpp', 30);
