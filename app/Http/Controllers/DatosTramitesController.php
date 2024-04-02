<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;





class DatosTramitesController extends Controller
{
    //

    public function getDataTramites(Request $request){

        //Conexión a BD
        DB::setDefaultConnection("registro");
        $tipo_tramites = DB::table('tipo_tramites')->get();

        return $tipo_tramites;
    }

}
