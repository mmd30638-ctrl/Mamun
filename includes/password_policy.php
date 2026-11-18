<?php

/**
 * @return string|null Error message in Bengali, or null if valid.
 */
function ame_validate_password(string $password): ?string
{
    $len = strlen($password);
    if ($len < 8) {
        return 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।';
    }
    if ($len > 72) {
        return 'পাসওয়ার্ড ৭২ অক্ষরের বেশি হতে পারবে না।';
    }
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'পাসওয়ার্ডে অন্তত একটি অক্ষর (A-Z) এবং একটি সংখ্যা (0-9) থাকতে হবে।';
    }
    $weak = ['password', 'admin', '123456', 'admin123', '12345678', 'password1', 'qwerty12'];
    if (in_array(strtolower($password), $weak, true)) {
        return 'অনেক সহজ/ডিফল্ট পাসওয়ার্ড ব্যবহার করা যাবে না।';
    }
    return null;
}

function ame_password_policy_hint(): string
{
    return 'কমপক্ষে ৮ অক্ষর, একটি অক্ষর (A-Z) এবং একটি সংখ্যা (0-9)।';
}
