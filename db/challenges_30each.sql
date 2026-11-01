USE cpp_learning;

-- Brings every difficulty to 30 challenges:
--   Beginner     25 -> 30  (+5,  order_num 76-80)
--   Intermediate 26 -> 30  (+4,  order_num 81-84)
--   Advanced     24 -> 30  (+6,  order_num 85-90)

INSERT INTO challenges (title, code, expected_output, difficulty, xp_reward, hint, category, order_num) VALUES

-- ══════════ BEGINNER +5 (76-80) ══════════

('Logical AND',
'#include<iostream>\nusing namespace std;\nint main(){\n    int a=5, b=3;\n    if(a > 2 && b > 2)\n        cout << "yes";\n    else\n        cout << "no";\n    return 0;\n}',
'yes',
'beginner', 10,
'["The && operator requires BOTH conditions to be true for the whole expression to be true.", "Evaluate each side independently: is a > 2 true? Is b > 2 true? Both must hold for the if-branch to run."]',
'conditions', 76),

('Swap Variables',
'#include<iostream>\nusing namespace std;\nint main(){\n    int a=1, b=2;\n    int temp = a;\n    a = b;\n    b = temp;\n    cout << a << " " << b;\n    return 0;\n}',
'2 1',
'beginner', 10,
'["Swapping uses a temporary variable to hold one value while the other is overwritten.", "Trace each assignment in order: what does temp hold? What does a become? What does b become?"]',
'basics', 77),

('sizeof',
'#include<iostream>\nusing namespace std;\nint main(){\n    cout << sizeof(int) << " " << sizeof(char);\n    return 0;\n}',
'4 1',
'beginner', 10,
'["sizeof returns the size in bytes of a type or variable on the current platform.", "On virtually all modern 64-bit systems, int occupies 4 bytes and char occupies 1 byte."]',
'basics', 78),

('String Clear',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    string s = "hello";\n    cout << s.empty() << " ";\n    s.clear();\n    cout << s.empty();\n    return 0;\n}',
'0 1',
'beginner', 10,
'["empty() returns 1 (true) if the string has no characters, 0 (false) otherwise.", "Before clear(), s has characters so empty() is false. After clear(), s has none so empty() is true."]',
'strings', 79),

('Chained Ternary',
'#include<iostream>\n#include<string>\nusing namespace std;\nint main(){\n    int x = 15;\n    string r = x > 20 ? "big" : x > 10 ? "medium" : "small";\n    cout << r;\n    return 0;\n}',
'medium',
'beginner', 15,
'["Ternary operators can be chained: the false-branch of one ternary can itself be another ternary.", "Evaluate left to right: is x > 20? No. Is x > 10? Yes. So the result is the first value of the second ternary."]',
'conditions', 80),

-- ══════════ INTERMEDIATE +4 (81-84) ══════════

('Reverse String',
'#include<iostream>\n#include<string>\n#include<algorithm>\nusing namespace std;\nint main(){\n    string s = "Hello";\n    reverse(s.begin(), s.end());\n    cout << s;\n    return 0;\n}',
'olleH',
'intermediate', 20,
'["std::reverse rearranges the elements of a range in-place, so the last character becomes first.", "Write out \"Hello\" and mentally flip it — the result is the reversed string."]',
'strings', 81),

('Set Size',
'#include<iostream>\n#include<set>\nusing namespace std;\nint main(){\n    set<int> s = {3,1,4,1,5,9,2,6,5};\n    cout << s.size();\n    return 0;\n}',
'7',
'intermediate', 20,
'["std::set stores only unique elements — duplicates are silently discarded on insertion.", "Count the distinct values in the initializer list. That count is what size() returns."]',
'stl', 82),

('Transform Uppercase',
'#include<iostream>\n#include<string>\n#include<algorithm>\nusing namespace std;\nint main(){\n    string s = "hello";\n    transform(s.begin(), s.end(), s.begin(), ::toupper);\n    cout << s;\n    return 0;\n}',
'HELLO',
'intermediate', 20,
'["std::transform applies a function to every element in a range and writes the results back.", "::toupper converts a single lowercase letter to its uppercase equivalent. Applied to every character in s."]',
'strings', 83),

('Priority Queue',
'#include<iostream>\n#include<queue>\nusing namespace std;\nint main(){\n    priority_queue<int> pq;\n    pq.push(3); pq.push(1); pq.push(4);\n    cout << pq.top();\n    return 0;\n}',
'4',
'intermediate', 25,
'["A priority_queue is a max-heap by default: the largest element is always at the top.", "After inserting 3, 1, and 4, which of those values is the greatest? top() returns it."]',
'stl', 84),

-- ══════════ ADVANCED +6 (85-90) ══════════

('constexpr Function',
'#include<iostream>\nusing namespace std;\nconstexpr int square(int x){ return x*x; }\nint main(){\n    cout << square(6);\n    return 0;\n}',
'36',
'advanced', 30,
'["constexpr allows a function to be evaluated at compile time when called with constant arguments.", "The function body is a simple expression. Substitute the argument and evaluate."]',
'modern-cpp', 85),

('Modify With Reference Loop',
'#include<iostream>\n#include<vector>\nusing namespace std;\nint main(){\n    vector<int> v = {1,2,3};\n    for(auto& x : v) x *= 2;\n    for(auto x : v) cout << x << " ";\n    return 0;\n}',
'2 4 6 ',
'advanced', 30,
'["auto& in a range-based for gives a reference to each element, so modifying x modifies the vector.", "The first loop doubles every element in place. The second loop then prints those modified values."]',
'modern-cpp', 86),

('Nested Lambda',
'#include<iostream>\nusing namespace std;\nint main(){\n    auto outer = [](int x){\n        auto inner = [x](int y){ return x + y; };\n        return inner(10);\n    };\n    cout << outer(7);\n    return 0;\n}',
'17',
'advanced', 35,
'["A lambda can be defined inside another lambda. The inner lambda captures x from the outer lambda''s parameter.", "outer(7) runs with x=7. inner captures x=7 and is called with y=10. Evaluate x+y."]',
'modern-cpp', 87),

('Vector of Pairs Sort',
'#include<iostream>\n#include<vector>\n#include<algorithm>\nusing namespace std;\nint main(){\n    vector<pair<int,string>> v = {{3,"c"},{1,"a"},{2,"b"}};\n    sort(v.begin(), v.end());\n    cout << v[0].second;\n    return 0;\n}',
'a',
'advanced', 35,
'["Pairs are compared lexicographically: first by .first, then by .second if .first values tie.", "After sorting ascending by the integer key, which pair comes first? Access its string value with .second."]',
'stl', 88),

('std::array',
'#include<iostream>\n#include<array>\nusing namespace std;\nint main(){\n    array<int,4> a = {10,20,30,40};\n    cout << a.front() << " " << a.back();\n    return 0;\n}',
'10 40',
'advanced', 30,
'["std::array is a fixed-size container with the same performance as a raw array but with member functions.", "front() returns the first element and back() returns the last. Identify those from the initializer."]',
'stl', 89),

('std::function',
'#include<iostream>\n#include<functional>\nusing namespace std;\nint main(){\n    function<int(int,int)> mul = [](int a, int b){ return a*b; };\n    cout << mul(4, 5) << " " << mul(3, 7);\n    return 0;\n}',
'20 21',
'advanced', 30,
'["std::function wraps any callable (lambda, function pointer, functor) matching a given signature.", "Call mul with each pair of arguments and evaluate the multiplication for each call."]',
'modern-cpp', 90);
