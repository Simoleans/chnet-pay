# 📋 Análisis del Método `sendC2P` - PaymentController

## 🔍 Resumen Ejecutivo

El método `sendC2P` procesa pagos mediante el sistema C2P (Comercio a Persona) del Banco Nacional de Crédito (BNC). Este método valida datos, envía el pago al banco, registra el pago en la base de datos y actualiza el crédito del usuario en USD. La facturación se maneja en otro sistema (Wispro), por lo que no se aplican pagos a facturas locales.

---

## ✅ Correcciones Aplicadas

### 1. **Eliminado `dd($request->all())` (Línea 643)**
   - **Problema**: Debug statement que detiene la ejecución
   - **Solución**: Removido completamente

### 2. **Variable `$terminal` no definida (Línea 688)**
   - **Problema**: Variable usada sin definir, causaría error fatal
   - **Solución**: Obtenida desde `config('app.bnc.terminal')` con validación

### 3. **Validación de `invoice_id` y `client_id` (Líneas 659-660)**
   - **Problema**: Marcados como `required` pero son opcionales en el frontend
   - **Solución**: Cambiados a `nullable|string`

---

## 🔄 Flujo Completo del Método

```
┌─────────────────────────────────────────────────────────────┐
│ 1. VALIDACIÓN DE USUARIO AUTENTICADO                        │
└─────────────────────────────────────────────────────────────┘
                    │
                    ▼
         ┌──────────────────────┐
         │ ¿Usuario autenticado?│
         └──────────┬───────────┘
                    │
         ┌──────────┴──────────┐
         │                      │
        ❌ NO                  ✅ SÍ
         │                      │
         ▼                      ▼
┌──────────────────┐   ┌──────────────────────────────┐
│ Error 401        │   │ 2. VALIDACIÓN DE DATOS       │
│ "Usuario no      │   │    - debtor_bank_code       │
│  autenticado"    │   │    - token                   │
└──────────────────┘   │    - amount                  │
                       │    - debtor_id               │
                       │    - debtor_phone            │
                       │    - invoice_id (opcional)   │
                       │    - client_id (opcional)    │
                       └──────────┬───────────────────┘
                                  │
                                  ▼
                       ┌──────────────────────┐
                       │ ¿Datos válidos?      │
                       └──────────┬───────────┘
                                  │
                       ┌──────────┴──────────┐
                       │                      │
                      ❌ NO                  ✅ SÍ
                       │                      │
                       ▼                      ▼
              ┌──────────────────┐   ┌──────────────────────────────┐
              │ Error 422        │   │ 3. NORMALIZACIÓN Y VALIDACIÓN│
              │ Validación       │   │    - Teléfono: 58XXXXXXXXXX  │
              │ fallida          │   │    - Cédula: V00000000       │
              └──────────────────┘   └──────────┬───────────────────┘
                                                 │
                                                 ▼
                                      ┌──────────────────────┐
                                      │ ¿Formato válido?     │
                                      └──────────┬───────────┘
                                                 │
                                      ┌──────────┴──────────┐
                                      │                      │
                                     ❌ NO                  ✅ SÍ
                                      │                      │
                                      ▼                      ▼
                             ┌──────────────────┐   ┌──────────────────────────────┐
                             │ Error 422         │   │ 4. OBTENER TERMINAL          │
                             │ "Formato inválido"│   │    - Desde config            │
                             └──────────────────┘   └──────────┬───────────────────┘
                                                                │
                                                                ▼
                                                     ┌──────────────────────┐
                                                     │ ¿Terminal configurado?│
                                                     └──────────┬───────────┘
                                                                │
                                                     ┌──────────┴──────────┐
                                                     │                      │
                                                    ❌ NO                  ✅ SÍ
                                                     │                      │
                                                     ▼                      ▼
                                            ┌──────────────────┐   ┌──────────────────────────────┐
                                            │ Error 500         │   │ 5. ENVIAR C2P AL BANCO       │
                                            │ "Terminal no      │   │    - BncHelper::sendC2PPayment│
                                            │  configurado"     │   └──────────┬───────────────────┘
                                            └──────────────────┘              │
                                                                               ▼
                                                                    ┌──────────────────────┐
                                                                    │ ¿Respuesta exitosa?  │
                                                                    └──────────┬───────────┘
                                                                               │
                                                                    ┌──────────┴──────────┐
                                                                    │                      │
                                                                   ❌ NO                  ✅ SÍ
                                                                    │                      │
                                                                    ▼                      ▼
                                                           ┌──────────────────┐   ┌──────────────────────────────┐
                                                           │ Error 409         │   │ 6. VALIDAR STATUS DEL BANCO    │
                                                           │ "No se pudo       │   │    - status === 'OK'           │
                                                           │  procesar C2P"    │   └──────────┬───────────────────┘
                                                           └──────────────────┘              │
                                                                                             ▼
                                                                                  ┌──────────────────────┐
                                                                                  │ ¿Status === 'OK'?     │
                                                                                  └──────────┬───────────┘
                                                                                             │
                                                                                  ┌──────────┴──────────┐
                                                                                  │                      │
                                                                                 ❌ NO                  ✅ SÍ
                                                                                  │                      │
                                                                                  ▼                      ▼
                                                                         ┌──────────────────┐   ┌──────────────────────────────┐
                                                                         │ Error 409         │   │ 7. OBTENER TASA BCV           │
                                                                         │ "Pago no aprobado"│   │    - BncHelper::getBcvRatesCached│
                                                                         └──────────────────┘   └──────────┬───────────────────┘
                                                                                                            │
                                                                                                            ▼
                                                                                                 ┌──────────────────────┐
                                                                                                 │ ¿Tasa BCV disponible?│
                                                                                                 └──────────┬───────────┘
                                                                                                            │
                                                                                                 ┌──────────┴──────────┐
                                                                                                 │                      │
                                                                                                ❌ NO                  ✅ SÍ
                                                                                                 │                      │
                                                                                                 ▼                      ▼
                                                                                        ┌──────────────────┐   ┌──────────────────────────────┐
                                                                                        │ Error 500         │   │ 8. CONVERTIR MONTO A USD     │
                                                                                        │ "No se pudo       │   │    - amount / bcvRate        │
                                                                                        │  obtener BCV"     │   └──────────┬───────────────────┘
                                                                                        └──────────────────┘              │
                                                                                                                           ▼
                                                                                                                ┌──────────────────────────────┐
                                                                                                                │ 9. CREAR REGISTRO DE PAGO     │
                                                                                                                │    - Payment::create()        │
                                                                                                                │    - verify_payments = true   │
                                                                                                                └──────────┬───────────────────┘
                                                                                                                           │
                                                                                                                           ▼
                                                                                                                ┌──────────────────────────────┐
                                                                                                                │ 10. CREAR REGISTRO DE PAGO    │
                                                                                                                │     - Payment::create()       │
                                                                                                                │     - verify_payments = true  │
                                                                                                                └──────────┬───────────────────┘
                                                                                                                           │
                                                                                                                           ▼
                                                                                                                ┌──────────────────────────────┐
                                                                                                                │ 11. ACTUALIZAR CRÉDITO        │
                                                                                                                │     - credit_balance += amount│
                                                                                                                │     - Guardado en USD         │
                                                                                                                └──────────┬───────────────────┘
                                                                                                                           │
                                                                                                                           ▼
                                                                                                                ┌──────────────────────────────┐
                                                                                                                │ 12. REGISTRAR EN WISPRO      │
                                                                                                                │     - Si invoice_id existe    │
                                                                                                                │     - Si client_id existe     │
                                                                                                                └──────────┬───────────────────┘
                                                                                                                           │
                                                                                                                           ▼
                                                                                                                ┌──────────────────────────────┐
                                                                                                                │ 13. RESPUESTA EXITOSA         │
                                                                                                                │     - JSON con datos del pago │
                                                                                                                └──────────────────────────────┘
```

---

## 📝 Validaciones Detalladas

### **Validación 1: Usuario Autenticado**
```php
if (!$user) {
    return response()->json([
        'success' => false,
        'error' => 'Usuario no autenticado'
    ], 401);
}
```
- **Condición**: Usuario debe estar autenticado
- **Error**: 401 Unauthorized
- **Ejemplo de fallo**: Usuario no logueado

---

### **Validación 2: Datos de Entrada**
```php
$validated = $request->validate([
    'debtor_bank_code' => 'required|numeric',
    'token' => 'required|string|max:255',
    'amount' => 'required|numeric|min:0.01',
    'debtor_id' => ['required','string','max:20','regex:/^[VEve]-?[0-9]+$/'],
    'debtor_phone' => ['required','string','max:20'],
    'invoice_id' => 'nullable|string',
    'client_id' => 'nullable|string',
]);
```

**Campos Requeridos:**
- `debtor_bank_code`: Código numérico del banco (ej: 191, 0102)
- `token`: Token de validación del banco (máx 255 caracteres)
- `amount`: Monto en bolívares (mínimo 0.01)
- `debtor_id`: Cédula en formato V/E seguido de números (ej: V12345678, E-87654321)
- `debtor_phone`: Teléfono (máx 20 caracteres)

**Campos Opcionales:**
- `invoice_id`: ID de factura en Wispro
- `client_id`: ID de cliente en Wispro

**Ejemplo de fallo**: 
```json
{
  "debtor_bank_code": "abc",  // ❌ Debe ser numérico
  "amount": 0,                // ❌ Debe ser >= 0.01
  "debtor_id": "12345678"     // ❌ Debe empezar con V o E
}
```

---

### **Validación 3: Formato de Teléfono**
```php
$debtorPhoneDigits = preg_replace('/\D/', '', (string) $validated['debtor_phone']);
if (!preg_match('/^58\d{10}$/', $debtorPhoneDigits)) {
    return response()->json([
        'success' => false,
        'error' => 'Formato de teléfono inválido. Use 58XXXXXXXXXX (sin +, espacios ni guiones)'
    ], 422);
}
```

**Formato Esperado**: `58` + `10 dígitos` = `12 dígitos totales`

**Ejemplos:**
- ✅ `584241234567` (Válido)
- ✅ `584123456789` (Válido)
- ❌ `04241234567` (Falta prefijo 58)
- ❌ `58424123456` (Solo 11 dígitos)
- ❌ `+584241234567` (Tiene símbolo +)

---

### **Validación 4: Formato de Cédula**
```php
$normalizedId = strtoupper(preg_replace('/[^VE0-9]/', '', $validated['debtor_id']));
if (!preg_match('/^[VE][0-9]+$/', $normalizedId)) {
    return response()->json([
        'success' => false,
        'error' => 'Formato de cédula inválido. Debe ser V00000000 o E00000000'
    ], 422);
}
```

**Formato Esperado**: `V` o `E` seguido solo de dígitos (sin guiones, puntos, espacios)

**Ejemplos:**
- ✅ `V12345678` (Válido)
- ✅ `E87654321` (Válido)
- ✅ `v-12345678` → Normalizado a `V12345678` (Válido)
- ❌ `12345678` (Falta V o E)
- ❌ `V-123-456-78` (Tiene guiones, no se normaliza correctamente)
- ❌ `J12345678` (Letra incorrecta)

---

### **Validación 5: Terminal Configurado**
```php
$terminal = config('app.bnc.terminal');
if (empty($terminal)) {
    return response()->json([
        'success' => false,
        'error' => 'Terminal BNC no configurado. Contacte al administrador.'
    ], 500);
}
```

**Condición**: Variable `BNC_TERMINAL` debe estar en `.env`
- **Error**: 500 Internal Server Error
- **Ejemplo de fallo**: Variable no configurada en `.env`

---

### **Validación 6: Respuesta del Helper BNC**
```php
if (!$result || (is_array($result) && isset($result['error']) && $result['error'] === true)) {
    // Manejo de error
    return response()->json([
        'success' => false,
        'message' => $friendlyMessage,
    ], 409);
}
```

**Condiciones de Error:**
- `$result` es `null` o `false`
- `$result['error'] === true`

**Mensajes de Error Priorizados:**
1. `$result['decrypted']['message']` (mensaje desencriptado del banco)
2. `$result['message']` (mensaje directo)
3. Mensaje genérico: "No se pudo procesar el pago C2P"

**Ejemplo de respuesta de error:**
```php
[
    'error' => true,
    'status' => 400,
    'message' => 'Token inválido',
    'decrypted' => ['message' => 'El token proporcionado ha expirado']
]
```

---

### **Validación 7: Status del Banco**
```php
if (!is_array($result) || !isset($result['status']) || $result['status'] !== 'OK') {
    return response()->json([
        'success' => false,
        'message' => $result['message'] ?? 'El pago no fue aprobado por el banco',
    ], 409);
}
```

**Condición**: `$result['status']` debe ser exactamente `'OK'`

**Ejemplos de Respuestas:**
- ✅ `['status' => 'OK', 'reference' => 'C2P-123456']` (Aprobado)
- ❌ `['status' => 'ERROR', 'message' => 'Fondos insuficientes']` (Rechazado)
- ❌ `['status' => 'PENDING']` (Pendiente, no aprobado)

---

### **Validación 8: Tasa BCV Disponible**
```php
$bcvData = BncHelper::getBcvRatesCached();
$bcvRate = $bcvData['Rate'] ?? null;

if (!$bcvRate) {
    return response()->json([
        'success' => false,
        'error' => 'No se pudo obtener la tasa BCV. Intente nuevamente.'
    ], 500);
}
```

**Condición**: Debe poder obtener la tasa BCV desde caché o API
- **Error**: 500 Internal Server Error
- **Ejemplo de fallo**: API de BCV no disponible

---

## 🔄 Flujos de Ejecución con Ejemplos

### **Ejemplo 1: Pago C2P Exitoso - Actualización de Crédito**

**Datos de Entrada:**
```json
{
  "debtor_bank_code": "191",
  "token": "ABC123XYZ",
  "amount": 455.00,
  "debtor_id": "V12345678",
  "debtor_phone": "584241234567",
  "invoice_id": "12345",
  "client_id": "67890"
}
```

**Contexto:**
- Usuario: Carlos Pérez
- Tasa BCV: 45.50 Bs/$
- Monto pagado: 455.00 Bs = $10.00 USD
- Crédito inicial: $0.00 USD

**Flujo:**
1. ✅ Usuario autenticado
2. ✅ Validación de datos OK
3. ✅ Teléfono normalizado: `584241234567`
4. ✅ Cédula normalizada: `V12345678`
5. ✅ Terminal obtenido: `TERMINAL123`
6. ✅ C2P enviado al banco
7. ✅ Respuesta: `['status' => 'OK', 'reference' => 'C2P-20250101120000']`
8. ✅ Tasa BCV: 45.50
9. ✅ Monto en USD: 455.00 / 45.50 = $10.00
10. ✅ Pago creado: `Payment` con `verify_payments = true`
11. ✅ **Crédito actualizado**: `credit_balance = $10.00 USD` (0 + 10)
12. ✅ Wispro: Pago registrado exitosamente (si invoice_id/client_id presentes)
13. ✅ Respuesta: `{"success": true, "message": "Pago C2P procesado exitosamente. Crédito disponible: $10.00 USD"}`

---

### **Ejemplo 2: Pago C2P Exitoso - Acumulación de Crédito**

**Datos de Entrada:**
```json
{
  "debtor_bank_code": "0102",
  "token": "XYZ789ABC",
  "amount": 910.00,
  "debtor_id": "E87654321",
  "debtor_phone": "584123456789"
}
```

**Contexto:**
- Usuario: María González
- Tasa BCV: 45.50 Bs/$
- Monto pagado: 910.00 Bs = $20.00 USD
- Crédito inicial: $5.00 USD

**Flujo:**
1-9. (Igual que Ejemplo 1)
10. ✅ Pago creado: $20.00 USD
11. ✅ **Crédito actualizado**: `credit_balance = $25.00 USD` (5 + 20)
12. ✅ Wispro: No se registra (no hay invoice_id/client_id)
13. ✅ Respuesta: `{"success": true, "message": "Pago C2P procesado exitosamente. Crédito disponible: $25.00 USD"}`

---

### **Ejemplo 3: Pago C2P Rechazado por el Banco**

**Datos de Entrada:**
```json
{
  "debtor_bank_code": "191",
  "token": "EXPIRED123",
  "amount": 455.00,
  "debtor_id": "V12345678",
  "debtor_phone": "584241234567"
}
```

**Flujo:**
1-5. (Igual que Ejemplo 1)
6. ❌ C2P enviado al banco
7. ❌ Respuesta: `['status' => 'ERROR', 'message' => 'Token expirado']`
8. ❌ Validación falla: `status !== 'OK'`
9. ❌ Respuesta: `{"success": false, "message": "Token expirado"}` (409)

**Resultado:**
- No se crea ningún pago
- No se actualiza ninguna factura
- No se actualiza crédito
- Usuario recibe mensaje de error

---

### **Ejemplo 4: Error en Validación de Teléfono**

**Datos de Entrada:**
```json
{
  "debtor_bank_code": "191",
  "token": "ABC123",
  "amount": 455.00,
  "debtor_id": "V12345678",
  "debtor_phone": "04241234567"  // ❌ Falta prefijo 58
}
```

**Flujo:**
1. ✅ Usuario autenticado
2. ✅ Validación de datos OK
3. ❌ Teléfono normalizado: `04241234567` (11 dígitos, no empieza con 58)
4. ❌ Validación falla: `!preg_match('/^58\d{10}$/', '04241234567')`
5. ❌ Respuesta: `{"success": false, "error": "Formato de teléfono inválido. Use 58XXXXXXXXXX (sin +, espacios ni guiones)"}` (422)

---

### **Ejemplo 5: Error - Terminal No Configurado**

**Datos de Entrada:**
```json
{
  "debtor_bank_code": "191",
  "token": "ABC123",
  "amount": 455.00,
  "debtor_id": "V12345678",
  "debtor_phone": "584241234567"
}
```

**Contexto:**
- Variable `BNC_TERMINAL` no está en `.env`

**Flujo:**
1-4. (Igual que Ejemplo 1)
5. ❌ Terminal obtenido: `null` o `''`
6. ❌ Validación falla: `empty($terminal)`
7. ❌ Respuesta: `{"success": false, "error": "Terminal BNC no configurado. Contacte al administrador."}` (500)

---

## 🎯 Puntos Clave del Flujo

### **1. Registro de Pago**
- Se crea un único registro de `Payment` con el monto completo en USD
- El pago se marca como verificado (`verify_payments = true`) porque el banco lo valida automáticamente
- No se asocia a ninguna factura local (`invoice_id = null`)

### **2. Actualización de Crédito**
- **TODO el monto se guarda como crédito** en USD
- El crédito se almacena directamente en **dólares** (`credit_balance`)
- Se calcula: `credit_balance = credit_balance_actual + amountInUSD`
- No se aplica a facturas locales (la facturación se maneja en Wispro)

### **3. Sincronización con Wispro**
- Solo se ejecuta si `invoice_id` y `client_id` están presentes
- Se ejecuta en un `try-catch` separado (no afecta el flujo principal si falla)
- Se registra en logs si hay éxito o error
- **Nota**: La facturación real se maneja en Wispro, este sistema solo registra el pago

### **4. Referencia del Pago**
- Por defecto: `C2P-YYYYMMDDHHMMSS-{user_id}`
- Si el banco devuelve referencia, se usa esa: `$result['reference']`

---

## ⚠️ Consideraciones Importantes

1. **Todos los pagos C2P se marcan como verificados** (`verify_payments = true`) porque el banco los valida automáticamente.

2. **El monto viene en bolívares** desde el frontend, pero se almacena en **dólares** en la base de datos.

3. **Se crea un único registro de Payment**:
   - Sin `invoice_id` (la facturación se maneja en Wispro)
   - Con el monto completo convertido a USD

4. **Todo el monto se guarda como crédito** en USD, no se aplica a facturas locales.

5. **El registro en Wispro es opcional** y no bloquea el flujo si falla.

6. **Los errores del banco se propagan** con mensajes amigables cuando están disponibles.

7. **El crédito se almacena en USD**, no en bolívares, para facilitar la gestión y evitar problemas de conversión.

---

## 🔧 Configuración Requerida

### Variables de Entorno (.env)
```env
BNC_TERMINAL=TERMINAL123
BNC_CLIENT_ID=CLIENT123
BNC_ACCOUNT=ACCOUNT123
BNC_BASE_URL=https://api.bnc.com
BNC_MASTER_KEY=MASTER_KEY
BNC_CLIENT_GUID=GUID
```

---

## 📊 Respuestas JSON

### **Éxito:**
```json
{
  "success": true,
  "message": "Pago C2P procesado exitosamente. Crédito disponible: $10.00 USD",
  "data": {
    "payment_id": 123,
    "amount_usd": 10.00,
    "credit_balance": 10.00,
    "verified": true,
    "bank_response": {
      "status": "OK",
      "reference": "C2P-20250101120000"
    }
  }
}
```

### **Error de Validación:**
```json
{
  "success": false,
  "error": "Formato de teléfono inválido. Use 58XXXXXXXXXX (sin +, espacios ni guiones)"
}
```

### **Error del Banco:**
```json
{
  "success": false,
  "message": "Token expirado"
}
```

---

## ✅ Estado Final

- ✅ `dd()` removido
- ✅ Variable `$terminal` definida y validada
- ✅ `invoice_id` y `client_id` marcados como opcionales
- ✅ Flujo completo documentado
- ✅ Validaciones explicadas
- ✅ Ejemplos de casos de uso incluidos
