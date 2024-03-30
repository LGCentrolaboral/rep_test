<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

class ValidarMiembroController extends Controller
{
    //
    public function validarMiembro(Request $request){

        $miembros = $request->input('miembros');

        $curp_array = [ $miembros['curp'] ];

        $data_2 = [
			'curps' => $curp_array
		];

         $http = new Client();

         $url = "https://imss.centrolaboral.gob.mx/api/empleados?api_token=" . env('API_TOKEN_IMSS');

         try {
            //code...
            $response_imss = $http->request('POST',$url,['form_params'=>$data_2]);
            $response = $response_imss->getBody()->getContents();
         } catch (GuzzleException $e) {
            //throw $th;
            $response = 'Error al realizar la solicitud con Guzzle: ' . $e->getMessage();
         }



        //    // Devolver el cuerpo de la respuesta como JSON
        return response()->json(['message' => 'Validación correcta', 'CURP' => $miembros['curp'], 'data' => json_encode($data_2) , 'response' => json_encode($response) ]);

        //return response()->json(['message' => 'Validación correcta', 'CURP' => $miembros['curp'], 'Respuesta' => $response_imss]);

    }

}
