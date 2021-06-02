<?php

namespace euroglas\recursoshumanos;

class recursoshumanos implements \euroglas\eurorest\restModuleInterface
{
    // Nombre oficial del modulo
    public function name() { return "recursoshumanos"; }

    // Descripcion del modulo
    public function description() { return "Acceso a la informacion de Recursos Humanos"; }

    // Regresa un arreglo con los permisos del modulo
    // (Si el modulo no define permisos, debe regresar un arreglo vacío)
    public function permisos()
    {
        $permisos = array();

        // $permisos['_test_'] = 'Permiso para pruebas';

        return $permisos;
    }

    // Regresa un arreglo con las rutas del modulo
    public function rutas()
    {
        $items['/rh/empresas']['GET'] = array(
            'name' => 'Lista empresas',
            'callback' => 'listaempresas',
            'token_required' => TRUE,
        );

        $items['/rh/[i:idEmpresa]/huellas']['GET'] = array(
            'name' => 'Lista huellas',
            'callback' => 'listahuellas',
            'token_required' => TRUE,
        );

        $items['/rh/checada']['POST'] = array(
            'name' => 'Guarda una checada',
            'callback' => 'creaChecada',
            'token_required' => TRUE,
        );

        $items['/rh/usuario']['GET'] = array(
            'name' => 'Obten usuario de RH',
            'callback' => 'getUsuario',
            'token_required' => TRUE,
        );

        return $items;
    }

    public function getUsuario()
    {
        if( empty($_REQUEST['idempresa'])|| empty($_REQUEST['login']) )
        {
            http_response_code(400);
            header('content-type: application/json');
            die(json_encode( $this->reportaErrorUsandoHateoas(
                400002,
                "Faltan parametros",
                "Mínimo, requiero idempresa y login",
                $_REQUEST
            )));
        }

        $idEmpresa = $_REQUEST['idempresa'];
        $usrLogin = $_REQUEST['login'];

        // Obten un arreglo con los detalles de las empresas
        // La llave del arreglo, es el idEmpresa
        $empresas = $this->getListaEmpresas();

        // Valida que el ID es valido
        if( ! array_key_exists($idEmpresa,$empresas) )
        {
            http_response_code(400);
            header('content-type: application/json');
            die(json_encode( $this->reportaErrorUsandoHateoas(
                400001,
                "Empresa invalida",
                "No tengo una empresa con ID [{$idEmpresa}]"
            )));
        }

        $dbName = $empresas[$idEmpresa]["base"];

        //
        // Conexión a la bD adecuada
        //
        // No tenemos listadas todas las BDs, 
        // iniciamos conectando a rhn (Bases)
        $dbRH = $this->connect_db("rhn");

        // Luego, nos cambiamos de BD
        $dbRH->exec("USE {$dbName}");

        //
        // NOTA: Porque es opcional el password?
        //
        //    Esta funcion NO es para authenticar propiamente un usuario, eso se hace con /auth
        //    y para llamar ésta funcion, eso ya ocurrió (para entrar aquí, necesitamos un Token)
        //
        //    Esta funcion se usa para validar detalles de un usuario, por ejemplo, cuando cierta
        //    accion requiere autorizacion de un administrador, el administrador ingresa sus
        //    credenciales, y usamos esta funcion para obtener sus datos (y verificar que es admin)
        //
        //    Entonces, porque no solicitar siempre la contraseña? Porque hay otros casos donde solo
        //    quieres datos del usuario, sin validar sus credenciales (de nuevo, eso se haria con auth)
        //
        $queryStr = '';
        if( empty($_REQUEST['password']) )
        {
            $queryStr = "SELECT * FROM usuarios WHERE Usuario = '{$usrLogin}'";
        } else {
            $cve = md5($_REQUEST['password']);
            $queryStr = "SELECT * FROM usuarios WHERE Usuario = '{$usrLogin}' AND Clave='{$cve}'";
        }

        $sth = $dbRH->query($queryStr);

        $usuario = $sth->fetch(\PDO::FETCH_ASSOC);

        if( empty($usuario) )
        {
            http_response_code(404);
            header('content-type: application/json');
            die(json_encode( $this->reportaErrorUsandoHateoas(
                404,
                "Usuario invalido",
                "No se pudo reconocer ese usuario"
            )));
        }

        die( $this->formateaRespuesta($usuario) );
    }

    /**
     * Define que secciones de configuracion requiere
     *
     * @return array Lista de secciones requeridas
     */
    public function requiereConfig()
    {
        $secciones = array();

        $secciones[] = 'dbaccess';

        return $secciones;
    }

    private $config = array();

    /**
     * Carga UNA seccion de configuración
     *
     * Esta función será llamada por cada seccion que indique "requiereConfig()"
     *
     * @param string $sectionName Nombre de la sección de configuración
     * @param array $config Arreglo con la configuracion que corresponde a la seccion indicada
     *
     */
    public function cargaConfig($sectionName, $config)
    {
        $this->config[$sectionName] = $config;
    }

    /**
     * Conecta a la Base de Datos
     */
    private function connect_db($dbKey)
    {
        $unaBD = null;

        if ($this->config && $this->config['dbaccess'])
        {
            $unaBD = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);

            if( $unaBD->connect($dbKey) === false )
            {
                throw new Exception($unaBD->getLastError());
            }
        } else {
            throw new Exception("La BD no esta configurada");
        }

        return $unaBD;
    }

    function __construct()
    {
        $this->DEBUG = isset($_REQUEST['debug']);
    }

    public function listaEmpresas() {

        $datos = $this->getListaEmpresas();

        die( $this->formateaRespuesta($datos) );
    }

    
    public function listahuellas($idEmpresa) {

        $datos = $this->getListahuellas($idEmpresa);

        die( $this->formateaRespuesta($datos) );
    }

    public function getListaEmpresas() {
        $query = "SELECT ID_Empresa, base,  Descripcion FROM bases";

        $dbRH = $this->connect_db("rhn");

        $sth = $dbRH->query($query);

        if( $sth === false )
        {
            die( $dbRH->getLastError() );
        }

        $datos = $sth->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);

        return $datos;
    }

    public function getListahuellas( $idEmpresa ) {

        // Obten un arreglo con los detalles de las empresas
        // La llave del arreglo, es el idEmpresa
        $empresas = $this->getListaEmpresas();

        // Valida que el ID es valido
        if( ! array_key_exists($idEmpresa,$empresas) )
        {
            http_response_code(400);
            header('content-type: application/json');
            die(json_encode( $this->reportaErrorUsandoHateoas(
                400001,
                "Empresa invalida",
                "No tengo una empresa con ID [{$idEmpresa}]"
            )));
        }

        $dbName = $empresas[$idEmpresa]["base"];

        //
        // Conexión a la bD adecuada
        //
        // No tenemos listadas todas las BDs, 
        // iniciamos conectando a rhn (Bases)
        $dbRH = $this->connect_db("rhn");

        // Luego, nos cambiamos de BD
        $dbRH->exec("USE {$dbName}");

        // El primer campo se usa como llave del arreglo que se genera
        $query = "";
        $query .= "SELECT h.ID_Empresa, e.codigo as Codigo, e.Nombre, d.Descripcion AS Depto, p.Descripcion AS Puesto,  h.NumeroDedo, h.FMD ";
        $query .= "FROM huellas h ";
        $query .= "JOIN empleados e ON h.codigo = e.Codigo ";
        $query .= "JOIN departamentos d ON e.ID_Departamento = d.ID_Departamento ";
        $query .= "JOIN puestos p ON e.ID_Puesto = p.ID_Puesto ";

        $sth = $dbRH->query($query);

        if( $sth === false )
        {
            die( $dbRH->getLastError() );
        }

        $huellas = array();
        while ($datosDeHuella = $sth->fetch(\PDO::FETCH_ASSOC)) {
            
            $huellas[] = $datosDeHuella;
        }

        return $huellas;

    }

    public function creaChecada() {

        //echo( $this->formateaRespuesta( $_POST ) );

        // Obten un arreglo con los detalles de las empresas
        // La llave del arreglo, es el idEmpresa
        $empresas = $this->getListaEmpresas();

        $dbName = $_POST['dbname'];
        $codigo = $_POST['codigo'];
        $checador = $_POST['checador'];

        //
        // Conexión a la bD adecuada
        //
        // No tenemos listadas todas las BDs, 
        // iniciamos conectando a rhn (Bases)
        $dbRH = $this->connect_db("rhn");

        // Luego, nos cambiamos de BD
        $dbRH->exec("USE {$dbName}");

        /// PARCHE
        /// necesito revisar RHN para quitar referencias al ID_Empleado de las checadas
        $currentPDO = $dbRH->getCurrentConnection(); // Para tener acceso a todo PDO, no solo dbAccess
        $sth = $currentPDO->query("SELECT id_Empleado FROM empleados WHERE Codigo = {$codigo}");
        $idEmpleado = $sth->fetch(\PDO::FETCH_COLUMN);

        //echo "creando checada de empleado ID {$idEmpleado}";

        if( empty( $_POST['hora'] ) )
        {
            // Checada normal (usa la hora del servidor)
            $dbRH->exec("CALL ChecadaRegistrar({$idEmpleado},{$codigo},0,'{$checador}')");
        } else {
            // Checada "forzada" (muy posiblemente del vigilante)
            $hora = $_POST['hora'];
            $dbRH->exec("CALL ChecadasImprevisto({$idEmpleado},{$codigo},0,'{$checador}','{$hora}')");
        }
        
        http_response_code(201); // CREADO
        // Normalmente, se debe incluir un encabezado "Location" con la URL del "nuevo recurso"
        // como las checadas no son accessibles, omitimos dicho encabezado.
        // header('Location: /rh/checada/{idChecada}');
        die("OK");
    }

    private function formateaRespuesta($datos)
	{
		if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'JSON')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'JSON')!==false)
		)
		{
			header('content-type: application/json');
			return( json_encode( $datos ) );
		}
		else if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'CSV')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'CSV')!==false)
		)
		{
			$output = fopen("php://output",'w') or die("Can't open php://output");
			header("Content-Type:application/csv");
			foreach($datos as $dato) {
				if(is_array($dato))
				{
    				fputcsv($output, $dato);
				} else {
					fputs($output, $dato . "\n");
				}
			}
			fclose($output) or die("Can't close php://output");
			return;
			//return( json_encode( $datos ) );
		}
		else
		{
			// Formato no definido
			header('content-type: text/plain');
			return( print_r($datos, TRUE) );
		}
    }

        /**
     * Formatea el error usando estandar de HATEOAS
     * @param integer $code Codigo HTTP o Codigo de error interno
     * @param string  $userMessage Mensaje a mostrar al usuario (normalmente, con vocabulario sencillo)
     * @param string  $internalMessage Mensaje para usuarios avanzados, posiblemente con detalles técnicos
     * @param string  $moreInfoUrl URL con una explicación del error, o documentacion relacionada
     * @return string Error en un arreglo, para ser enviado al cliente usando json_encode().
    */
    private function reportaErrorUsandoHateoas($code=400, $userMessage='', $internalMessage='',$moreInfoUrl=null)
    {
        $hateoas = array(
            'links' => array(
                'self' => $_SERVER['REQUEST_URI'],
            ),
        );
        $hateoasErrors = array();
        $hateoasErrors[] = array(
            'code' => $code,
            'userMessage' => $userMessage,
            'internalMessage' => $internalMessage,
            'more info' => $moreInfoUrl,
        );

        $hateoas['errors'] = $hateoasErrors;

        // No lo codificamos con Jason aqui, para que el cliente pueda agregar cosas mas facilmente
        return($hateoas);
    }

    private $DEBUG = false;
}