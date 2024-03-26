<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class MicrosoftLoginController extends Controller
{
    //
    public function redirectToMicrosoft(){
        return Socialite::driver('azure')->redirect();
    }

    public function handleMicrosoftCallback(){
        //$user = Socialite::driver('azure')->user();


        // Aquí puedes autenticar o registrar al usuario en tu aplicación
        // $user->getId(), $user->getName(), $user->getEmail(), ...

        // Luego, redirige al usuario a la página deseada
        return view('auth.microsoft-callback');
    }


}
