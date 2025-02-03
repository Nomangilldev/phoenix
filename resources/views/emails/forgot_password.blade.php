<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .email-header {
            background-color: #4CAF50;
            color: #ffffff;
            text-align: center;
            padding: 20px;
        }
        .email-content {
            padding: 30px;
            color: #333333;
            line-height: 1.6;
        }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            text-align: center;
            margin: 20px 0;
        }
        .email-footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #777777;
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Email Header -->
        <div class="email-header">
            <h1>Password Reset Request</h1>
        </div>

        <!-- Email Content -->
        <div class="email-content">
            <p>Hello,</p>
            <p>We received a request to reset your password. Please use the following OTP code to reset your password:</p>

            <div class="otp-code">{{ $otp }}</div>

            <p>If you didn't request a password reset, please ignore this email. This OTP code will expire shortly.</p>
            <p>Thank you,<br>Your Company Team</p>
        </div>

        <!-- Email Footer -->
        <div class="email-footer">
            <p>&copy; {{ date('Y') }} Your Company. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
