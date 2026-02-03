# 📌 API Rutas del Proyecto

## 🔹 Usuarios / Auth

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| GET    | `/user` | ✅ | Obtener info del usuario logueado |
| PUT    | `/user` | ✅ | Actualizar info del usuario |
| POST   | `/user` | ✅ | Crear usuario (opcional) |

---

## 🔹 Locales

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| GET    | `/locales` | ✅ | Listar locales del usuario |
| POST   | `/locales` | ✅ | Crear nuevo local |
| PUT    | `/locales/{localId}` | ✅ | Actualizar local |
| DELETE | `/locales/{localId}` | ✅ | Eliminar local |

---

## 🔹 Categorías

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| GET    | `/locales/{localId}/categorias` | ❌ | Listar categorías para clientes |
| POST   | `/locales/{localId}/categorias` | ✅ | Crear categoría |
| PUT    | `/categorias/{categoriaId}` | ✅ | Actualizar categoría |
| DELETE | `/categorias/{categoriaId}` | ✅ | Eliminar categoría |

---

## 🔹 Productos

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| GET    | `/categorias/{categoriaId}/productos` | ❌ | Listar productos de una categoría |
| GET    | `/productos/{productoId}` | ❌ | Mostrar producto |
| POST   | `/productos` | ✅ | Crear producto |
| PUT    | `/productos/{productoId}` | ✅ | Actualizar producto |
| DELETE | `/productos/{productoId}` | ✅ | Eliminar producto |

---

## 🔹 Pedidos

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| POST   | `/pedidos` | ❌ | Crear pedido (cliente) |
| GET    | `/pedidos/{pedidoId}` | ❌ | Mostrar estado del pedido al cliente |
| GET    | `/pedidos` | ✅ | Listar pedidos del local (dueño) |
| PUT    | `/pedidos/{pedidoId}/estado` | ✅ | Cambiar estado (pendiente, aprobado, pagado, cancelado) |

---

## 🔹 Facturación / Transacciones

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| GET    | `/transacciones` | ✅ | Listar transacciones/pagos |
| POST   | `/transacciones` | ✅ | Crear transacción manual |
| PUT    | `/transacciones/{id}` | ✅ | Actualizar estado de transacción |

---

## 🔹 MercadoPago

| Método | Ruta | Auth | Propósito |
|--------|-----|------|-----------|
| POST   | `/mercadopago/access-token` | ✅ | Vincular local con MP (OAuth) |
| POST   | `/mercadopago/preference` | ✅ | Crear preferencia de pago |
| POST   | `/mercadopago/save-preapproval` | ✅ | Guardar preapproval (suscripción) |
| POST   | `/mercadopago/iniciar-suscripcion` | ✅ | Iniciar suscripción de plan |
| POST   | `/mercadopago/cambiar-plan` | ✅ | Cambiar plan de suscripción |
| POST   | `/mercadopago/webhook` | ❌ | Webhook MP para confirmar pago |

---

## 🔹 Notas

- Todo lo público (❌) es **cliente final**, no requiere auth.  
- Todo lo privado (✅) es para **local/usuario dueño**, requiere token Bearer/Sanctum.  
- `webhook` siempre es público, MercadoPago lo llama automáticamente.  
- `pedidos/{pedidoId}` público solo para **ver estado**, no para modificar.  

---

## 🔹 Diagrama de Flujo API (Markdown)

```text
CLIENTE
   |
   |-- GET /locales/{id}/categorias  ---> Listar categorías
   |-- GET /categorias/{id}/productos ---> Listar productos
   |-- GET /productos/{id}            ---> Ver producto
   |-- POST /pedidos                   ---> Crear pedido
   |-- GET /pedidos/{id}               ---> Ver estado pedido
   |-- POST /mercadopago/webhook       ---> MP confirma pago
   |
   v

USUARIO / LOCAL (Auth required)
   |
   |-- GET /user                       ---> Info usuario
   |-- PUT /user                        ---> Actualizar info
   |
   |-- GET /locales                     ---> Listar locales
   |-- POST /locales                    ---> Crear local
   |-- PUT /locales/{id}                ---> Actualizar local
   |-- DELETE /locales/{id}             ---> Eliminar local
   |
   |-- POST /locales/{id}/categorias    ---> Crear categoría
   |-- PUT /categorias/{id}             ---> Actualizar categoría
   |-- DELETE /categorias/{id}          ---> Eliminar categoría
   |
   |-- POST /productos                   ---> Crear producto
   |-- PUT /productos/{id}               ---> Actualizar producto
   |-- DELETE /productos/{id}            ---> Eliminar producto
   |
   |-- GET /pedidos                      ---> Listar pedidos
   |-- PUT /pedidos/{id}/estado          ---> Cambiar estado
   |
   |-- GET /transacciones                ---> Listar transacciones
   |-- POST /transacciones               ---> Crear transacción
   |-- PUT /transacciones/{id}           ---> Actualizar transacción
   |
   |-- POST /mercadopago/access-token    ---> Vincular MP
   |-- POST /mercadopago/preference      ---> Crear preferencia
   |-- POST /mercadopago/save-preapproval ---> Guardar preapproval
   |-- POST /mercadopago/iniciar-suscripcion ---> Iniciar suscripción
   |-- POST /mercadopago/cambiar-plan   ---> Cambiar plan
