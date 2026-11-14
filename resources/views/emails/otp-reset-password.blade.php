<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode Verifikasi Reset Kata Sandi</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fb;
            color: #1e293b;
        }
        .container {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(11, 31, 58, 0.08);
        }
        .header {
            background-color: #0B1F3A;
            padding: 28px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .body {
            padding: 32px 24px;
            text-align: center;
        }
        .body p {
            margin: 0 0 16px;
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
        }
        .otp-box {
            margin: 24px 0;
        }
        .otp-code {
            display: inline-block;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #0B1F3A;
            background: #f0f5ff;
            border: 2px dashed #2E6BE6;
            border-radius: 12px;
            padding: 16px 32px;
        }
        .expired {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 12px;
        }
        .warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            color: #9a3412;
            margin-top: 20px;
            text-align: left;
        }
        .footer {
            background: #f8fafc;
            padding: 20px 24px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 0;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .footer a {
            color: #2E6BE6;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Arsip Dokumen Grogol Selatan</h1>
            <p>Kantor Lurah Grogol Selatan</p>
        </div>
        <div class="body">
            <p>Halo,</p>
            <p>
                Kami menerima permintaan reset kata sandi untuk akun
                <strong>{{ $email }}</strong>.
                Gunakan kode berikut untuk melanjutkan:
            </p>
            <div class="otp-box">
                <span class="otp-code">{{ $otp }}</span>
            </div>
            <p class="expired">Kode ini berlaku selama 3 menit.</p>
            <div class="warning">
                <strong>Perhatian:</strong> Jika Anda tidak melakukan permintaan ini, abaikan email ini.
                Jangan bagikan kode ini kepada siapapun.
            </div>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} Kelurahan Grogol Selatan</p>
            <p>Sistem internal untuk petugas berwenang.</p>
        </div>
    </div>
</body>
</html>
