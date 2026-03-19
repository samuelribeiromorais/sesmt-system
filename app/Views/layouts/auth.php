<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SESMT TSE</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f2f2ed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #001e21;
        }
        .login-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,30,32,0.12);
            width: 100%;
            max-width: 420px;
            padding: 48px 40px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo img {
            height: 60px;
        }
        .login-title {
            text-align: center;
            font-size: 14px;
            color: #005e4e;
            margin-top: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #001e21;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #001e21;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #00b279;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #005e4e;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #00b279;
        }
        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .alert-error {
            background: #fef2f2;
            color: #e74c3c;
            border: 1px solid #fecaca;
        }
        .alert-warning {
            background: #fffbeb;
            color: #f39c12;
            border: 1px solid #fde68a;
        }
    </style>
</head>
<body>
    <?= $content ?>
</body>
</html>
