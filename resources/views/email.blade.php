<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Confirma tu correo</title>
</head>

<body style="font-family: sans-serif; background-color: #f9fafb; padding: 20px;">
    <div
        style="max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 8px; padding: 30px; text-align: center; border: 1px solid #e5e7eb;">
        <img src="{{ asset('images/logo-lg.png') }}" alt="MenuGo" style="width: 120px; margin-bottom: 20px;" />

        <h1 style="color: #f97316;">¡Hola {{ $user->name }}!</h1>
        <p style="color: #4b5563; font-size: 16px;">
            Gracias por registrarte en <strong>MenuGo</strong>. Para comenzar a usar tu cuenta, confirma tu correo
            haciendo clic en el botón de abajo.
        </p>

        <a href="{{ $url }}"
            style="display: inline-block; margin: 20px 0; padding: 12px 25px; background-color: #f97316; color: white; border-radius: 6px; text-decoration: none; font-weight: bold;">
            Confirmar correo
        </a>

        <p style="color: #9ca3af; font-size: 14px; margin-top: 20px;">
            Si no creaste esta cuenta, simplemente ignora este mensaje.
        </p>
    </div>
</body>

</html>
