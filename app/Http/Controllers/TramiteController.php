<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TramiteController extends Controller
{
    //

    public function simularTramite(Request $request){

        $id_tipo_tramite = $request->input('id_tipo_tramite');
        $cat_status_id = 1;
        $visible = true;

        //Inserción de nuevo tramite
        try {
            //code...
            DB::setDefaultConnection("registro");
            DB::table('tramites')->insert([
                'tipo_tramite_id'   =>  $id_tipo_tramite,
                'cat_estatus_id'    =>  '1',
                'folio'             =>  'TEST de creacion de tramite' 
            ]);

            $ultimoRegistro = DB::table('tramites')->orderBy('id','desc')->first();


        } catch (\Throwable $th) {
            //throw $th;
            $error = $th;
            return $error;
        }
        return $ultimoRegistro;
    }

}
