<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\User;
use App\Models\SellerProfile;
use App\Models\Product;

class SellerOrderController extends Controller
{
    public function index(Request $request){
        $sellerId = Auth::id();

        // Esta variable se crea para almacenar en ella la consulta (query) que se va a realizar
        // a la base de datos. Según la info que obtengamos del frontend, se modificará de una
        // manera o de otra para luego finalmente hacer el get()
        $query = Order::with('buyer', 'lines.product')->where('seller_id', $sellerId);

        if($request->has('statuses')){
            // La función explode lo que hace es generar un array cortando por el separador
            // designado en la función (en este caso, la coma). La función query lo que hace
            // es obtener el 'query' de la url que nos devuelve el frontend.
            // Un ejemplo sería algo como /seller/orders?statuses=pending,weight_adjusted
            // Aquí, la función query nos devolvería 'pending,weight_adjusted' y con explode
            // lo convertimos en ['pending', 'weight_adjusted']
            $statusArray = explode(',', $request->query('statuses'));
            $query->whereIn('status', $statusArray);
        }else{
            $query->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
        }

        $orders = $query->get();

        $formattedOrders = $this->formatOrders($orders);

        return response()->json($formattedOrders, 200);
    }

    // Función para buscar un pedido concreto y obtener todos los detalles importantes de este.
    // En el frontend se traduce como hacer clic en un pedido y ver los detalles del pedido
    public function show($orderId){
        $sellerId = Auth::id();

        $order = Order::where('id', $orderId)->with('buyer', 'lines.product')->firstOrFail();
        $collection = collect([$order]);

        $formattedOrder = $this->formatOrders($collection)->first();

        return response()->json($formattedOrder, 200);
    }

    //FUNCIÓN PARA CAMBIAR DE 'NEW' A 'PENDING'
    public function markAsPending($orderId){
        $sellerId = Auth::id();

        $order = Order::where('id', $orderId)->where('status', 'new')->with('seller', 'lines.product')->firstOrFail();

        $order->status = 'pending';

        $order->save();

        return response()->json([
            'message' => 'Pedido aceptado correctamente',
            'order_id' => $order->id
        ], 200);
    }

    // FUNCIÓN PARA PODER EDITAR UN PEDIDO PENDIENTE O QUE YA ESTÁ EDITADO. 
    // PERMITE EDITAR EL PESO REAL O LA CANTIDAD PARA CALCULAR EL PRECIO REAL DEL PEDIDO
    public function update(Request $request, $orderId){
        
        // Se valida que los datos del frontend cumplan con unas ciertas características. 
        // En caso de error, la función 'validate()' devuelve un error 422 en el frontend automáticamente.
        $validated = $request->validate([
            // Comprobar que desde el frontend se está devolviendo un objeto 'lines' y que sea un array
            'lines' => 'required|array',

            // Aquí se comprueba que en todos los objetos del array (*), el id sea numérico entero 
            // y que sea un id real en la base de datos (exists:order_lines,id)
            'lines.*.id' => 'required|integer|exists:order_lines,id',

            // Se comprueba que el peso real tenga un valor numérico y que sea mayor a 0 (gt:0).
            // Con decimal:0,2 nos aseguramos de que tenga máximo dos decimales.
            // Validamos también el máximo para evitar errores de desbordamiento en la base de datos.
            'lines.*.real_weight' => 'required|numeric|decimal:0,2|gt:0|max:999999.99',

            // Este dato corresponde con un campo oculto del frontend que se habilita si el vendedor
            // necesita poner manualmente el precio por unidad (en caso de inconsistencias con datos como el peso estimado o el precio estimado).
            // Nos aseguramos de que sea un dato mayor que 0.
            'lines.*.unit_price' => 'nullable|numeric|decimal:0,2|gt:0|max:999999.99',

            // Se recoge la cantidad para productos que se venden por unidades.
            // Debe ser entero y mayor que 0.
            'lines.*.quantity' => 'nullable|numeric|integer|gt:0|max:999999'
        ]);

        $sellerId = Auth::id();

        // Se pretende actualizar aquel pedido que esté pendiente o que ya haya sido editado y se quiera volver a editar.
        $order = Order::where('id', $orderId)
                        ->where('seller_id', $sellerId)
                        ->whereIn('status', ['pending', 'weight_adjusted'])
                        ->with('lines.product')
                        ->firstOrFail();

        // Con Transaction, nos aseguramos de que en caso de haber un fallo (luz, servidor), los datos no queden incompletos.
        // O se ejecuta toda la función o no se hace nada.
        DB::Transaction(function () use ($order, $validated){
            $totalPrice = 0;
            
            // Se utiliza la función collect() para convertir el array simple del request en una Colección de Laravel.
            // Esto nos permite usar funciones avanzadas como 'firstWhere' que los arrays nativos de PHP no tienen.
            $orderLines = collect($validated['lines']);

            // Se recorren las líneas del pedido guardadas en la base de datos ($order->lines).
            // Esto es crucial por seguridad: si un usuario malintencionado intenta enviar IDs de otros pedidos
            // en el request, el bucle los ignorará porque solo iteramos sobre lo que realmente pertenece a este pedido.
            foreach($order->lines as $line){
                
                // La variable data es un array asociativo, que permite acceder a los campos de dentro directamente mediante el nombre de estos campos
                // sin necesidad de acceder a ellos mediante posición
                $data = $orderLines->firstWhere('id', $line->id);

                // Solo se procesan aquellas líneas del pedido que hayan sido editadas
                // ¿Cómo sabe el servidor diferenciar de las líneas que han sido modificadas de las que no?:
                    // 
                if($data){
                    // Cuando un cliente realiza un pedido y el vendedor lo acepta, el pedido pasa a pendiente. Pero,
                    // estos pedidos no tienen un peso real aún, en qué momento se les debería asignar el peso real? O
                    // pasa algo si los pedidos pendientes no tienen un peso real?
                        // El peso real se asigna en esta misma función. Al principio, un pedido 'pending'
                        // no tiene un peso real, y más abajo es donde se le asigna este valor a la base de datos
                    $realWeight = $data['real_weight'];

                // --- BLOQUE 1 CORREGIDO: PRIORIDAD AL PRECIO MANUAL ---
                // ¿Para qué sirve poder editar el precio por unidad? Si haces un pedido, el precio establecido al hacer la reserva
                // no se ve afectado por futuros cambios en los precios de los productos
                    // Sirve para darle una opción al vendedor de hacer un pequeño descuento en el pedido
                    $unitPrice = 0;

                    // 1. PRIMERO comprobamos si el frontend nos manda un precio unitario nuevo
                    // (Esto permite corregir el precio si estaba mal calculado)
                    // ¿Qué hace isset()?
                        // Comprueba que existe una variable y que además no esté vacía
                    if(isset($data['unit_price']) && $data['unit_price'] > 0) {
                        $unitPrice = $data['unit_price'];
                    }
                    // 2. SI NO hay precio nuevo, usamos el cálculo histórico (lo que tenías antes)
                    elseif ($line->price_at_moment > 0) {
                        if ($line->product->unit === 'kg' && $line->weight_at_moment > 0) {
                            $unitPrice = $line->price_at_moment / $line->weight_at_moment;
                        } 
                        elseif ($line->product->unit !== 'kg' && $line->quantity > 0) {
                            $unitPrice = $line->price_at_moment / $line->quantity;
                        }
                    }
                    // 3. Si todo falla
                    else {
                        abort(422, "No se puede determinar el precio unitario.");
                    }
                    
                    // --- BLOQUE 2: GESTIÓN DE STOCK ---
                    
                    // CASO A: Producto vendido por PESO (KG)
                    if($line->product->unit === 'kg'){
                        
                        $weightDifference = $realWeight - $line->weight_at_moment;

                        // Si la diferencia es positiva, estamos quitando stock. Verificamos que haya suficiente.
                        if($weightDifference > 0 && $weightDifference > $line->product->stock){
                            abort(400, "No se puede establecer el peso real debido a que supera al stock del producto. Faltan " . ($weightDifference - $line->product->stock) . "kg");
                        }

                        // Con decrement, se opera automáticamente: 
                        // - Si $weightDifference es positivo (ej: 0.5), resta stock.
                        // - Si es negativo (ej: -0.5), al restar un negativo, suma stock (devuelve al almacén).

                        // ¿Qué ocurre si al principio hay una cantidad de 5 kilos pero luego el vendedor la cambia a 3kilos? ¿Se recupera el stock o no pasa nada?
                        $line->product->decrement('stock', $weightDifference);
                        
                        // Actualizamos solo el peso en la línea
                        $line->real_weight = $realWeight;
                    
                    // CASO B: Producto vendido por UNIDAD (Quantity)
                    } elseif(isset($data['quantity'])){
                        //Para aquellos productos que funcionan por unidad, la cantidad se guarda en un campo quantity.
                        //Para estos productos, el campo de weight_at_moment serviría como información adicional, pero no para calcular el precio o stock
                        $newQuantity = $data['quantity'];
                        $qtyDifference = $newQuantity - $line->quantity;

                        // Verificamos stock si pide más unidades de las que había
                        if($qtyDifference > 0 && $qtyDifference > $line->product->stock){
                             abort(400, "No hay suficiente stock de " . $line->product->title . ". Solo quedan " . ($line->product->stock) . " unidades.");
                        }

                        // Ajustamos stock y actualizamos la cantidad en la línea
                        // Lo mismo que antes, si el cliente pide 5 patatas pero luego quiere 3, esa diferencia de patatas, se suma al stock?
                        $line->product->decrement('stock', $qtyDifference);
                        $line->quantity = $newQuantity; 
                        
                        // Aunque el peso no sea necesario ya que el precio se calcula por unidad, igualmente es importante guardar el peso real del pedido, por si en caso
                        // de contratar una agencia de repartos externa que cobran por kilo o para el transporte, es importante conocer este dato
                        $line->real_weight = $realWeight; 
                    }

                    // --- BLOQUE 3: CÁLCULO FINAL Y GUARDADO ---

                    // Calculamos el total de la línea según su tipo (Kilos * Precio o Unidades * Precio)
                    if ($line->product->unit === 'kg') {
                        $totalLinePrice = $unitPrice * $realWeight;
                    } else {
                        // Usamos la cantidad (que puede ser la nueva si entró en el elseif de arriba, o la vieja si no)
                        $totalLinePrice = $unitPrice * $line->quantity;
                    }

                    $line->price_at_moment = $totalLinePrice;
                    $line->save(); // Guardamos los cambios en la línea (BD)
                    
                    $totalPrice += $totalLinePrice; // Sumamos al acumulador del pedido

                } else {
                    // Si la línea NO fue editada por el usuario, sumamos su precio antiguo al total
                    // para no perder el valor de los productos no tocados.
                    $totalPrice += $line->price_at_moment;
                }                
            }

            // Actualizamos el precio total del pedido completo una sola vez al final
            $order->total_price = $totalPrice;
            
            // Actualizamos el estado si estaba pendiente
            if($order->status === 'pending'){
                $order->status = 'weight_adjusted';
            }
            
            $order->save();
        });

        return response()->json(['message' => 'Pedido actualizado correctamente', 'total' => $order->total_price]);
    }

    public function markAsReady($orderId){
        $sellerId = Auth::id();

        $order = Order::where('id', $orderId)->where('status', ['pending', 'weight_adjusted'])->firstOrFail();

        // Faltaría la opción de configurar un punto de recogida, aunque no sé si eso se hace aquí o en el controlador de puntos de recogida

        $order->status = 'ready';
        $order->save();

        return response()->json(['message' => 'Pedido marcado como listo para recoger', 'order' => $order]);
    }

    public function markAsCompleted($orderId){
        $sellerId = Auth::id();

        $order = Order::where('id', $orderId)->where('status', 'ready')->firstOrFail();

        // Añadir una validación para confirmar que se ha completado el pedido

        $order->status = 'completed';
        $order->save();

        return response()->json(['message' => 'Pedido completado y entregado', 'order' => $order]);
    }

    // Esta función ahora sirve tanto para el VENDEDOR (Rechazar) como para el COMPRADOR (Cancelar)
    public function cancelOrReject(Request $request, $orderId){

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:5|max:255'
        ]);

        $userId = Auth::id();

        $order = Order::where('id', $orderId)->with('lines.product')->firstOrFail();

        // 3. DETERMINAR EL ROL Y LOS PERMISOS
        $isBuyer = ($order->buyer_id === $userId);
        $isSeller = (Auth::id() === $userId);

        // Si el usuario no es ni el comprador ni el vendedor de este pedido -> FUERA
        if (!$isBuyer && !$isSeller) {
            abort(403, 'No tienes permiso para gestionar este pedido.');
        }

        // 4. REGLAS DE ESTADO SEGÚN QUIÉN SEAS
        if ($isBuyer) {
            // REGLA COMPRADOR: Solo puede cancelar si está en 'new'.
            // Si el vendedor ya lo aceptó ('pending'), el comprador debe contactar por chat/teléfono.
            if ($order->status !== 'new') {
                abort(400, 'No puedes cancelar el pedido porque ya esta siendo preparado. Contacta con el vendedor.');
            }
            $newStatus = 'cancelled'; // Estado específico para saber que fue el cliente
        } 
        elseif ($isSeller) {
            // REGLA VENDEDOR: Puede rechazar en casi cualquier estado (menos si ya se entregó/completó).
            if (in_array($order->status, ['completed', 'rejected', 'cancelled'])) {
                abort(400, 'Este pedido ya esta finalizado o cancelado.');
            }
            $newStatus = 'rejected'; // Estado específico para saber que fue el vendedor
        }

        // 5. TRANSACCIÓN (Devolución de Stock + Cambio de Estado)
        DB::transaction(function () use ($order, $validated, $newStatus) {
            
            // LÓGICA DE DEVOLUCIÓN DE STOCK
            // Como asumimos que el stock se resta SIEMPRE al crear el pedido ('new'),
            // SIEMPRE debemos devolverlo, sea quien sea el que cancele.
            
            foreach ($order->lines as $line) {
                
                if ($line->product->unit === 'kg') {
                    // Si el pedido es 'new', real_weight será 0, así que devolvemos el estimado.
                    // Si el pedido ya se pesó, real_weight tendrá valor y devolvemos eso.
                    $weightToReturn = ($line->real_weight > 0) ? $line->real_weight : $line->weight_at_moment;
                    
                    if($weightToReturn > 0) {
                        $line->product->increment('stock', $weightToReturn);
                    }

                } else {
                    // Producto por unidad
                    $qtyToReturn = $line->quantity;
                    if($qtyToReturn > 0) {
                        $line->product->increment('stock', $qtyToReturn);
                    }
                }
            }

            // Guardamos el nuevo estado ('cancelled' o 'rejected')
            $order->status = $newStatus;
            
            // Guardamos el motivo. Es útil saber por qué el cliente canceló.
            // Asegúrate de tener una columna 'cancellation_reason' o usar la misma 'rejection_reason'
            $order->rejection_reason = $validated['rejection_reason']; 

            $order->save();
        });

        $message = $isBuyer ? 'Has cancelado tu pedido correctamente.' : 'Has rechazado el pedido correctamente.';

        return response()->json(['message' => $message, 'status' => $newStatus]);
    }

    //----------------------------------------------------------------------------------------------------------

    //FUNCION QUE COMPLEMENTA A LAS FUNCIONES DE BÚESQUEDA PARA FORMATAR LA SALIDA Y DEVOLVER LOS DATOS INTERESANTES
    public function formatOrders($orders){
        return $orders->map(function($order){
            return[
                'id' => $order->id,
                'status' => $order->status,
                'buyer_name' => $order->buyer?->name ?? 'Comprador eliminado',
                'total_price' => $order->total_price,
                'rejection_reason' => $order->rejection_reason,
                'lines' => $order->lines->map(function($line) {
                    return [
                        'id' => $line->id,
                        'name' => $line->product?->title ?? 'Producto eliminado',
                        'quantity' => $line->quantity,
                        'unit' => $line->product?->unit ?? 'ud',
                        'estimated_weight' => $line->weight_at_moment,
                        'real_weight' => $line->real_weight,
                        'line_price' => $line->price_at_moment
                    ];
                })
            ];
        });
    }
}
