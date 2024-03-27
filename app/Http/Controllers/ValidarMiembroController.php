<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ValidarMiembroController extends Controller
{
    //
    public function validarMiembro(Request $request){

     

        $miembros = $request->input('miembros');
       

        return response()->json(['message' => 'Validación correcta', 'data' => $miembros]);

    }

}
