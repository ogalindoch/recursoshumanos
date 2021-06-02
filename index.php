<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

const TOKENSECRET = '672995BFB8C8D3892A65E97A9BFA4C49';

// use euroglas\eurorest;

//
// Verifica si nos indicaron que servidor somos, como variable de medio ambiente
$ApiName = '';
if( !empty($_ENV['API_NAME']) )
{
	// Ok, ya tenemos un nombre de la API, de ahi, el servidor va a asumir el nombre del archivo de configuracion.
	$ApiName = $_ENV['API_NAME'];
}

// El constructor debe:
// - Definir que archivo(s) de configuración leer
// - En base al archivo de configuración, cargar los modulos activos
// - Definir las rutas (incluidas las basicas, como OPTIONS)
$servidor = new euroglas\eurorest\restServer($ApiName);

// Inicializa el Secreto usado para encriptar los Token
$servidor->SetSecret( TOKENSECRET );

// Ahora, 
// - Valida que la URL solicitada, sea una ruta valida
// - Valida que el callback asociado, es "ejecutable"
// - Si la ruta así lo define, verifica 
//		- La llamada incluye el Token requerido
//		- El usuario que corresponde al Token, tiene permiso de accesar la URL
// - Ejecuta el callback, y manda el resultado de regreso
// = En caso de error en alguno de los pasos anteriores, genera el HATEOAS adecuado
// ==> OJO, esta una vez que se hace esta llamada, ya no vamos a regresar aquí
//			(una de dos, la llamada se ejecuto y se regreso el resultado, o hubo el error y se reportó)
$servidor->matchAndProcess();