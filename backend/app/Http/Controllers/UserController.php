<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\SellerProfile;

class UserController extends Controller
{   
    // Les llamo de esta manera predefinida a las funciones para que la estructura usada en api.php sea capaz de llamarlas de manera sencilla

    // Get
    public function index() {

        return User::all();
    }

    // Función para obtener la información del usuario que ha iniciado sesión
    public function user(Request $request){
        // Se obtiene la información del usuario junto con su perfil de vendedor
        // (en caso de no tener, los campos estarían en null, y en el frontend
        // se hace la validación para mostrar o no la información)
        // La función load es similar a la función with. Sirve para traer
        // campos de otra tabla con la que está relacionada, pero la función
        // with sirve antes de tener el dato (user) y hacer la consulta SQL
        // y la función load sirve cuando ya se tiene el dato (user) y se quiere
        // traer más info de otras tablas
        return response()->json($request->user()->load('seller'), 200);
    }

    // Post
    public function store(Request $request) {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'in:seller,buyer,admin',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'buyer',
        ]);

        return response()->json($user, 201);
    }

    // Get (por ID)
    public function show(string $id) {

        $user = User::findOrFail($id);

        // ⬇️ MODIFICACIÓN: Cargamos las reseñas recibidas
        // Usamos la relación 'receivedReviews' que añadimos al modelo User
        if (method_exists($user, 'receivedReviews')) {
            $reviews = $user->receivedReviews()
                            // Traemos solo los datos básicos del autor de la reseña
                            ->with('author:id,name,surname,avatar_url') 
                            ->orderBy('created_at', 'desc')
                            ->get();

            // Las adjuntamos al objeto usuario para que el frontend las reciba
            $user->reviews = $reviews;
        }

        return response()->json($user, 200);
    }

    // Put
    public function update(Request $request, string $id) {

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'surname' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:seller,buyer,admin',
        ]);

        $user->update($validated);
        
        return response()->json($user, 200);
    }

    // Delete
    public function destroy(string $id) {
        
        $user = User::findOrFail($id);
        $user->delete();

        return response()->noContent(); // devuelve automáticamente código 204
    }

    public function becomeSeller(Request $request) {
        $user = $request->user();

        if ($user->role === 'seller' || $user->seller()->exists()) {
            return response()->json(['message' => 'Ya eres vendedor o tienes una tienda creada'], 400);
        }

        $validated = $request->validate([
            'store_name' => 'required|string|max:255',
            'nif'        => 'required|string|min:9|max:9', 
            'description'=> 'nullable|string'
        ]);

        SellerProfile::create([
            'seller_id'   => $user->id, 
            'store_name'  => $validated['store_name'],
            'nif'         => $validated['nif'],
            'description' => $validated['description'] ?? null,
            'avatar_url'  => null 
        ]);

        $user->role = 'seller';
        $user->save();

        return response()->json([
            'message' => '¡Tienda creada con éxito!',
            'user'    => $user->load('seller') 
        ]);
    }

    public function updateProfile(Request $request) {
        // Se obtiene la instancia del usuario que ha iniciado sesión
        $user = $request->user();

        // Se validan los datos que introduce
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            // Se valida que el email ha de ser único en la tabla users, que ocupa
            // el campo email (unique:users,email,). Además, a la hora de editar tu perfil,
            // si no modificas el email, te saltaría un error, porque Laravel se pondría a buscar
            // tu email en la base de datos, y como existe, causaría problemas. Para ello,
            // se añade el trozo '. $user->id,' que sirve para excluir de la búsqueda el id
            // del usuario que ha iniciado sesión. De esta manera, se soluciona este problema
            // y además tampoco podrías ponerte el email de otro usuario
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            // Se valida la foto de perfil. Se valida que sea una imagen, que
            // coincida con las extensiones que hay abajo y que pese máximo 2MB
            'avatar_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        // Se valida si el usuario manda un archivo
        // en el campo de la foto de perfil al actualizar su perfil
        if ($request->hasFile('avatar_url')) {
            // Si es que sí, se valida si el usuario tenía previamente una foto de perfil
            if ($user->avatar_url) {
                // Si es así, se borra la antigua foto de la carpeta 'public' 
                // (donde se almacenan las fotos de perfil. La ruta de la carpeta 
                // se encuentra en storage/app/public/avatars)
                Storage::disk('public')->delete($user->avatar_url);
            }
            
            // Se guarda en una variable, la ruta donde se va a guardar la foto.
            // Primero, se obtiene el archivo del campo de Vue donde se aloja la foto
            // (avatar_url), luego, se almacena en la carpeta 'avatars' dentro
            // de la carpeta 'public' (ruta mencionada anteriormente). Además,
            // también modifica el nombre de los archivos para que en caso de que
            // dos usuarios guarden una foto con el mismo nombre, no se sobreescriban
            $path = $validated->file('avatar_url')->store('avatars', 'public');
            
            // Se registra la ruta de la foto de perfil en el registro del usuario
            // de la base de datos
            $user->avatar_url = $path; 
        }

        // Se saca de la variable $validated la imagen del avatar, ya que no queremos guardar la imagen en la base de datos
        // queremos la url de la imagen donde está guardada en el servidor
        unset($validated['avatar_url']);
        
        // Se modifican los campos de la base de datos con los nuevos valores introducidos
        // $user->name = $validated['name'];
        // $user->surname = $validated['surname'];
        // $user->email = $validated['email'];

        // Hace lo mismo que arriba pero en una línea. Rellena los campos del usuario con los datos
        // de la variable $validated
        $user->fill($validated); 

        
        // Se guardan los cambios
        $user->save();

        // Se devuelve un mensaje de respuesta
        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user->load('seller')
        ]);
    }    
}