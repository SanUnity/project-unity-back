<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>::Proyecto Unity::</title>
    </head>
    <body>
        <table border="0" style="font-family: Arial, Helvetica, sans-serif; margin: 0 auto; padding: 0; width: 600px; border-collapse: collapse;" width="600" height="500">
            <tr style="border-collapse: collapse;">
                <td style="text-align: center; border-collapse: collapse; background-color: #4d4d4d; border-bottom: 5px solid #AD033C; padding: 20px; height: 30px;">
                    <img src="{{ Config::get('app.url') }}/img/logo_gobierno.png" width="200">
                </td>
            </tr>
            <tr>
                <td style="background-color: #f1f1f1; padding: 25px 50px; vertical-align: top;">
                    <h1 style="font-size: 16px;">Hola,</h1>
                    <p style="font-size: 14px;">Has solicitado la verificación del emaill en la plataforma de {{ Config::get('app.name') }}.</p>
                    <p style="font-size: 14px;">Tu código de verificación es: <b>{{ $data['otp'] }}</b></p>
                    <br>
                    <p style="font-size: 13px;">Equipo del Proyecto Unity.</p>
                </td>
            </tr>
        </table>
    </body>
</html>