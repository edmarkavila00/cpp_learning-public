CREATE DATABASE IF NOT EXISTS cpp_learning;
USE cpp_learning;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    xp INT DEFAULT 0,
    level INT DEFAULT 1,
    streak INT DEFAULT 0,
    last_played DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    code TEXT NOT NULL,
    expected_output VARCHAR(500) NOT NULL,
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    xp_reward INT DEFAULT 10,
    hint TEXT NULL,
    category VARCHAR(50) DEFAULT 'general',
    order_num INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    solved TINYINT(1) DEFAULT 0,
    attempts INT DEFAULT 0,
    solved_at TIMESTAMP NULL,
    UNIQUE KEY uq_user_challenge (user_id, challenge_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (challenge_id) REFERENCES challenges(id)
);

-- Seed challenges
INSERT INTO challenges (title, code, expected_output, difficulty, xp_reward, hint, category, order_num) VALUES
('Hello World', '#include<iostream>\nusing namespace std;\nint main(){\n    cout << "Hello, World!";\n    return 0;\n}', 'Hello, World!', 'beginner', 10, 'cout prints text to screen', 'basics', 1),

('Simple Addition', '#include<iostream>\nusing namespace std;\nint main(){\n    int a = 5, b = 3;\n    cout << a + b;\n    return 0;\n}', '8', 'beginner', 10, 'Add two integers', 'basics', 2),

('String Output', '#include<iostream>\nusing namespace std;\nint main(){\n    string name = "CppQuest";\n    cout << "Welcome to " << name;\n    return 0;\n}', 'Welcome to CppQuest', 'beginner', 10, 'String concatenation with cout', 'basics', 3),

('For Loop', '#include<iostream>\nusing namespace std;\nint main(){\n    for(int i = 1; i <= 3; i++){\n        cout << i << " ";\n    }\n    return 0;\n}', '1 2 3 ', 'beginner', 15, 'Loop runs 3 times starting at 1', 'loops', 4),

('While Loop', '#include<iostream>\nusing namespace std;\nint main(){\n    int x = 10;\n    while(x > 7){\n        cout << x << " ";\n        x--;\n    }\n    return 0;\n}', '10 9 8 ', 'beginner', 15, 'x decrements each iteration', 'loops', 5),

('If Else', '#include<iostream>\nusing namespace std;\nint main(){\n    int n = 7;\n    if(n % 2 == 0)\n        cout << "even";\n    else\n        cout << "odd";\n    return 0;\n}', 'odd', 'beginner', 15, 'Check if number divides evenly by 2', 'conditions', 6),

('Array Sum', '#include<iostream>\nusing namespace std;\nint main(){\n    int arr[] = {1, 2, 3, 4, 5};\n    int sum = 0;\n    for(int i = 0; i < 5; i++) sum += arr[i];\n    cout << sum;\n    return 0;\n}', '15', 'intermediate', 20, 'Sum all elements of the array', 'arrays', 7),

('Function Call', '#include<iostream>\nusing namespace std;\nint square(int n){ return n * n; }\nint main(){\n    cout << square(4);\n    return 0;\n}', '16', 'intermediate', 20, 'What is 4 * 4?', 'functions', 8),

('Pointer Basics', '#include<iostream>\nusing namespace std;\nint main(){\n    int x = 42;\n    int* p = &x;\n    cout << *p;\n    return 0;\n}', '42', 'intermediate', 25, 'Dereference the pointer to get value', 'pointers', 9),

('Class Object', '#include<iostream>\nusing namespace std;\nclass Dog{\npublic:\n    string name = "Rex";\n    void bark(){ cout << name << " says Woof!"; }\n};\nint main(){\n    Dog d;\n    d.bark();\n    return 0;\n}', 'Rex says Woof!', 'advanced', 30, 'Object calls its method', 'oop', 10);
