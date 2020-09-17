<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>::Proyecto Unity::</title>
    </head>
    <body>
        <table border="0" style="font-family: Arial, Helvetica, sans-serif; margin: 0 auto; padding: 0; width: 600px; border-collapse: collapse;" width="600" height="500" summary="content mail">
            <tr style="border-collapse: collapse;">
                <th style="text-align: center; border-collapse: collapse; background-color: #4d4d4d; border-bottom: 5px solid #AD033C; padding: 20px; height: 30px;" scope="col">
                    <img src="{{ Config::get('app.url') }}/img/Logo.png" width="200" alt="logo">
                </th>
            </tr>
            <tr>
                <td style="background-color: #f1f1f1; padding: 25px 50px; vertical-align: top;">
                    <h1 style="font-size: 16px;">Hola, {{ $data['name'] }}</h1>
                    <p style="font-size: 14px;">Se ha bloqueado tu cuenta por superar el máximo número de intentos fallidos en {{ Config::get('app.name') }}.</p>
                    <p style="font-size: 14px;">Para crear una nueva contraseña tienes que acceder al siguiente link:</p>
                    <br>
                    <a style="color: #AD033C;" href="{{ Config::get('app.url') }}/dashboard/password/{{ $data['hash'] }}">Crear contraseña</a>
                    <br>
                    <br>
                    <p style="font-size: 13px;">Equipo del Proyecto Unity.</p>
                </td>
            </tr>
        </table>
    </body>
</html>