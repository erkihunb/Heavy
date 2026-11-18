<?php

namespace App\Security;

/**
 * Passwords found at the top of every leaked-password list, refused when
 * "Block common passwords" is on (System settings › Security). Compared
 * without regard to case.
 */
final class CommonPasswords
{
    private const array LIST = [
        '123456', '123456789', '12345678', '1234567890', '12345', '1234567', '123123', '111111', '000000',
        '654321', '666666', '121212', '112233', '123321', '987654321', '1q2w3e4r', '1q2w3e4r5t', '1qaz2wsx',
        'qwerty', 'qwerty123', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm', 'password', 'password1', 'password12',
        'password123', 'password1234', 'passw0rd', 'p@ssw0rd', 'p@ssword', 'p@ssword1', 'p@ssw0rd1', 'admin',
        'admin123', 'admin1234', 'administrator', 'welcome', 'welcome1', 'welcome123', 'welcome@123',
        'letmein', 'iloveyou', 'monkey', 'dragon', 'football', 'baseball', 'master', 'sunshine', 'princess',
        'abc123', 'abcd1234', 'abcdef', 'trustno1', 'superman', 'batman', 'starwars', 'shadow', 'michael',
        'jesus', 'jesus123', 'changeme', 'changeme123', 'default', 'secret', 'secret123', 'test', 'test123',
        'test1234', 'guest', 'login', 'user', 'user123', 'root', 'toor', 'pass', 'pass123', 'pass1234',
        'ethiopia', 'ethiopia1', 'ethiopia123', 'ethiopia@123', 'addis', 'addisababa', 'addis123',
        'habesha', 'aacra', 'aacra123', 'heavy123', 'qwe123', 'zaq12wsx', '!qaz2wsx', 'q1w2e3r4', 'aa123456',
        'a123456', 'a1b2c3d4', 'qazwsx', 'computer', 'internet', 'samsung', 'google', 'whatever', 'hello',
        'hello123', 'freedom', 'access', 'mustang', 'charlie', 'killer', 'soccer', 'hockey', 'ninja',
        '7777777', '88888888', '11111111', '00000000', '123qwe', 'qwerty1', 'qwerty12', '1password',
    ];

    public static function contains(string $password): bool
    {
        return in_array(mb_strtolower($password), self::LIST, true);
    }
}
