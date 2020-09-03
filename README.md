# PROJECT UNITY - BACKEND
Project Unity es un proyecto abierto con el que se pretende ayudar en la lucha contra el COVID-19. Desde el proyecto de backend se acceso a las APIS de las que hacen uso el proyecto de front, ios, android y el dashbaord.

## Instalación
 
Para poder ejecutar el proyecto en local tenemos que tener instalado

- php >= 7.2.5
- BCMath PHP Extension
- Ctype PHP Extension
- Fileinfo PHP extension
- JSON PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
- Composer

Para poder tener el modulo de Exposure notification funcionando necesiamos instalar

- php7.3-dev php-pear phpunit gcc libz-dev
- pecl install grpc
  
Después hacemos un clone del proyecto y dentro de la carpeta del proyecto ejecutamos `composer install`.

Para poder lanzar el proyecto en local podemos hacerlo de dos formas:

- Ejecutando `php artisan serve`, esto nos levantara el proyecto en local http://localhost:8000
- Añadiendo un virtualhost en el apache, se necesita tener el mod_rewrite activo:

```
<VirtualHost *:80>
    ServerName unity.local
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/unity/public

    <Directory /var/www/html/unity>
        AllowOverride All
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>

```

## Variables de entorno
Para la configuración del entorno hay que copiar el fichero .env.example a .env y modificar las variables necesarias. A continuación el uso de cada una de las variables de entorno.

 - **APP_NAME**, nombre del proyecto
 - **APP_ENV**, entorno, local, production, pre...
 - **APP_KEY**, clave con la que se cifran los datos sensibles, para generarla ejecutar `php artisan key:generate`
 - **APP_DEBUG**, activar el debug de laravel
 - **APP_URL**, url de la aplicación back
 - **APP_FRONT_URL**, url del dashboard
 - **ELASTICSEARCH_HOSTS**, uri de los nodos de elasticsearch
 - **ELASTICSEARCH_RETRIES**, número de intentos de conexión al cluster de elasticsearch, rellenar con el número de nodos del cluster
 - **EMAIL_NO_REPLY**, origen de los correos enviados por el backend
 - **PUSH_TITLE**, título para las notificaciones push enviadas por el backend
 - **TIME_BETWEEN_TEST**, tiempo que tiene que esperar un perfil para realizar otro test de autodiagnóstico, en segundos
 - **ENCRYPTION_SALT**, salt para hashear los correos y teléfonos de los usuarios
 - **AWS_ACCESS_KEY_ID**,  access key de aws para usar sus servicios
 - **AWS_SECRET_ACCESS_KEY**, secret key de aws para usar sus servicios
 - **AWS_DEFAULT_REGION**, region del aws,
 - **MAIL_MAILER**, proveedor de emails, por defecto ses (Amazon SES)
 - **SNS_AWS_DEFAULT_REGION**, region de aws para envio de notificaciones push y SMS
 - **SNS_IOS_TARGET**, target para las notificaciones push con IOS en AWS, APNS_SANDBOX o APNS
 - **QUEUE_CONNECTION**, proveedor de cola, por defecto sqs (Amazon sqs)
 - **SQS_PREFIX**, url de la cola SQS
 - **SQS_QUEUE**, nombre de la cola
 - **SQS_AWS_DEFAULT_REGION**, region de AWS donde se encuentra la cola SQS
 - **TOKEN_BACK**, token de autenticación para que la funciona lambda se autentique con el back
 - **PCR_RESULT_TOKEN**, token de autenticación para que el servicio de resultados de resultados PCR se autentique con el back
 - **JWT_DOMAIN**, dominio del token JWT generado para las sesiones de usuario
 - **JWT_TIME**, tiempo de vida del token JWT, en segundos
 - **JWT_TIME_ADMIN**, tiempo de vida del token JWT para los usuarios admin, en segundos
 - **JWT_PRIVATE_KEY**, clave privada para generar el token JWT, clave RSA
 - **JWT_PUBLIC_KEY**, clave pública para verificar el token JWT, clave RSA
 - **SMS_CONTACT_EXPOSED**, contenido del SMS enviado a un contacto cuando un usuario ha dado positivo en un test PCR
 - **SMS_RESULT_TEST**, contenido del SMS enviado a un usuario cuando se tienen los resultados de un test PCR
 - **BLUETRACE_PASSWORD**, contraseña con la que se cifran los tempID
 - **BLUETRACE_REFRESH_INTERVAL**, cuando se debería volver a obtener tempIDs, en horas
 - **BLUETRACE_PERIOD**, cuantos minutos dura cada tempID
 - **BLUETRACE_BATCHSIZE**, cuantos tempID se generan en cada petición
 - **DP3T_PRIVATE_KEY**, clave privada para firmar los datos de los tokens de usuarios infectados, clave EC
 - **DP3T_PUBLIC_KEY**, clave pública para firmar los datos de los tokens de usuarios infectados, clave EC
 - **DP3T_BUNDLEID**, bundleid de la aplicacion ios
 - **DP3T_ANDROID_PACKAGE**, android package
 - **DP3T_REGION**, región donde se usará exposure notification, ejemplo "sp"
 - **DP3T_KEY_IDENTIFIER**, region en formato 3 digitos (MCC mobile country code), para compatibilidad con apple
 - **MODULE_BLUETRACE**, activar las apis para el módulo de BlueTrace
 - **MODULE_DP3T**, activar las apis para el módulo de DP3T (no se puede activar a la vez que EN)
 - **MODULE_EXPOSURE_NOTIFICATION**, activar las apis para el módulo de Exposure Notification (no se puede activar a la vez que DP3T)
 - **MODULE_PCR**, activar las apis para el módulo de recibir los resultados de PCR
 - **MODULE_SEMAPHORE**, activar las apis para el módulo de la desescalada
 - **MODULE_EXIT_REQUESTS**, activar las apis para el módulo de peticiones de salida

## Configuración base de datos
Como base de datos utilizamos una noSQL (elasticsearch), una vez configurado el fichero .env podemos ejecutar el comando `php artisan elasticsearch:create`, este comando creará los índices necesarios para el uso de la aplicación.

## Comandos

Existen una serie de comandos creados en el proyecto de laravel para poder inicializar datos y realizar pruebas. Los comandos se ejecutan con `php artisan {comando}` en la carpeta del proyecto.

 - **import:postalCode**, para importar el listado de códigos postales de un pais, con sus estados, municipios y localidades. En el proyecto viene incluido el de México
 - **admin:create** , permite crear usuarios administradores
 - **decrypt:text**, permite descifrar un dato cifrado que hayamos obtenido manualmente de la base de datos
 - **dummy:create**, crea una base de usuarios fake, para pruebas
 - **elasticsearch:create**, crear los índices de elasticsearch, tiene el atributo --clear para resetear un indice
 - **export:qrlist**, crea un fichero con los códigos de laboratorio cifrados para crear los QR de laboratorios de test PCR
 - **hospitals:create**, importa el listado de hospitales
 - **import:statesInfo**, importa los códigos de estado, municipio para vincularlo con el geoJSON en el mapa de desescalada
 - **pcr:validate**, asignar resultado de un PCR manualmente
 - **push:test**, probar el envío de notificaciones push manualmente
 - **sms:test**, probar el envío de SMS manualmente
