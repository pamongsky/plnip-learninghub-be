<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Reset Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #009CDE 0%, #0077B6 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .otp-box {
            background: #f8f9fa;
            border: 2px dashed #009CDE;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            color: #009CDE;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .expiry-notice {
            font-size: 14px;
            color: #dc3545;
            margin-top: 15px;
        }
        .instructions {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .instructions p {
            margin: 5px 0;
            font-size: 14px;
            color: #856404;
        }
        .security-notice {
            background: #e7f3ff;
            border-left: 4px solid #0056b3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-notice p {
            margin: 5px 0;
            font-size: 14px;
            color: #004085;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e9ecef;
        }
        .footer a {
            color: #009CDE;
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background: #e9ecef;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🔐 Reset Password</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">PLN IP Learning Hub</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                <p>Halo <strong>{{ $userName }}</strong>,</p>
            </div>

            <p>Kami menerima permintaan untuk mereset password akun Anda. Gunakan kode OTP berikut untuk melanjutkan proses reset password:</p>

            <!-- OTP Box -->
            <div class="otp-box">
                <div class="otp-label">Kode OTP Anda:</div>
                <div class="otp-code">{{ $otpCode }}</div>
                <div class="expiry-notice">
                    ⏱️ Kode ini berlaku selama {{ $expiresInMinutes }} menit
                </div>
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <p><strong>📋 Cara Menggunakan:</strong></p>
                <p>1. Kembali ke halaman verifikasi OTP</p>
                <p>2. Masukkan kode OTP di atas</p>
                <p>3. Buat password baru Anda</p>
            </div>

            <div class="divider"></div>

            <!-- Security Notice -->
            <div class="security-notice">
                <p><strong>🛡️ Peringatan Keamanan:</strong></p>
                <p>• Jangan bagikan kode OTP ini kepada siapapun, termasuk staff PLN IP</p>
                <p>• Jika Anda tidak meminta reset password, abaikan email ini</p>
                <p>• Kode OTP akan otomatis hangus setelah {{ $expiresInMinutes }} menit</p>
            </div>

            <p style="margin-top: 30px; font-size: 14px; color: #666;">
                Jika Anda memiliki pertanyaan atau membutuhkan bantuan, silakan hubungi HCIS di
                <a href="mailto:hcis@plnip.co.id" style="color: #009CDE;">hcis@plnip.co.id</a>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                <strong>PT PLN Indonesia Power</strong><br>
                Learning Hub Portal
            </p>
            <p style="margin: 0; color: #999;">
                Email ini dikirim secara otomatis, mohon tidak membalas email ini.
            </p>
            <p style="margin: 10px 0 0 0;">
                © {{ date('Y') }} PT PLN Indonesia Power. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
