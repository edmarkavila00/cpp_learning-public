USE cpp_learning;

-- ─────────────────────────────────────────────────────────────────
-- Brings every difficulty to 20 challenges:
--   Beginner     15 → 20  (+5,  order_num 46–50)
--   Intermediate 10 → 20  (+10, order_num 51–60)
--   Advanced      5 → 20  (+15, order_num 61–75)
-- ─────────────────────────────────────────────────────────────────

INSERT INTO challenges (title, code, expected_output, difficulty, xp_reward, hint, category, order_num) VALUES

-- ══════════════ BEGINNER +5 (46–50) ══════════════

('Multi-Variable Init',
'#include<iostream>\nusing namespace std;\nint main(){\n    int a=1, b=2, c=3;\n    cout << a+b+c;\n    return 0;\n}',
'6',
'beginner', 10,
'Multiple variables can be declared and initialized in one statement. Evaluate each value and add them together.',
'basics', 46),

('String Find',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    string s = "Hello C++";\n    cout << s.find("C++");\n    return 0;\n}',
'6',
'beginner', 10,
'find() returns the index where the substring first appears. Remember that string positions are zero-based.',
'strings', 47),

('Break Statement',
'#include<iostream>\nusing namespace std;\nint main(){\n    for(int i=1; i<=5; i++){\n        if(i == 3) break;\n        cout << i << " ";\n    }\n    return 0;\n}',
'1 2 ',
'beginner', 15,
'break immediately exits the loop. Count how many iterations complete before the break condition is met.',
'loops', 48),

('Continue Statement',
'#include<iostream>\nusing namespace std;\nint main(){\n    for(int i=1; i<=5; i++){\n        if(i == 3) continue;\n        cout << i << " ";\n    }\n    return 0;\n}',
'1 2 4 5 ',
'beginner', 15,
'continue skips the rest of the loop body for that iteration and jumps straight to the next one. The loop itself does not stop.',
'loops', 49),

('Const Variable',
'#include<iostream>\nusing namespace std;\nint main(){\n    const int MAX = 100;\n    cout << MAX;\n    return 0;\n}',
'100',
'beginner', 10,
'const declares a variable whose value cannot be changed after initialization. It prints exactly like a normal variable.',
'basics', 50),

-- ══════════════ INTERMEDIATE +10 (51–60) ══════════════

('2D Array',
'#include<iostream>\nusing namespace std;\nint main(){\n    int a[2][2] = {{1,2},{3,4}};\n    cout << a[1][1];\n    return 0;\n}',
'4',
'intermediate', 20,
'A 2D array is indexed as a[row][col], both zero-based. Trace which row and column the subscript selects.',
'arrays', 51),

('Enum Value',
'#include<iostream>\nusing namespace std;\nenum Color { RED, GREEN, BLUE };\nint main(){\n    Color c = GREEN;\n    cout << c;\n    return 0;\n}',
'1',
'intermediate', 20,
'Enum constants are assigned integer values starting from 0 by default. When printed with cout, the underlying integer is displayed.',
'basics', 52),

('stoi Conversion',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    string s = "42";\n    int n = stoi(s);\n    cout << n * 2;\n    return 0;\n}',
'84',
'intermediate', 20,
'stoi converts a string to an integer. Once converted, normal arithmetic applies to the result.',
'strings', 53),

('to_string Length',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    int n = 255;\n    string s = to_string(n);\n    cout << s.length();\n    return 0;\n}',
'3',
'intermediate', 20,
'to_string converts a number to its decimal string representation. Count the characters in that representation.',
'strings', 54),

('Sort Front Back',
'#include<iostream>\n#include<vector>\n#include<algorithm>\nusing namespace std;\nint main(){\n    vector<int> v = {5,3,8,1,9};\n    sort(v.begin(), v.end());\n    cout << v.front() << " " << v.back();\n    return 0;\n}',
'1 9',
'intermediate', 20,
'std::sort rearranges elements into ascending order. front() returns the first element and back() returns the last after sorting.',
'stl', 55),

('stringstream Build',
'#include<iostream>\n#include<sstream>\nusing namespace std;\nint main(){\n    stringstream ss;\n    ss << "Score: " << 100;\n    cout << ss.str();\n    return 0;\n}',
'Score: 100',
'intermediate', 25,
'A stringstream works like cout but writes into an in-memory string. Call .str() to retrieve the accumulated string.',
'strings', 56),

('Struct Method',
'#include<iostream>\nusing namespace std;\nstruct Circle {\n    double r;\n    double area(){ return 3 * r * r; }\n};\nint main(){\n    Circle c = {2};\n    cout << c.area();\n    return 0;\n}',
'12',
'intermediate', 20,
'A struct can have member functions. The method has access to the struct''s own fields. Evaluate the expression using the stored value of r.',
'basics', 57),

('count Algorithm',
'#include<iostream>\n#include<vector>\n#include<algorithm>\nusing namespace std;\nint main(){\n    vector<int> v = {1,2,3,2,2,4};\n    cout << count(v.begin(), v.end(), 2);\n    return 0;\n}',
'3',
'intermediate', 20,
'std::count scans a range and returns how many elements equal the target value.',
'stl', 58),

('fill Algorithm',
'#include<iostream>\n#include<vector>\n#include<algorithm>\nusing namespace std;\nint main(){\n    vector<int> v(3);\n    fill(v.begin(), v.end(), 7);\n    for(auto x : v) cout << x << " ";\n    return 0;\n}',
'7 7 7 ',
'intermediate', 20,
'std::fill sets every element in a range to the given value. The vector is size 3, so every element becomes that value.',
'stl', 59),

('max_element',
'#include<iostream>\n#include<vector>\n#include<algorithm>\nusing namespace std;\nint main(){\n    vector<int> v = {4,2,9,1,7};\n    cout << *max_element(v.begin(), v.end());\n    return 0;\n}',
'9',
'intermediate', 20,
'max_element returns an iterator to the largest element. Dereference it with * to get the value.',
'stl', 60),

-- ══════════════ ADVANCED +15 (61–75) ══════════════

('Operator Overloading',
'#include<iostream>\nusing namespace std;\nstruct Vec2 {\n    int x, y;\n    Vec2 operator+(Vec2 o){ return {x+o.x, y+o.y}; }\n};\nint main(){\n    Vec2 a={1,2}, b={3,4};\n    Vec2 c = a + b;\n    cout << c.x << " " << c.y;\n    return 0;\n}',
'4 6',
'advanced', 30,
'operator+ is defined as a member function that returns a new Vec2. Apply it component-wise using the two operand objects.',
'oop', 61),

('Exception Handling',
'#include<iostream>\nusing namespace std;\nint main(){\n    try {\n        throw 42;\n    } catch(int e){\n        cout << "Caught: " << e;\n    }\n    return 0;\n}',
'Caught: 42',
'advanced', 30,
'throw transfers control to the nearest matching catch. The catch parameter receives the thrown value.',
'modern-cpp', 62),

('Static Class Member',
'#include<iostream>\nusing namespace std;\nclass Counter {\npublic:\n    static int count;\n    Counter(){ count++; }\n};\nint Counter::count = 0;\nint main(){\n    Counter a, b, c;\n    cout << Counter::count;\n    return 0;\n}',
'3',
'advanced', 30,
'A static member variable is shared across all instances. Every constructor call increments the same shared counter.',
'oop', 63),

('Lambda Sort Descending',
'#include<iostream>\n#include<vector>\n#include<algorithm>\nusing namespace std;\nint main(){\n    vector<int> v = {3,1,4,1,5};\n    sort(v.begin(), v.end(), [](int a, int b){ return a > b; });\n    cout << v[0] << " " << v[4];\n    return 0;\n}',
'5 1',
'advanced', 30,
'Passing a comparator lambda to sort changes the ordering. A comparator returning a > b produces descending order.',
'modern-cpp', 64),

('Member Init List',
'#include<iostream>\nusing namespace std;\nclass Box {\npublic:\n    int w, h;\n    Box(int w, int h): w(w), h(h){}\n    int area(){ return w*h; }\n};\nint main(){\n    Box b(4,5);\n    cout << b.area();\n    return 0;\n}',
'20',
'advanced', 30,
'A member initializer list (the : w(w), h(h) part) initializes fields before the constructor body runs. Evaluate the method using those initialized values.',
'oop', 65),

('Friend Function',
'#include<iostream>\nusing namespace std;\nclass Secret {\n    int val = 7;\n    friend void reveal(Secret s);\n};\nvoid reveal(Secret s){ cout << s.val; }\nint main(){\n    Secret s;\n    reveal(s);\n    return 0;\n}',
'7',
'advanced', 30,
'A friend function is declared inside the class to grant it access to private members. It is not a member function itself.',
'oop', 66),

('Namespace',
'#include<iostream>\nusing namespace std;\nnamespace math {\n    int square(int x){ return x*x; }\n}\nint main(){\n    cout << math::square(5);\n    return 0;\n}',
'25',
'advanced', 30,
'A namespace groups related names to avoid conflicts. Use the :: scope resolution operator to call a function inside a namespace.',
'basics', 67),

('Abstract Class',
'#include<iostream>\nusing namespace std;\nclass Animal {\npublic:\n    virtual string sound() = 0;\n};\nclass Dog : public Animal {\npublic:\n    string sound(){ return "Woof"; }\n};\nint main(){\n    Dog d;\n    cout << d.sound();\n    return 0;\n}',
'Woof',
'advanced', 35,
'A pure virtual function (= 0) makes the class abstract. A concrete derived class must override it. Calling the method on the derived object invokes the override.',
'oop', 68),

('Tuple',
'#include<iostream>\n#include<tuple>\nusing namespace std;\nint main(){\n    auto t = make_tuple(1, "hello", 3.14);\n    cout << get<0>(t) << " " << get<1>(t);\n    return 0;\n}',
'1 hello',
'advanced', 30,
'std::tuple holds a fixed number of values of different types. get<N>(t) retrieves the element at compile-time index N (zero-based).',
'stl', 69),

('Copy Constructor',
'#include<iostream>\nusing namespace std;\nclass Num {\npublic:\n    int val;\n    Num(int v): val(v){}\n    Num(const Num& o): val(o.val * 2){}\n};\nint main(){\n    Num a(5);\n    Num b = a;\n    cout << b.val;\n    return 0;\n}',
'10',
'advanced', 35,
'A copy constructor is called when one object is initialized from another of the same type. Read what the copy constructor does to the source value.',
'oop', 70),

('Bitwise AND',
'#include<iostream>\nusing namespace std;\nint main(){\n    int a = 12, b = 10;\n    cout << (a & b);\n    return 0;\n}',
'8',
'advanced', 30,
'The & operator performs bitwise AND — each bit in the result is 1 only if the corresponding bits in both operands are 1. Convert both numbers to binary to trace it.',
'basics', 71),

('Right Shift',
'#include<iostream>\nusing namespace std;\nint main(){\n    int x = 6;\n    cout << (x >> 1);\n    return 0;\n}',
'3',
'advanced', 30,
'The >> operator shifts bits to the right. Each right-shift by 1 is equivalent to dividing by 2 (for non-negative integers), discarding any remainder.',
'basics', 72),

('accumulate',
'#include<iostream>\n#include<numeric>\n#include<vector>\nusing namespace std;\nint main(){\n    vector<int> v = {1,2,3,4,5};\n    cout << accumulate(v.begin(), v.end(), 0);\n    return 0;\n}',
'15',
'advanced', 30,
'std::accumulate folds a range into a single value starting from the given initial value. The default operation is addition.',
'stl', 73),

('stringstream Parse',
'#include<iostream>\n#include<sstream>\nusing namespace std;\nint main(){\n    string line = "10 20 30";\n    stringstream ss(line);\n    int a, b, c;\n    ss >> a >> b >> c;\n    cout << a + b + c;\n    return 0;\n}',
'60',
'advanced', 30,
'A stringstream initialized with a string lets you extract values from it using >> just like cin. Each >> reads the next whitespace-separated token.',
'strings', 74),

('Structured Binding',
'#include<iostream>\n#include<map>\nusing namespace std;\nint main(){\n    map<string,int> m = {{"a",1},{"b",2},{"c",3}};\n    for(auto& [k,v] : m)\n        cout << k << v;\n    return 0;\n}',
'a1b2c3',
'advanced', 35,
'Structured bindings (C++17) unpack each map entry into a key k and value v. A std::map always iterates in ascending key order.',
'modern-cpp', 75);
