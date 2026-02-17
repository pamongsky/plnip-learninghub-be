<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class UserCredentialPdfService
{
    /**
     * Generate PDF for single user credentials
     *
     * @param array $userData ['name' => '', 'email' => '', 'employee_id' => '', 'password' => '']
     * @return string PDF content
     */
    public static function generateSingle(array $userData): string
    {
        $html = self::generateSingleUserHtml($userData);
        return self::generatePdf($html, 'User Credential');
    }

    /**
     * Generate PDF for multiple users credentials
     *
     * @param array $usersData Array of user data
     * @return string PDF content
     */
    public static function generateBulk(array $usersData): string
    {
        $html = self::generateBulkUsersHtml($usersData);
        return self::generatePdf($html, 'User Credentials');
    }

    /**
     * Generate HTML for single user
     */
    private static function generateSingleUserHtml(array $user): string
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Credential</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid #0066cc;
            border-radius: 10px;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 10px;
        }
        .title {
            font-size: 18px;
            color: #666;
        }
        .content {
            margin: 30px 0;
        }
        .field {
            margin: 15px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .field-label {
            font-weight: bold;
            color: #0066cc;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .field-value {
            font-size: 16px;
            color: #333;
            word-break: break-all;
        }
        .password-box {
            background-color: #fff3cd;
            border: 2px dashed #ff9800;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .password-label {
            font-weight: bold;
            color: #ff6b00;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .password-value {
            font-size: 20px;
            font-weight: bold;
            color: #d84315;
            font-family: "Courier New", monospace;
            letter-spacing: 2px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">PLN IP Learning Hub</div>
            <div class="title">User Account Credentials</div>
        </div>

        <div class="content">
            <div class="field">
                <div class="field-label">Full Name</div>
                <div class="field-value">' . htmlspecialchars($user['name']) . '</div>
            </div>

            <div class="field">
                <div class="field-label">Email Address</div>
                <div class="field-value">' . htmlspecialchars($user['email']) . '</div>
            </div>

            ' . (!empty($user['employee_id']) ? '
            <div class="field">
                <div class="field-label">Employee ID (NIP)</div>
                <div class="field-value">' . htmlspecialchars($user['employee_id']) . '</div>
            </div>
            ' : '') . '

            ' . (!empty($user['department']) ? '
            <div class="field">
                <div class="field-label">Department</div>
                <div class="field-value">' . htmlspecialchars($user['department']) . '</div>
            </div>
            ' : '') . '

            ' . (!empty($user['position']) ? '
            <div class="field">
                <div class="field-label">Position</div>
                <div class="field-value">' . htmlspecialchars($user['position']) . '</div>
            </div>
            ' : '') . '

            <div class="password-box">
                <div class="password-label">⚠️ TEMPORARY PASSWORD</div>
                <div class="password-value">' . htmlspecialchars($user['password']) . '</div>
            </div>

            <div class="warning">
                <strong>⚠️ IMPORTANT SECURITY NOTICE:</strong><br>
                • This is your temporary password. You must change it upon first login.<br>
                • Keep this document secure and confidential.<br>
                • Do not share your password with anyone.<br>
                • After changing your password, please destroy this document.
            </div>
        </div>

        <div class="footer">
            Generated on ' . date('d F Y, H:i:s') . ' WIB<br>
            PLN IP Learning Hub Portal - Confidential Document
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Generate HTML for bulk users
     */
    private static function generateBulkUsersHtml(array $users): string
    {
        $rows = '';
        foreach ($users as $index => $user) {
            $rows .= '
            <tr>
                <td style="text-align: center;">' . ($index + 1) . '</td>
                <td>' . htmlspecialchars($user['name']) . '</td>
                <td>' . htmlspecialchars($user['email']) . '</td>
                <td>' . htmlspecialchars($user['employee_id'] ?? '-') . '</td>
                <td>' . htmlspecialchars($user['department'] ?? '-') . '</td>
                <td style="font-family: Courier; font-weight: bold; color: #d84315;">' . htmlspecialchars($user['password']) . '</td>
            </tr>';
        }

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>User Credentials</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #0066cc;
            margin-bottom: 10px;
        }
        .title {
            font-size: 18px;
            color: #666;
        }
        .info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #0066cc;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin-top: 20px;
            font-size: 11px;
            color: #856404;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">PLN IP Learning Hub</div>
        <div class="title">User Account Credentials - Bulk Creation</div>
    </div>

    <div class="info">
        <strong>📋 Summary:</strong> ' . count($users) . ' user accounts created on ' . date('d F Y, H:i:s') . ' WIB
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Name</th>
                <th style="width: 22%;">Email</th>
                <th style="width: 12%;">NIP</th>
                <th style="width: 18%;">Department</th>
                <th style="width: 23%;">Temporary Password</th>
            </tr>
        </thead>
        <tbody>
            ' . $rows . '
        </tbody>
    </table>

    <div class="warning">
        <strong>⚠️ SECURITY NOTICE:</strong><br>
        • All passwords listed are temporary and must be changed upon first login.<br>
        • Distribute this document securely to the respective users.<br>
        • This is a confidential document - handle with care.<br>
        • Users should destroy their credential copy after changing their password.
    </div>

    <div class="footer">
        Generated on ' . date('d F Y, H:i:s') . ' WIB<br>
        PLN IP Learning Hub Portal - Confidential Document<br>
        Total Users: ' . count($users) . '
    </div>
</body>
</html>';
    }

    /**
     * Generate PDF from HTML
     */
    private static function generatePdf(string $html, string $filename): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
