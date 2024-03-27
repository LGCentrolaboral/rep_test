<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ValidarMiembroController extends Controller
{
    //
    public function verMiembro(Request $request){

        $miembro = $request->input('miembros');


        return response()->json(['message' => 'Validacion correcta', 'data' => $request]);

    }

}
