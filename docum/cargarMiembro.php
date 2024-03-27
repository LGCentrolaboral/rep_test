<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use Illuminate\Support\Facades\Log;
use SimpleXLSX;
use App\Models\{TramiteAsociacion, TramiteEstatusComponente};
use App\Models\{Domicilio, PersonaDomicilio, TipoPersonasAsociacion};
use App\Models\{Patron, Persona, Asociacion, AsociacionPersona, AsociacionesAsociaciones, Tramite};
use Symfony\Component\Debug\Exception\FatalThrowableError;

class CargarMiembros
{
	public $es_miembro = true;
	/**
	 * CARGA DEARCHIVO PARA GUARDAR MIEMBROS
	 */
	public function upload($asociacion_id, $file, $tramite_id, $es_miembro = true, $es_patronal = 0)
	{
		$response = [
			'estatus' => 'error',
			'mensaje' => 'Ocurrio un error al registrar los usuarios.'
		];
		$this->es_miembro = $es_miembro;
		if ($file->getClientOriginalExtension() == 'csv')
			//Log::channel('single')->info('oaaaaa');
			$response = $this->uploadMiembrosCsv($asociacion_id, $file, $tramite_id);

		if ($file->getClientOriginalExtension() == 'xlsx' || $file->getClientOriginalExtension() == 'xls')
			$response = $this->uploadMiembrosExcel($asociacion_id, $file, $tramite_id, $es_patronal);
		if (empty($response->errores))
			$setInfoCurp = $this->setInfoCurp($tramite_id, $asociacion_id);
		//dd($response);
		return $response;
	}

	/**
	 * GUARDAR MIEMBRO
	 */
	public function guardarMiembro($miembro, $asociacion_id, $tramite_id, $es_miembro = 1)
	{

		$curp_obligatorio = $miembro['curp'];
		if ($curp_obligatorio == 'no_es_obligatorio') {
			$persona = Persona::create($miembro);
			if (!isset($persona->id))
				return [
					'estatus' => 'error',
					'mensaje' => 'Ocurrio un error al registrar a la empresa ' . $miembro['nombre'] . '.'
				];
		} else {
			$persona = Persona::whereCurp($miembro['curp'])->first();

			if (!isset($persona->id)) {
				$persona = Persona::create($miembro);
			}

			//$this->validateCurp($persona);
			if (!isset($persona->id))
				return [
					'estatus' => 'error',
					'mensaje' => 'Ocurrio un error al registrar al usuario con CURP ' . $miembro['curp'] . '.'
				];
		}


		$relation = AsociacionPersona::where('persona_id', $persona->id)->where('asociacion_id', $asociacion_id)->where('tramite_id', $tramite_id)->first();

		if (!isset($relation->id)) {
			$realtion = [
				'persona_id' => $persona->id,
				'asociacion_id' => $asociacion_id,
				'tramite_id' => $tramite_id,
				'es_miembro' => $es_miembro
			];
			AsociacionPersona::create($realtion);
		} else {
			return ['estatus' => 'error', 'mensaje' => 'Miembro con CURP ' . $miembro['curp'] . ' ya está registrado en la asociación.', 'persona' => $persona];
		}

		$this->registrarDomicilio($miembro['domicilio'], $persona->id);

		// $this->actualizarEstatusComponente($persona, $asociacion_id, $tramite_id);

		return ['estatus' => 'exito', 'mensaje' => 'El usuario ' . $miembro['nombre_completo'] . ' quedo registrado con exito.', 'persona' => $persona];
	}

	public function guardarAsociacionPersona($miembro, $asociacion_id, $tramite_id, $tipo = 'trabajador')
	{
		$persona = Persona::whereCurp($miembro['curp'])->first();

		if (!isset($persona->id))
			$persona = Persona::create($miembro);
		//$this->validateCurp($persona);
		if (!isset($persona->id))
			return [
				'estatus' => 'error',
				'mensaje' => 'Ocurrio un error al registrar al usuario con CURP ' . $miembro['curp'] . '.'
			];

		$relation = TipoPersonasAsociacion::where('persona_id', $persona->id)
			->where('asociacion_id', $asociacion_id)
			->where('tramite_id', $tramite_id)
			->where('tipo', $tipo)
			->first();

		if (!isset($relation->id)) {
			$realtion = [
				'persona_id' => $persona->id,
				'asociacion_id' => $asociacion_id,
				'tramite_id' => $tramite_id,
				'tipo' => $tipo,
				'es_representante' => false,
			];

			TipoPersonasAsociacion::create($realtion);
		} else {
			return ['estatus' => 'error', 'mensaje' => 'Miembro con CURP ' . $miembro['curp'] . ' ya está registrado en la asociación.', 'persona' => $persona];
		}
		$tipo_tramite = Tramite::where('id', $tramite_id)->first();
		if ($tipo_tramite->tipo_tramite_id != 10) {

			if (isset($miembro['domicilio'])) {
				$this->registrarDomicilio($miembro['domicilio'], $persona->id);
			}
		}

		// $this->actualizarEstatusComponente($persona, $asociacion_id, $tramite_id);

		return ['estatus' => 'exito', 'mensaje' => 'El usuario ' . $miembro['nombre_completo'] . ' quedo registrado con exito.', 'persona' => $persona];
	}

	/**
	 * Importar miembros actuales a otro tramite
	 * @return [type] [description]
	 */
	public function importar($asociacion_id, $miembros, $tramite_destino_id)
	{
		$total = 0;

		if ($tramite_destino_id < 1) return $total;

		if (count($miembros) > 0) {
			foreach ($miembros as $miembro) {
				$personarelacion = AsociacionPersona::where('persona_id', $miembro->id)
					->where('asociacion_id', $asociacion_id)
					->where('tramite_id', $tramite_destino_id)->first();

				if (isset($personarelacion->id)) continue;

				$realtion = [
					'persona_id' => $miembro->id,
					'asociacion_id' => $asociacion_id,
					'tramite_id' => $tramite_destino_id,
					'es_miembro' => $miembro->pivot->es_miembro
				];

				AsociacionPersona::create($realtion);

				$total++;
			}
		}

		return $total;
	}

	/**
	 * Importar miembros actuales a otro tramite
	 * @return [type] [description]
	 */
	public function importarDirectiva($asociacion_id, $directiva, $tramite_destino_id)
	{
		$total = 0;

		if ($tramite_destino_id < 1) return $total;

		if (count($directiva) > 0) {
			foreach ($directiva as $registro) {

				TipoPersonasAsociacion::create([
					'asociacion_id' => $asociacion_id,
					'persona_id' => $registro->persona_id,
					'tramite_id' => $tramite_destino_id,
					'tipo' => $registro->tipo,
					'puesto' => $registro->puesto
				]);

				$total++;
			}
		}

		return $total;
	}

	/**
	 * ACTUALIZACION ESTATUS COMPONENTE
	 */
	public function actualizarEstatusComponente($persona, $asociacion_id)
	{
		$miembros = AsociacionPersona::where('asociacion_id', $asociacion_id)->count();

		if ($miembros >= 20) {
			$componente = TramiteEstatusComponente::where('tramite_id', $tramite_id)->where('componente', 'miembros')->first();

			if (isset($componente->id)) {
				$componente->update(['estatus' => 1]);
			} else {
				TramiteEstatusComponente::create([
					'tramite_id' => $tramite_as->tramite_id,
					'componente' => 'miembros',
					'estatus' => 1
				]);
			}
		} else {
			$tramite_as = TramiteAsociacion::where('asociacion_id', $asociacion_id)->first();

			$componente = TramiteEstatusComponente::where('tramite_id', $tramite_as->tramite_id)->where('componente', 'miembros')->first();

			if (isset($componente->id))
				$componente->update(['estatus' => 0]);
		}
	}

	/**
	 * REGISTRAR EL DOMICILIO DEL MIEMBRO
	 */
	public function registrarDomicilio($domicilio, $persona_id)
	{
		$existe = PersonaDomicilio::where('persona_id', $persona_id)->first();

		if ($existe)
			return false;

		$domicilio = Domicilio::create($domicilio);

		PersonaDomicilio::create([
			'persona_id' => $persona_id,
			'domicilio_id' => $domicilio->id
		]);
	}

	/**
	 * CARGAR MIEMBROS DESDE CSV
	 */
	public function uploadMiembrosCsv($asociacion_id, $file, $tramite_id)
	{
		$csv   = fopen($file->getRealPath(), "r");
		$lector = file_get_contents($file->getRealPath());
		$lector = mb_convert_encoding($lector, 'utf-8');
		fclose($csv);

		$miembros = explode("\n", $lector);
		//$miembros = explode(";", $lector);

		return $this->uploadMiembrosArr($miembros, $asociacion_id, $tramite_id);
	}

	/**
	 * CARGAR MIEMBROS DESDE UN XLSX
	 */
	private function uploadMiembrosExcel($asociacion_id, $file, $tramite_id, $es_patronal)
	{
		$xlsx = SimpleXLSX::parse($file->getRealPath());
		if ($xlsx == false || !$xlsx->success())
			return [
				'estatus' => 'error',
				'mensaje' => 'El documento que intentas cargar esta dañado, intenta con otro.'
			];

		return $this->uploadMiembrosArr($xlsx->rows(), $asociacion_id, $tramite_id, $es_patronal);
	}


	public function miembrosExcelToArray($file)
	{

		return SimpleXLSX::parse($file->getRealPath());
	}

	/**
	 * RECORRE FILA POR FILA DEL ARCHIVO PARA CREAR EL MIEMBRO
	 */
	private function uploadMiembrosArr($miembros, $asociacion_id, $tramite_id, $es_patronal)
	{
		//dd($miembros);

		$logRegistros = [
			'exitosos' => 0,
			'errores' => [],
			'directiva_asociacion' => [],
			'secciones' => []
		];

		$error = false;
		$curps = [];

		if (!empty($miembros)) {

			//Cambio 1 
			$tipo_tramite = Tramite::whereId($tramite_id)->first();

			$resultados = [];
			$i=0;

			set_time_limit(700);

			foreach ($miembros as $key => $miembro_datos) {
				if ($key == 0) continue;

				$linea = $key + 1;

				if (!is_array($miembro_datos))
					$miembro_datos = explode(",", $miembro_datos);

				

				//$tipo_tramite = Tramite::whereId($tramite_id)->first();
				if ($tipo_tramite->tipo_tramite_id == 5) {
					if ($es_patronal == 1) {
						$curp = 'no_es_obligatorio';
					} else {
						$dato_curp = trim($miembro_datos[4]);
						$curp = isset($dato_curp) ? $dato_curp : '';
					}
				} else {
					$dato_curp = isset($miembro_datos[3]) ? trim($miembro_datos[3]) : '';
					$curp = isset($dato_curp) ? $dato_curp : '';
				}

				if (in_array($curp, $curps)) {
					$logRegistros['errores'][$linea] = ['estatus' => 'error', 'mensaje' => 'La CURP ' . $curp . ' se encuentra repetida en el archivo.'];
					$error = true;
					continue;
				}

				$promise = $this->validacionesMiembroCsv($miembro_datos,$asociacion_id,$tramite_id,$curp);

				$promise->then(
					function($data) use($i){
						$i = $i + 1;
						var_dump($data);
						$resultados = $data;
					}
				);
				$promise->wait();

				

				//dd('FIN');

				//$valido = $this->validacionesMiembroCsv($miembro_datos, $asociacion_id, $tramite_id, $curp);

				//dd($miembros, $miembro_datos, $valido);	

				// if ($curp != 'no_es_obligatorio') {
				// 	$curps[] = $curp;

				// 	if (@$valido['estatus'] == 'error-vacia')
				// 		continue;

				// 	if (@$valido['estatus'] == 'error') {
				// 		$logRegistros['errores'][$linea] = $valido;
				// 		$error = true;
				// 		continue;
				// 	}
				// }
			}

			dd($resultados);
		}


		// if (!empty($miembros) and !$error) {

		// 	foreach ($miembros as $key => $miembro_datos) {

		// 		if ($key == 0) continue;

		// 		$linea = $key + 1;

		// 		if (!is_array($miembro_datos))
		// 			$miembro_datos = explode(",", $miembro_datos);

		// 		if (count($miembro_datos) < 3)
		// 			continue;
				
		// 		////$tipo_tramite = Tramite::whereId($tramite_id)->first();
		// 		$id_tipo_tramite = $tipo_tramite->tipo_tramite_id;
		// 		$persona = $this->arrPersona($miembro_datos, $id_tipo_tramite, $curp);
		// 		if ($this->es_miembro) {
		// 			$response = $this->guardarMiembro($persona, $asociacion_id, $tramite_id);

					
		// 		} else {
		// 			$response = $this->guardarAsociacionPersona($persona, $asociacion_id, $tramite_id);
				
		// 		}
		// 		if ($response['estatus'] == 'exito') $logRegistros['exitosos'] = $logRegistros['exitosos'] + 1;

		// 		if ($response['estatus'] == 'error') $logRegistros['errores'][$linea] = $response;
		// 	}
		// }

		//dd($miembros, $logRegistros);
		
		//return $logRegistros;
	}

	/**
	 * REGRESA UN ARREGLO DEL MIEMBRO A GENERAR
	 */
	private function arrPersona($miembro_datos, $id_tipo_tramite, $curp)
	{
		//        no_es_obligatorio
		if ($id_tipo_tramite == 5) {
			unset($miembro_datos[0], $miembro_datos[6], $miembro_datos[7]);
			$miembro_datos = array_values($miembro_datos);
			$nombre = isset($miembro_datos[0]) ? $miembro_datos[0] : '';
			if ($curp == 'no_es_obligatorio') {
				$curp = 'no_es_obligatorio';
				$pa = '';
				$sa = '';
				$rfc = isset($miembro_datos[1]) ? $miembro_datos[1] : '';
				$empresa = isset($miembro_datos[3]) ? $miembro_datos[3] : '';
				$calle = isset($miembro_datos[2]) ? $miembro_datos[2] : '';
				$no_exterior = '';
				$no_interior = '';
				$colonia = '';
				$municipio_alcaldia = '';
				$estado = '';
				$cp = '';
			} else {
				$pa = isset($miembro_datos[1]) ? $miembro_datos[1] : '';
				$sa = isset($miembro_datos[2]) ? $miembro_datos[2] : '';
				$curp = isset($miembro_datos[3]) ? $miembro_datos[3] : '';
				$empresa = isset($miembro_datos[4]) ? $miembro_datos[4] : '';
				$calle = isset($miembro_datos[5]) ? $miembro_datos[5] : '';
				$no_exterior = isset($miembro_datos[6]) ? $miembro_datos[6] : '';
				$no_interior = isset($miembro_datos[7]) ? $miembro_datos[7] : '';
				$colonia = isset($miembro_datos[8]) ? $miembro_datos[8] : '';
				$municipio_alcaldia = isset($miembro_datos[9]) ? $miembro_datos[9] : '';
				$estado = isset($miembro_datos[10]) ? $miembro_datos[10] : '';
				$cp = isset($miembro_datos[11]) ? $miembro_datos[11] : '';
				$rfc = '';
			}
		} else {
			$nombre = isset($miembro_datos[0]) ? $miembro_datos[0] : '';
			$pa = isset($miembro_datos[1]) ? $miembro_datos[1] : '';
			$sa = isset($miembro_datos[2]) ? $miembro_datos[2] : '';
			$curp = isset($miembro_datos[3]) ? $miembro_datos[3] : '';
			$empresa = isset($miembro_datos[4]) ? $miembro_datos[4] : '';
			$calle = isset($miembro_datos[5]) ? $miembro_datos[5] : '';
			$no_exterior = isset($miembro_datos[6]) ? $miembro_datos[6] : '';
			$no_interior = isset($miembro_datos[7]) ? $miembro_datos[7] : '';
			$colonia = isset($miembro_datos[8]) ? $miembro_datos[8] : '';
			$municipio_alcaldia = isset($miembro_datos[9]) ? $miembro_datos[9] : '';
			$estado = isset($miembro_datos[10]) ? $miembro_datos[10] : '';
			$cp = isset($miembro_datos[11]) ? $miembro_datos[11] : '';
			$rfc = '';
		}
		$arr = [
			'nombre' => $nombre,
			'primer_apellido' => $pa,
			'segundo_apellido' => $sa,
			'nombre_completo' => $nombre . ' ' . $pa . ' ' . $sa,
			'curp' => $curp,
			'empresa' => $empresa,
			'rfc' => $rfc,
			'domicilio' => [
				'calle' => $calle,
				'no_exterior' => $no_exterior,
				'no_interior' => $no_interior,
				'colonia' => $colonia,
				'municipio_alcaldia' => $municipio_alcaldia,
				'estado' => $estado,
				'cp' => $cp
			]
		];
		return $arr;
	}

	private function asignarSeccion($miembro_datos, $asociacion_id, $tramite_id, $persona)
	{
		if (!isset($miembro_datos[12]) || $miembro_datos[12] == '')
			return ['estatus' => 'error', 'mensaje' => 'No pertenece a una sección.', 'persona' => $persona];

		$nombre = isset($miembro_datos[12]) ? $miembro_datos[12] : '';
		$seccion = $this->getSeccion($nombre, $asociacion_id, $tramite_id);

		$es_directiva = isset($miembro_datos[13]) ? $miembro_datos[13] : 0;
		$puesto = isset($miembro_datos[13]) ? $miembro_datos[13] : '';

		$personarelacion = $this->asignarMiembroSeccion($persona->id, $seccion->id, $tramite_id);

		if ($es_directiva == 1)
			$directivaSeccion = $this->asignarDirectiva($seccion->id, $persona->id, $tramite_id, $puesto, $persona);

		return ['estatus' => 'exito', 'seccion' => $seccion];
	}

	/**
	 * REGRESA LA ASOCIACION EN BASE AL NOMBRE SI NO EXITE LA GENERA
	 * (falta checar que pasara cuando sea un tramite de modificacion de miembros)
	 */
	public function getSeccion($nombre, $asociacion_id, $tramite_id)
	{
		$asociacion = Asociacion::findOrFail($asociacion_id);

		$seccion = Asociacion::whereRaw("translate(nombre, 'áàéèíìóòúù', 'aaeeiioouu') = translate('" . $nombre . "', 'áàéèíìóòúù', 'aaeeiioouu')")
			->first();

		if (!isset($seccion->id)) {

			$seccion = Asociacion::create([
				'nombre' => $nombre,

			]);

			$seccion->update(['id_historico' => $seccion->id]);

			AsociacionesAsociaciones::create([
				'id_historico' => $asociacion->id_historico,

			]);

			TramiteAsociacion::create([
				'tramite_id' => $tramite_id,
				'asociacion_id' => $seccion->id,
			]);
		}

		return $seccion;
	}

	public function asignarMiembroSeccion($persona_id, $seccion_id, $tramite_id)
	{
		$personarelacion = AsociacionPersona::where('persona_id', $persona_id)->where('asociacion_id', $seccion_id)->where('tramite_id', $tramite_id)->first();

		if (isset($personarelacion->id))
			return [
				'estatus' => 'error',
				'mensaje' => 'Ya esta registrado el miembro en la seccion.',
			];

		$realtion = [
			'persona_id' => $persona_id,
			'asociacion_id' => $seccion_id,
			'tramite_id' => $tramite_id,
			'es_miembro' => 1
		];

		AsociacionPersona::create($realtion);

		return ['estatus' => 'exito', 'mensaje' => 'El usuario quedo registrado con exito en la seccion.'];
	}


	public function asignarDirectiva($asociacion_id, $persona_id, $tramite_id, $puesto, $persona = [])
	{
		$texto = isset($persona->id) ? ' ' . $persona->nombre_completo : '';

		if ($puesto == '')
			return ['estatus' => 'error', 'mensaje' => 'Falta agregar el puesto en la directiva para el usuario' . $texto . '.'];

		// validar si ya existe o no

		$exist = TipoPersonasAsociacion::where('asociacion_id', $asociacion_id)
			->where('persona_id', $persona_id)->where('tramite_id', $tramite_id)
			->whereTipo('directiva')->first();

		if (isset($exist->id))
			return ['estatus' => 'error', 'mensaje' => 'El usuario ya es parte de la directiva' . $texto . '.'];

		TipoPersonasAsociacion::create([
			'asociacion_id' => $asociacion_id,
			'persona_id' => $persona_id,
			'tramite_id' => $tramite_id,
			'tipo' => 'directiva',
			'puesto' => $puesto
		]);

		$texto = isset($persona->id) ? ' ' . $persona->nombre_completo : '';;
		return ['estatus' => 'exito', 'mensaje' => 'El miembro' . $texto . ' se actualizo correctamente.'];
	}


	/**
	 * Validar carga de un miembro mediante CSV
	 */
	private function validacionesMiembroCsv($miembro_datos, $asociacion_id, $tramite_id, $curp)
	{
		$promise = new Promise(
			function() use( &$promise,$miembro_datos, $asociacion_id, $tramite_id, $curp)
			{
				if (count($miembro_datos) < 3){
					$promise->resolve(['estatus' => 'error-vacia', 'mensaje' => 'Al parecer la fila se encuentra vacía.']);
					return $promise;
				}

				$tipo_tramite = Tramite::whereId($tramite_id)->first();

				if ($tipo_tramite->tipo_tramite_id == 5) {
					unset($miembro_datos[0], $miembro_datos[6], $miembro_datos[7]);
					$miembro_datos = array_values($miembro_datos);
					$nombre = isset($miembro_datos[0]) ? $miembro_datos[0] : '';
					$pa = isset($miembro_datos[1]) ? $miembro_datos[1] : '';
					$ma = isset($miembro_datos[2]) ? $miembro_datos[2] : '';
					if ($curp == 'no_es_obligatorio') {
						$curp_ = 'no_es_obligatorio';
					} else {
						$data3 = isset($miembro_datos[3]) ? $miembro_datos[3] : '';
						$curp_ = trim($data3);
					}
				} else {
					$nombre = isset($miembro_datos[0]) ? $miembro_datos[0] : '';
					$pa = isset($miembro_datos[1]) ? $miembro_datos[1] : '';
					$ma = isset($miembro_datos[2]) ? $miembro_datos[2] : '';
					$data3 = isset($miembro_datos[3]) ? $miembro_datos[3] : '';
					$curp_ = trim($data3);
				}

				if (trim($nombre) == '' || trim($pa) == '')
				{
					$resolve(['estatus' => 'error', 'mensaje' => 'Favor de agregar un Nombre y Apellidos.']);
					return $promise;
				}

				if($curp_!='no_es_obligatorio'){
					if($curp_ == ''){
						$promise->resolve(['estatus' => 'error', 'mensaje' => 'La columna CURP es obligatoria.']);
						return $promise;
					}
				}

				$promise2 = $this->validateCurp($curp_);
					
				$promise2->then(
					function($data) use($promise){
						$promise->resolve($data);
					});

				$promise2->wait();
				
			if(isset($personarelacion->id)){
				$promise->resolve([
						'estatus' => 'error',
						'mensaje' => 'Miembro con CURP ' . $curp . ' ya está registrado en la asociación.',
						'persona' => $persona
				]);
			}
			});

			return $promise;
	}

	public function validateCurp($curps)
	{

		$promise = new Promise(
			function() use(&$promise, $curps){

				$curp_arr = [ $curps];
		$http = new Client([
			'headers'  =>	[
				'Authorization' => 'Bearer ' . env('BEARER_API_TOKEN_IMSS'),
				'Content-Type' => 'application/x-www-form-urlencoded'
			]
		]);
		$base_url = 'https://apicurp.centrolaboral.gob.mx/Curp/';
		 $url = "https://imss.centrolaboral.gob.mx/api/empleados?api_token=" . env('API_TOKEN_IMSS');
		$data = [
			'curp' => $curps
		];

		$data_2 = [
			'curps' => $curp_arr
		];

		

		//$response_renapo = $http->request('POST', $base_url, ['form_params' => $data]);
		$response_imss = $http->request('POST', $url, ['form_params' => $data_2]);
		
		$curps_imss = json_decode($response_imss->getBody());
		$curps_imss = $curps_imss[0];
		//$curps_renapo = json_decode($response_renapo->getBody());
		var_dump($curps_imss);
		// Nuevas validaciones

		$promise->resolve('test');

		// $datos_curp = $curps_renapo->datos[0];

		// Busca datos de la persona en la BD que coincidan con el curp
		//$persona = Persona::where('curp', $datos_curp->curp)->first();
		//var_dump($persona);

		// dd($curps_response, $curps_response2 ,$datos_curp, $datos_curp->curp, $persona);
		
		//dd($curps_imss);

			//$persona = Persona::where('curp', $api_response->curp)->first();
			// if($curps_imss->existe!=null){
			// 	$persona->curp_valida = $curps_imss->existe;
			// }else{
			// 	$persona->curp_valida = false;
			// }
			// $persona->curp_valida_update_at = Carbon::now();
			// if (is_null($persona->curp_valida_at)) {
			// 	$persona->curp_valida_at = Carbon::now();
			// }
			// if ($curps_imss->existe) {
			// 	$patrones_id = [];
			// 	foreach ($curps_imss->patrones as $p) {
			// 		$patron = Patron::firstOrCreate(['nombre' => $p]);
			// 		$patrones_id[] = $patron->id;
			// 	}
			// 	$persona->patrones()->sync($patrones_id);
			// }
			// $persona->update();
		
		// $http_renapo = new Client();
		// $url = "https://imss.centrolaboral.gob.mx/api/renapo?api_token=" . env('API_TOKEN_IMSS');
		// $data = [
		// 	'curps' => $curps
		// ];
		// $response = $http_renapo->request('POST', $url, ['form_params' => $data]);
		// $curps_response = json_decode($response->getBody());
		
			//$persona = Persona::where('curp', $api_response->curp)->first();
			//var_dump($curps_renapo);
			// if($curps_renapo->statusoper == 'EXITOSO'){
			// 	$persona->curp_renapo_valida = true;
			// }else{
			// 	$persona->curp_renapo_valida = false;
			// }
			// $persona->curp_renapo_validacion_update_at = Carbon::now();
			// if (is_null($persona->curp_renapo_validacion_at)) {
			// 	$persona->curp_renapo_validacion_at = Carbon::now();
			// }
			// $persona->update();

			// if($datos_curp->curp == ''){
			// 	$promise->resolve(['estatus' => 'error', 'mensaje' => 'La CURP ' . $curps . ' es incorrecta.']);
			// }else{
			// 	if(strpos($datos_curp->statuscurp,'B')!== false){
			// 	$promise->resolve(['estatus'=>'error', 'mensaje' => 'La CURP ' . $curps . ' es una CURP con baja' ]);

			// 	}else{
			// 		$promise->resolve(['estatus' => 'success', 'mensaje' => 'La CURP ' . $curps . ' es correcta']);
			// 	}
				
			// }

			});

			return $promise;

		// Se puede hacer esta parte con promesa para una mejor optimización
		
	}


	public function validarCurpRenapo($curps)
	{

		$http_renapo = new Client();
		$url = "https://imss.centrolaboral.gob.mx/api/renapo?api_token=" . env('API_TOKEN_IMSS');
		$data = [
			'curps' => $curps
		];
		$response = $http_renapo->request('POST', $url, ['form_params' => $data]);
		$curps_response = json_decode($response->getBody());
		foreach ($curps_response as $api_response) {
			var_dump($api_response);
		}
	}

	/**
	 * @param $tramite_id
	 */
	private function setInfoCurp($tramite_id, $asociacion_id): void
	{
		$asociacion = Asociacion::find($asociacion_id);
		$miembros = $asociacion->miembros;
		if (count($miembros) <= 0) {
			$tramite = Tramite::find($tramite_id);
			$miembros = $tramite->busquedaEmpresas($asociacion_id, '')->get();
		}
		$curps = $miembros->pluck('curp')->all();
		if (count($curps) > 0) {
			$this->validateCurp($curps);
		}
	}
}
