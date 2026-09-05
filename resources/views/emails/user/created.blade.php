<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .content {
            padding: 20px 0;
        }
        .credentials {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .credentials strong {
            display: inline-block;
            min-width: 120px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bienvenido, {{ $user->name }}</h1>
        </div>

        <div class="content">
            <p>Tu cuenta de usuario ha sido creada correctamente.</p>

            <h3>Tus datos de acceso:</h3>
            <div class="credentials">
                <div><strong>Email:</strong> {{ $user->email }}</div>
                <div><strong>Contraseña:</strong> {{ $plainPassword }}</div>
            </div>

            <p>
                <a href="{{ route('login') }}" class="button">Acceder al sistema</a>
            </p>

            <p>Por favor, cambia tu contraseña después del primer acceso por razones de seguridad.</p>

            <p>Si tienes problemas para acceder o requieres asistencia, por favor contacta al administrador.</p>
        </div>

        <div class="footer">
            <p>Saludos cordiales,<br>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>

