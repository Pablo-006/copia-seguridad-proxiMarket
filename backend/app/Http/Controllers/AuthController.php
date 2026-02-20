<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // ... (Login y Register se quedan igual) ...

    public function login(Request $request) {
        // Se validan el email y la contraseña. Si algo falla, Laravel
        // responde automáticamente
        $request->validate([
            // email requerido y con un formato de email
            'email' => 'required|email',
            // contraseña requerida
            'password' => 'required',
        ]);
        // Se obtiene el usuario a partir del email
        $user = User::where('email', $request->email)->first();
        // En caso de no encontrar ningún usuario o en caso de que se haya
        // encontrado pero la contraseña no coincida, devolver un error
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'error' => ['Las credenciales son incorrectas.'],
            ]);
        }
        // Si no hay problemas, se crea un token de acceso para que el usuario
        // pueda navegar por la web. Se le otorga el token en texto plano al usuario
        // y en la base de datos se almacena encriptado para mayor seguridad 
        // (en caso de que alguien accediera a la base de datos, no podría hacer
        // nada con los token)
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('seller');

        // Se devuelve una respuesta en formato JSON
        return response()->json([
            // Mensaje informativo de que todo ha ido bien
            'message' => 'Login exitoso',
            // El token de acceso para el usuario
            'access_token' => $token,
            // El tipo de token, identificado al portador
            'token_type' => 'Bearer',
            // El usuario que se ha logeado para poder cargar sus datos
            'user' => $user,
        ], 200);
    }

    public function register(Request $request) {
        // Se validan los datos entrantes
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            // se valida que el email sea único en la tabla 'users'
            'email' => 'required|string|email|max:255|unique:users',
            // la propiedad 'confirmed' indica que el campo password ha de coincidir
            // con un campo llamado 'password_confirmation', para asegurarse de que la contraseña
            // que se quiere escribir es correcta
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Creación del usuario: se guarda el usuario en la base de datos y
        // se guardan los datos en una variable para poder devolverlos a Vue
        // y poder mostrar la información del usuario
        $user = User::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'email' => $validated['email'],
            // Con Hash::make(), se crea el usuario con la contraseña que 
            // ha introducido, encriptada
            'password' => Hash::make($validated['password']),
        ]);

        // Al igual que en el login, se crea el token y se devuelve una respuesta
        // para poder gestionar la información del usuario y el token
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201); // El código 201 indica que se ha creado una instancia correctamente
    }

    public function logout(Request $request) {
        // Se accede al usuario que ha iniciado sesión, y se borra
        // el registro del token que estaba utilizando para navegar por la web
        // en la base de datos para que deje de ser válido
        // (también se borra desde la web en la vista del perfil de Vue)
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}