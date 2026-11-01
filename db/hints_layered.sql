USE cpp_learning;

-- Convert every challenge to a JSON hint array.
-- Hint 1 = concept explanation, Hint 2 = code-tracing guidance.
-- Neither hint ever reveals the answer.

-- order 1: Hello World
UPDATE challenges SET hint = '["std::cout is C++\'s standard output stream. The << operator sends data to it, and string literals are printed exactly as written.",
"Look at what text is between the double quotes after <<. That text  - punctuation and all  - is what gets printed."]'
WHERE order_num = 1;

-- order 2: Simple Addition
UPDATE challenges SET hint = '["Declare variables and note what integer value each one holds at the point cout runs.",
"The + operator on two int variables produces their arithmetic sum. Evaluate that expression with the stored values."]'
WHERE order_num = 2;

-- order 3: String Output
UPDATE challenges SET hint = '["Multiple << operators chain output with no separator between them  - each piece is printed immediately after the previous.",
"What text is stored in name? Where does it appear relative to the string literal that precedes it?"]'
WHERE order_num = 3;

-- order 4: For Loop
UPDATE challenges SET hint = '["A for loop has three parts: init; condition; step. Trace i from its starting value, checking whether the condition holds each time.",
"Each time the loop body runs, i holds a specific value. How many iterations complete before i fails the condition?"]'
WHERE order_num = 4;

-- order 5: While Loop
UPDATE challenges SET hint = '["A while loop tests its condition before each iteration. Trace x from 10 and note what -- does after each print.",
"Ask yourself: which values of x satisfy x > 7? List those values  - they are exactly what gets printed."]'
WHERE order_num = 5;

-- order 6: If Else
UPDATE challenges SET hint = '["The % operator returns the remainder after integer division. Compute 7 % 2 mentally.",
"If the remainder is 0, the number is even and the if-branch runs; otherwise the else-branch runs. Which applies here?"]'
WHERE order_num = 6;

-- order 7: Array Sum
UPDATE challenges SET hint = '["sum starts at 0. Each loop iteration adds one array element to it. Trace i from 0 to 4.",
"Work through the additions one at a time: 0 + arr[0], result + arr[1], and so on through arr[4]."]'
WHERE order_num = 7;

-- order 8: Function Call
UPDATE challenges SET hint = '["When square(4) is called, substitute 4 for the parameter n everywhere in the function body.",
"The function body is a single expression involving n. Evaluate it with n = 4 and that is the return value printed."]'
WHERE order_num = 8;

-- order 9: Pointer Basics
UPDATE challenges SET hint = '["& gives the memory address of a variable. * before a pointer dereferences it, reading the value stored at that address.",
"p stores the address of x. Dereferencing p with *p reads back the value that was stored in x."]'
WHERE order_num = 9;

-- order 10: Class Object
UPDATE challenges SET hint = '["When an object is created its member variables are set to their in-class defaults. A method call runs that method with those values.",
"Find the name member\'s initial value inside the class, then read what bark() does with it."]'
WHERE order_num = 10;

-- order 11: Newline Output
UPDATE challenges SET hint = '["endl moves the cursor to the next line before the next cout runs.",
"Treat each cout as a separate print. The first ends with a newline, so the second starts on a new line."]'
WHERE order_num = 11;

-- order 12: Integer Division
UPDATE challenges SET hint = '["When both operands are int, the / operator performs integer division  - the decimal portion is truncated, not rounded.",
"Divide 7 by 2 on paper to get the exact result, then drop everything after the decimal point."]'
WHERE order_num = 12;

-- order 13: Modulo Operator
UPDATE challenges SET hint = '["The % operator gives the remainder after dividing the left operand by the right.",
"Divide 10 by 3 to find how many times 3 goes in evenly, then subtract that from 10 to find what is left over."]'
WHERE order_num = 13;

-- order 14: Char Output
UPDATE challenges SET hint = '["A char variable holds a single character. cout prints the character itself, not its numeric ASCII code.",
"What character is between the single quotes in the initializer? That exact character is what gets printed."]'
WHERE order_num = 14;

-- order 15: Boolean Output
UPDATE challenges SET hint = '["In C++, bool values are stored internally as integers. By default cout does not print \'true\' or \'false\'.",
"true maps to one specific integer and false to another. Think about which integer represents a truthy value."]'
WHERE order_num = 15;

-- order 16: Nested Loop
UPDATE challenges SET hint = '["Trace both loops together: for each value of i the inner loop runs its full range of j values.",
"For each (i, j) pair, compute i*j and note that it is followed by a space. List all pairs in order."]'
WHERE order_num = 16;

-- order 17: Do While
UPDATE challenges SET hint = '["A do-while always executes its body at least once before checking the condition.",
"Trace n from 0: print, increment, then check. How many iterations satisfy n < 3?"]'
WHERE order_num = 17;

-- order 18: Ternary Operator
UPDATE challenges SET hint = '["The ternary evaluates the condition. If true it produces the first value; if false it produces the second.",
"Evaluate x > 5 with x = 10. Which branch does that result select?"]'
WHERE order_num = 18;

-- order 19: Switch Case
UPDATE challenges SET hint = '["A switch compares its expression against each case label. When a match is found, that case\'s code runs until break.",
"What is the value of day? Find the matching case label and read what it prints."]'
WHERE order_num = 19;

-- order 20: String Length
UPDATE challenges SET hint = '["length() counts the number of characters in a string  - not including any null terminator.",
"Count the characters in the string literal manually. Each letter, digit, and space is one character."]'
WHERE order_num = 20;

-- order 21: String Concat
UPDATE challenges SET hint = '["The + operator on two std::string objects concatenates them, appending the right string to the end of the left.",
"What are the two string values? Write them out side by side with no separator  - that is the result."]'
WHERE order_num = 21;

-- order 22: Array Max
UPDATE challenges SET hint = '["The algorithm keeps a \'current maximum\' variable and updates it whenever a larger element is seen.",
"Trace through each element of the array in order. After the loop, what is the largest value that was encountered?"]'
WHERE order_num = 22;

-- order 23: Pass by Reference
UPDATE challenges SET hint = '["Passing by reference (&) means the function receives an alias to the original variable  - not a copy.",
"Any modification inside the function directly changes the caller\'s variable. What value does x hold after addTen runs?"]'
WHERE order_num = 23;

-- order 24: Recursive Factorial
UPDATE challenges SET hint = '["Trace the recursion by expanding each call: fact(4) calls fact(3), which calls fact(2), and so on until the base case.",
"Once you reach the base case, work back up, multiplying at each level. fact(1)=1, fact(2)=?, fact(3)=?, fact(4)=?"]'
WHERE order_num = 24;

-- order 25: Pointer Arithmetic
UPDATE challenges SET hint = '["Adding 1 to a pointer advances it to the next element  - it moves forward by the size of one element, not one byte.",
"p points to arr[0]. What element does p+1 point to? Dereference that to get the printed value."]'
WHERE order_num = 25;

-- order 26: Default Parameter
UPDATE challenges SET hint = '["A default parameter is used when the caller does not pass an argument for that parameter.",
"greet() is called with no arguments. Which value does name take inside the function?"]'
WHERE order_num = 26;

-- order 27: Constructor
UPDATE challenges SET hint = '["A constructor runs automatically at object creation and initializes the object\'s fields from its arguments.",
"Car c(\"Toyota\") calls the constructor with that string. What does the constructor store in brand?"]'
WHERE order_num = 27;

-- order 28: Inheritance
UPDATE challenges SET hint = '["When a derived class defines a method with the same name as the base class, calling it on a derived object uses the derived version.",
"c is a Cat object. Cat defines its own speak()  - which version runs?"]'
WHERE order_num = 28;

-- order 29: Vector Size
UPDATE challenges SET hint = '["push_back appends one element to the end of a vector, increasing its size by exactly 1.",
"Count the initial elements, then count how many push_back calls run. size() returns that total."]'
WHERE order_num = 29;

-- order 30: Lambda
UPDATE challenges SET hint = '["A lambda is an anonymous function stored in a variable. [] is the capture list, (int x) the parameters, and { return x*x; } the body.",
"square is called with 7. Substitute 7 into the lambda body and evaluate the expression."]'
WHERE order_num = 30;

-- order 31: Range-Based For
UPDATE challenges SET hint = '["A range-based for loop visits every element of the array or container in order.",
"auto deduces the element type. Trace x through each value in arr and note what is printed each time."]'
WHERE order_num = 31;

-- order 32: Post-Increment
UPDATE challenges SET hint = '["x++ (post-increment) returns the current value of x, then increments x.",
"The first << evaluates x++  - what value does it return? After that evaluation, what does x hold for the second <<?"]'
WHERE order_num = 32;

-- order 33: Type Casting
UPDATE challenges SET hint = '["A C-style cast (int)x truncates toward zero  - it drops the decimal, it does not round.",
"What is x before the cast? Remove everything after the decimal point to get the printed integer."]'
WHERE order_num = 33;

-- order 34: Compound Assignment
UPDATE challenges SET hint = '["Compound assignment operators modify the variable in place. They are evaluated left to right.",
"Trace x step by step: apply += first to get a new value, then apply *= to that updated value."]'
WHERE order_num = 34;

-- order 35: String Substr
UPDATE challenges SET hint = '["substr(pos) returns the portion of the string starting at index pos through the end. Indices start at 0.",
"Count the characters of the string from index 0. Which character sits at index 6, and what follows it?"]'
WHERE order_num = 35;

-- order 36: Struct Fields
UPDATE challenges SET hint = '["A struct groups related variables. Access individual fields with the . (dot) operator.",
"p is initialized with {3, 4}. Those values go into x and y in declaration order. What is x + y?"]'
WHERE order_num = 36;

-- order 37: Static Variable
UPDATE challenges SET hint = '["A static local variable is initialized once and retains its value between function calls  - it is not reset on each call.",
"Trace n across all three calls to tick(). What value does n hold at the start of each call?"]'
WHERE order_num = 37;

-- order 38: Function Overloading
UPDATE challenges SET hint = '["C++ selects the overload whose parameter types best match the argument types at each call site.",
"add(2,3) passes two ints  - which overload handles int arguments? add(1.5,2.5) passes two doubles  - which overload handles those?"]'
WHERE order_num = 38;

-- order 39: Map Lookup
UPDATE challenges SET hint = '["std::map stores key-value pairs. m[key] retrieves the value associated with that key.",
"Look up the value stored under \"cpp\" and under \"py\". Apply the arithmetic operator between them."]'
WHERE order_num = 39;

-- order 40: Vector Element
UPDATE challenges SET hint = '["push_back appends to the end of the vector. Vector indices are zero-based.",
"After push_back, how many elements are in v? The element at index 3 is whichever one was added last."]'
WHERE order_num = 40;

-- order 41: Pair
UPDATE challenges SET hint = '["std::pair holds two values of potentially different types. Access them with .first and .second.",
"p is initialized with {1, \"one\"}. .first gives the int, .second gives the string. They are printed separated by a space."]'
WHERE order_num = 41;

-- order 42: Template Function
UPDATE challenges SET hint = '["A function template creates a version for each type it is called with. The compiler deduces T from the arguments.",
"For maxVal(3,7) T is int. For maxVal(2.5,1.5) T is double. Evaluate the ternary in each case."]'
WHERE order_num = 42;

-- order 43: Virtual Dispatch
UPDATE challenges SET hint = '["virtual enables runtime polymorphism: the method called depends on the actual object type, not the pointer type.",
"s is declared as Shape* but points to a Circle object. Which class\'s name() method does the virtual dispatch invoke?"]'
WHERE order_num = 43;

-- order 44: unique_ptr
UPDATE challenges SET hint = '["make_unique<int>(99) allocates an int on the heap initialized to 99, wrapped in a unique_ptr.",
"Dereferencing a unique_ptr with * works the same as dereferencing a raw pointer  - it reads the stored value."]'
WHERE order_num = 44;

-- order 45: Lambda Capture
UPDATE challenges SET hint = '["The capture list [base] copies the local variable base into the lambda so it is accessible inside.",
"add is called with 5. Inside the lambda, base holds its captured value and x = 5. Evaluate the expression."]'
WHERE order_num = 45;

-- order 46: Multi-Variable Init
UPDATE challenges SET hint = '["Multiple variables can be declared and initialized in one statement separated by commas.",
"Read the value assigned to each variable, then evaluate the arithmetic expression using all three."]'
WHERE order_num = 46;

-- order 47: String Find
UPDATE challenges SET hint = '["find() returns the zero-based index where the substring first appears in the string.",
"Walk through the characters of s from index 0 and find where the target substring begins."]'
WHERE order_num = 47;

-- order 48: Break Statement
UPDATE challenges SET hint = '["break exits the enclosing loop immediately, regardless of the loop condition.",
"Trace i from 1. Which iteration triggers the break? Only iterations that complete before that point print output."]'
WHERE order_num = 48;

-- order 49: Continue Statement
UPDATE challenges SET hint = '["continue skips the rest of the loop body for the current iteration and goes straight to the next one.",
"Trace i from 1 to 5. Which value of i triggers continue and therefore does not print? All others print."]'
WHERE order_num = 49;

-- order 50: Const Variable
UPDATE challenges SET hint = '["const marks a variable as read-only after initialization. It prints just like a normal variable.",
"What value is MAX initialized to? That value is what gets printed."]'
WHERE order_num = 50;

-- order 51: 2D Array
UPDATE challenges SET hint = '["A 2D array is indexed a[row][col], both zero-based. The initializer lists fill rows in order.",
"The outer braces hold two rows. Which row is index 1? Within that row, which element is index 1?"]'
WHERE order_num = 51;

-- order 52: Enum Value
UPDATE challenges SET hint = '["Enum constants are assigned sequential integer values starting from 0 unless specified otherwise.",
"RED=0, GREEN=1, BLUE=2. When an enum is printed with cout it shows the underlying integer."]'
WHERE order_num = 52;

-- order 53: stoi Conversion
UPDATE challenges SET hint = '["stoi converts a decimal string to an int. The result is a normal integer you can do arithmetic on.",
"Convert the string to its integer value, then apply the multiplication."]'
WHERE order_num = 53;

-- order 54: to_string Length
UPDATE challenges SET hint = '["to_string converts an integer to its decimal string representation.",
"Write out the decimal representation of n. How many characters (digits) does that string contain?"]'
WHERE order_num = 54;

-- order 55: Sort Front Back
UPDATE challenges SET hint = '["std::sort arranges elements into ascending order by default. front() is the smallest, back() is the largest.",
"Mentally sort the five values from smallest to largest. The first and last elements are what get printed."]'
WHERE order_num = 55;

-- order 56: stringstream Build
UPDATE challenges SET hint = '["A stringstream accumulates text just like cout, but stores it in memory. str() retrieves the full string.",
"What two things are sent to ss with <<? They are concatenated in order with no separator."]'
WHERE order_num = 56;

-- order 57: Struct Method
UPDATE challenges SET hint = '["A struct can contain member functions that have access to the struct\'s own fields.",
"c.r is initialized to 2. Substitute r = 2 into the area() expression and evaluate."]'
WHERE order_num = 57;

-- order 58: count Algorithm
UPDATE challenges SET hint = '["std::count scans a range and returns how many elements equal the given value.",
"Go through the vector element by element and count how many match the target."]'
WHERE order_num = 58;

-- order 59: fill Algorithm
UPDATE challenges SET hint = '["std::fill sets every element in the range to the given value.",
"The vector has 3 elements and every one gets assigned the same value. What does printing them with spaces produce?"]'
WHERE order_num = 59;

-- order 60: max_element
UPDATE challenges SET hint = '["max_element returns an iterator pointing to the largest element. Dereference it with * to get the value.",
"Scan the vector elements and identify the largest one. That is what the dereference produces."]'
WHERE order_num = 60;

-- order 61: Operator Overloading
UPDATE challenges SET hint = '["operator+ is defined as a member function that returns a new Vec2 by combining both objects\' fields.",
"Apply the overloaded + to a and b component-wise: new x = a.x + b.x, new y = a.y + b.y."]'
WHERE order_num = 61;

-- order 62: Exception Handling
UPDATE challenges SET hint = '["throw transfers control to the nearest matching catch block. The thrown value becomes the catch parameter.",
"An int is thrown. The catch(int e) block matches. What value does e receive, and what does it print?"]'
WHERE order_num = 62;

-- order 63: Static Class Member
UPDATE challenges SET hint = '["A static member variable is shared across all instances of the class  - there is only one copy.",
"The constructor increments count. How many objects are constructed in main? What is count after all of them?"]'
WHERE order_num = 63;

-- order 64: Lambda Sort Descending
UPDATE challenges SET hint = '["Providing a comparator lambda to sort changes the ordering. Return a > b to sort in descending order.",
"After descending sort, v[0] is the largest element and v[4] is the smallest. Identify those from the input."]'
WHERE order_num = 64;

-- order 65: Member Init List
UPDATE challenges SET hint = '["A member initializer list (: w(w), h(h)) sets fields before the constructor body runs.",
"b is constructed with 4 and 5. Those become w and h. Evaluate the area() expression with those values."]'
WHERE order_num = 65;

-- order 66: Friend Function
UPDATE challenges SET hint = '["A friend function is granted access to private members of a class, even though it is not a member function itself.",
"val is private but reveal() is a friend. It reads val from s. What was val initialized to in the class body?"]'
WHERE order_num = 66;

-- order 67: Namespace
UPDATE challenges SET hint = '["A namespace groups names to avoid conflicts. Use :: to call a function inside one.",
"math::square(5) calls the square function from the math namespace with argument 5. Evaluate its body."]'
WHERE order_num = 67;

-- order 68: Abstract Class
UPDATE challenges SET hint = '["A pure virtual function (= 0) forces derived classes to override it. You cannot instantiate the abstract base directly.",
"Dog provides a concrete override of sound(). Calling d.sound() invokes that override, not the base."]'
WHERE order_num = 68;

-- order 69: Tuple
UPDATE challenges SET hint = '["std::tuple holds a fixed sequence of values. get<N>(t) retrieves the element at zero-based compile-time index N.",
"get<0>(t) is the first element, get<1>(t) is the second. They are printed separated by a space."]'
WHERE order_num = 69;

-- order 70: Copy Constructor
UPDATE challenges SET hint = '["A copy constructor is called when one object is initialized from another of the same type (Num b = a).",
"Read the copy constructor body carefully. What expression does it use to compute val from the source object?"]'
WHERE order_num = 70;

-- order 71: Bitwise AND
UPDATE challenges SET hint = '["& performs a bitwise AND: each result bit is 1 only if the corresponding bits in both operands are 1.",
"Convert 12 and 10 to binary (4 bits each). AND every bit position and convert the result back to decimal."]'
WHERE order_num = 71;

-- order 72: Right Shift
UPDATE challenges SET hint = '[">> shifts all bits to the right by the specified amount. Bits that fall off the right are lost.",
"A single right shift by 1 is equivalent to integer division by 2 for non-negative numbers. Apply that to x."]'
WHERE order_num = 72;

-- order 73: accumulate
UPDATE challenges SET hint = '["std::accumulate folds the range into a single value, starting from the initial value and applying the operation.",
"The default operation is addition. Sum all elements in v, starting from 0."]'
WHERE order_num = 73;

-- order 74: stringstream Parse
UPDATE challenges SET hint = '["A stringstream built from a string lets you extract whitespace-separated tokens with >> just like cin.",
"ss >> a >> b >> c extracts three integers from the string. Apply the arithmetic to those three values."]'
WHERE order_num = 74;

-- order 75: Structured Binding
UPDATE challenges SET hint = '["Structured bindings (auto& [k,v]) unpack each element into named variables. std::map iterates in ascending key order.",
"For each map entry, k is the key string and v is the integer value. They are printed side by side with no space."]'
WHERE order_num = 75;
