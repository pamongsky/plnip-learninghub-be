<?php

namespace App\Utils;

class PasswordGenerator
{
    /**
     * Generate a secure random password
     *
     * Requirements:
     * - Minimum 12 characters
     * - At least 1 uppercase letter
     * - At least 1 lowercase letter
     * - At least 1 number
     * - At least 1 special character
     *
     * @param int $length Password length (default: 12)
     * @return string Generated password
     */
    public static function generate(int $length = 12): string
    {
        if ($length < 8) {
            $length = 12; // Minimum safe length
        }

        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';

        // Ensure at least one character from each set
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest with random characters from all sets
        $allChars = $uppercase . $lowercase . $numbers . $special;
        $remainingLength = $length - 4;

        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to randomize character positions
        $passwordArray = str_split($password);
        shuffle($passwordArray);

        return implode('', $passwordArray);
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least 1 uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least 1 lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least 1 number';
        }

        if (!preg_match('/[!@#$%^&*]/', $password)) {
            $errors[] = 'Password must contain at least 1 special character (!@#$%^&*)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
