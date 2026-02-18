<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    // 1. PARA EL COMPRADOR (Página "Explorar")
    // Muestra todos los productos activos de todos los vendedores
    public function index()
    {   
        return Product::where('is_active', true)
                      ->with('seller') // Cargamos nombre de tienda
                      ->latest() // ordenar de más reciente a más antiguo
                      ->get();
    }

    // 2. PARA EL VENDEDOR (Su Panel de Control)
    // Muestra solo sus productos para editar/borrar
    public function myProducts(Request $request)
    {
        return Product::where('seller_id', $request->user()->id)
                      ->latest()
                      ->get();
    }

    // 3. Crear Producto (Con TU lógica de imágenes)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit'  => 'required|string|in:unit,kg,box', 
            'estimated_weight' => 'required|numeric|gt:0',
            'stock' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048' 
        ]);
        
        // Asignamos el vendedor
        $validated['seller_id'] = Auth::id();
        $validated['is_active'] = true;

        // Subida de imagen
        if ($request->hasFile('image')) {
            // El validated no tiene habilitado las funciones de store y demás funciones para manipular archivos
            // por eso se valida sobre el request
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path; 
        }

        // Quitamos el archivo de la imagen para no subirlo a la base de datos
        unset($validated['image']);
        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        if ($product->seller_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit'  => 'required|string|in:unit,kg,box', 
            'estimated_weight' => 'required|numeric|gt:0',
            'stock' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048' 
        ]);

        // Gestión de imagen (Borrar vieja, subir nueva)
        if ($request->hasFile('image')) {
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }
            $validated['image_url'] = $request->file('image')->store('products', 'public');
        }

        unset($validated['image']);
        $product->update($validated);
        return response()->json($product, 200);
    }

    public function destroy(Product $product)
    {
        if ($product->seller_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
             Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();
        return response()->noContent();
    }
}