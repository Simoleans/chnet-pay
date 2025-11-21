# 🔐 FLUJO DETALLADO: Sistema de Pagos CHNET

## 📌 INTRODUCCIÓN

El sistema de pagos tiene **DOS RUTAS PRINCIPALES**:

1. **`store()`** - Registro Manual (Requiere verificación del operador)
2. **`validateAndStorePayment()`** - Validación Automática BNC (Verificación instantánea)

---

## 🎯 MÉTODO 1: `store()` - Registro Manual de Pagos

### ✅ **CARACTERÍSTICAS**
- Usuario registra pago manualmente (con o sin imagen)
- **NO SE APLICA AUTOMÁTICAMENTE A FACTURAS**
- Queda con `verify_payments = false` (Pendiente de verificación)
- Operador debe verificar manualmente más tarde

### 📝 **PASO A PASO DEL FLUJO**

```
┌─────────────────────────────────────────────────────────────┐
│                    INICIO: store()                           │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 1. VALIDAR DATOS DE ENTRADA                                 │
│    - user_id (opcional, usa Auth si no viene)               │
│    - reference (referencia del banco)                        │
│    - amount (en BOLÍVARES)                                   │
│    - nationality (V, E, J)                                   │
│    - id_number (cédula sin guión)                            │
│    - bank (código del banco)                                 │
│    - phone (teléfono)                                        │
│    - payment_date (fecha del pago)                           │
│    - image (captura opcional)                                │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. PROCESAR IMAGEN (si existe)                              │
│    - Guardar en: storage/app/public/payment-receipts/       │
│    - Nombre: timestamp_uniqid.extension                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. OBTENER TASA BCV                                         │
│    - BncHelper::getBcvRatesCached()                          │
│    - Ejemplo: 45.50 Bs/$                                     │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. CONVERTIR MONTO Bs → USD                                 │
│    - amount_usd = amount_bs / bcv_rate                       │
│    - Ejemplo: 1,000 Bs / 45.50 = $21.98 USD                 │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. CREAR REGISTRO DE PAGO                                   │
│    ✅ Campos:                                                │
│       - user_id: ID del usuario                              │
│       - reference: Referencia del banco                      │
│       - amount: Monto en USD                                 │
│       - id_number: V-12345678                                │
│       - bank: 0191                                           │
│       - phone: 0412-1234567                                  │
│       - payment_date: 2025-11-21                             │
│       - image_path: payment-receipts/xxx.jpg                 │
│       - invoice_id: NULL ❌                                  │
│       - verify_payments: FALSE ❌                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. RESPUESTA AL USUARIO                                     │
│    ✅ "Pago registrado exitosamente.                        │
│        Pendiente de verificación por el operador."          │
│                                                              │
│    ⚠️ IMPORTANTE: El pago NO se aplica a facturas todavía   │
└─────────────────────────────────────────────────────────────┘
                              ↓
                          [ FIN ]
```

---

## 🔍 EJEMPLOS DETALLADOS - MÉTODO `store()`

### 📊 **ESCENARIO 1: Usuario con 1 factura pendiente de $10 - Paga $10 exacto**

**ANTES DEL PAGO:**
```
Usuario: Juan Pérez
Facturas Pendientes:
  ├─ Factura #001: $10.00 USD (Noviembre 2025) - Estado: pending
  └─ Total Adeudado: $10.00 USD

Crédito Disponible: $0.00
```

**REGISTRO DEL PAGO:**
```php
// Usuario paga: 455 Bs (tasa BCV: 45.50)
// 455 Bs / 45.50 = $10.00 USD

Payment::create([
    'user_id' => 1,
    'reference' => '12345678',
    'amount' => 10.00,  // USD
    'invoice_id' => NULL,  // ❌ NO asignado a factura
    'verify_payments' => false,  // ❌ NO verificado
]);
```

**DESPUÉS DEL PAGO:**
```
✅ Pago REGISTRADO pero NO APLICADO

Facturas Pendientes:
  ├─ Factura #001: $10.00 USD - Estado: pending (SIN CAMBIOS)
  └─ Total Adeudado: $10.00 USD

Pagos Registrados:
  └─ Pago #123: $10.00 USD - Estado: Sin verificar ⏳

Crédito Disponible: $0.00 (SIN CAMBIOS)

⚠️ El operador debe verificar el pago manualmente
```

---

### 📊 **ESCENARIO 2: Usuario con 2 facturas - Paga $25 (más de lo debido)**

**ANTES DEL PAGO:**
```
Usuario: María González
Facturas Pendientes:
  ├─ Factura #001: $10.00 USD (Octubre 2025) - Estado: pending
  ├─ Factura #002: $10.00 USD (Noviembre 2025) - Estado: pending
  └─ Total Adeudado: $20.00 USD

Crédito Disponible: $0.00
```

**REGISTRO DEL PAGO:**
```php
// Usuario paga: 1,137.50 Bs (tasa BCV: 45.50)
// 1,137.50 Bs / 45.50 = $25.00 USD

Payment::create([
    'user_id' => 2,
    'reference' => '87654321',
    'amount' => 25.00,  // USD
    'invoice_id' => NULL,  // ❌ NO asignado
    'verify_payments' => false,  // ❌ NO verificado
]);
```

**DESPUÉS DEL PAGO:**
```
✅ Pago REGISTRADO pero NO APLICADO

Facturas Pendientes:
  ├─ Factura #001: $10.00 USD - Estado: pending (SIN CAMBIOS)
  ├─ Factura #002: $10.00 USD - Estado: pending (SIN CAMBIOS)
  └─ Total Adeudado: $20.00 USD

Pagos Registrados:
  └─ Pago #124: $25.00 USD - Estado: Sin verificar ⏳

Crédito Disponible: $0.00 (SIN CAMBIOS)

💡 El operador verificará y:
   - Aplicará $10 a Factura #001
   - Aplicará $10 a Factura #002
   - Los $5 restantes irán a crédito
```

---

### 📊 **ESCENARIO 3: Usuario paga menos de lo debido**

**ANTES DEL PAGO:**
```
Usuario: Pedro Martínez
Facturas Pendientes:
  ├─ Factura #001: $15.00 USD (Octubre 2025) - Estado: pending
  └─ Total Adeudado: $15.00 USD

Crédito Disponible: $0.00
```

**REGISTRO DEL PAGO:**
```php
// Usuario paga: 455 Bs (tasa BCV: 45.50)
// 455 Bs / 45.50 = $10.00 USD

Payment::create([
    'user_id' => 3,
    'reference' => '11223344',
    'amount' => 10.00,  // USD (menos que $15)
    'invoice_id' => NULL,
    'verify_payments' => false,
]);
```

**DESPUÉS DEL PAGO:**
```
✅ Pago REGISTRADO pero NO APLICADO

Facturas Pendientes:
  ├─ Factura #001: $15.00 USD - Estado: pending (SIN CAMBIOS)
  └─ Total Adeudado: $15.00 USD

Pagos Registrados:
  └─ Pago #125: $10.00 USD - Estado: Sin verificar ⏳

Crédito Disponible: $0.00

💡 El operador verificará y:
   - Aplicará $10 a Factura #001 (pago parcial)
   - Factura #001 quedará con $5 pendientes
   - Estado cambiará a "partial"
```

---

## 🎯 MÉTODO 2: `toggleVerification()` - Verificación por Operador

### ✅ **CARACTERÍSTICAS**
- Operador cambia `verify_payments` de `false` a `true`
- **AQUÍ SÍ SE APLICA EL PAGO A FACTURAS**
- Llama a `applyPaymentToInvoices()`

### 📝 **PASO A PASO DEL FLUJO**

```
┌─────────────────────────────────────────────────────────────┐
│           INICIO: toggleVerification($payment)               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 1. CAMBIAR ESTADO DE VERIFICACIÓN                           │
│    - verify_payments: false → true                           │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. LLAMAR A applyPaymentToInvoices()                        │
│    → Aquí es donde se aplica el pago a las facturas         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. RESPONDER AL OPERADOR                                    │
│    ✅ "Pago verificado y aplicado a X facturas"             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 MÉTODO 3: `applyPaymentToInvoices()` - Aplicación de Pagos

### 📝 **ALGORITMO DETALLADO**

```
┌─────────────────────────────────────────────────────────────┐
│        INICIO: applyPaymentToInvoices($payment)             │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 1. INICIALIZAR VARIABLES                                    │
│    - remainingPayment = $payment->amount (en USD)            │
│    - appliedInvoices = []                                    │
│    - bcvRate = Tasa BCV actual                               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. OBTENER FACTURAS PENDIENTES                              │
│    - WHERE status != 'paid'                                  │
│    - ORDER BY period ASC (más antiguas primero)             │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. ITERAR POR CADA FACTURA                                  │
│    WHILE (remainingPayment > 0 && hay facturas)             │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3.1 CALCULAR DEUDA DE FACTURA                               │
│     remaining = amount_due - amount_paid                     │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3.2 DETERMINAR MONTO A APLICAR                              │
│     paymentToApply = min(remaining, remainingPayment)        │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3.3 CREAR NUEVO REGISTRO DE PAGO                            │
│     Payment::create([                                        │
│         'reference' => 'XXX (Aplicado a Factura)',          │
│         'user_id' => user_id,                                │
│         'invoice_id' => invoice->id,  // ✅ ASIGNADO         │
│         'amount' => paymentToApply,                          │
│         'verify_payments' => true,  // ✅ VERIFICADO         │
│     ]);                                                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 3.4 ACTUALIZAR FACTURA                                      │
│     - amount_paid += paymentToApply                          │
│     - remainingPayment -= paymentToApply                     │
│                                                              │
│     IF (amount_paid >= amount_due) {                         │
│         status = 'paid' ✅                                   │
│     } ELSE IF (amount_paid > 0) {                            │
│         status = 'partial' ⏳                                │
│     }                                                        │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. SI SOBRA DINERO (remainingPayment > 0)                   │
│    - Convertir a Bolívares: remaining * bcvRate              │
│    - Sumar al crédito del usuario                            │
│    - user->credit_balance += remainingPaymentBs              │
└─────────────────────────────────────────────────────────────┘
                              ↓
                          [ FIN ]
```

---

## 🔥 EJEMPLOS DETALLADOS - `applyPaymentToInvoices()`

### 📊 **EJEMPLO 1: Pago Exacto - $10 para 1 factura de $10**

**ANTES:**
```
Facturas:
  └─ Factura #001: $10.00 debido, $0.00 pagado → Estado: pending

Pago Verificado:
  └─ Pago #123: $10.00 USD (verify_payments = true)

Crédito Usuario: $0.00
```

**PROCESO:**
```php
// Paso 1: remainingPayment = $10.00

// Iteración Factura #001:
$remaining = $10.00 - $0.00 = $10.00
$paymentToApply = min($10.00, $10.00) = $10.00

// Crear nuevo pago asociado a factura
Payment::create([
    'invoice_id' => 1,
    'amount' => 10.00,
    'verify_payments' => true,
]);

// Actualizar factura
$invoice->amount_paid = $0.00 + $10.00 = $10.00
$invoice->status = 'paid' ✅

// Actualizar pago restante
$remainingPayment = $10.00 - $10.00 = $0.00

// No sobra dinero, no se agrega crédito
```

**DESPUÉS:**
```
✅ Pago APLICADO COMPLETAMENTE

Facturas:
  └─ Factura #001: $10.00 debido, $10.00 pagado → Estado: paid ✅

Pagos:
  ├─ Pago #123: $10.00 USD (original, sin invoice_id)
  └─ Pago #126: $10.00 USD (aplicado a Factura #001) ✅

Crédito Usuario: $0.00
```

---

### 📊 **EJEMPLO 2: Pago Mayor - $25 para facturas de $20 total**

**ANTES:**
```
Facturas:
  ├─ Factura #001: $10.00 debido, $0.00 pagado → Estado: pending
  └─ Factura #002: $10.00 debido, $0.00 pagado → Estado: pending

Pago Verificado:
  └─ Pago #124: $25.00 USD

Crédito Usuario: $0.00
Tasa BCV: 45.50 Bs/$
```

**PROCESO:**
```php
// Paso 1: remainingPayment = $25.00

// ===== Iteración Factura #001 =====
$remaining = $10.00 - $0.00 = $10.00
$paymentToApply = min($10.00, $25.00) = $10.00

Payment::create(['invoice_id' => 1, 'amount' => 10.00]);
$invoice->amount_paid = $10.00
$invoice->status = 'paid' ✅
$remainingPayment = $25.00 - $10.00 = $15.00

// ===== Iteración Factura #002 =====
$remaining = $10.00 - $0.00 = $10.00
$paymentToApply = min($10.00, $15.00) = $10.00

Payment::create(['invoice_id' => 2, 'amount' => 10.00]);
$invoice->amount_paid = $10.00
$invoice->status = 'paid' ✅
$remainingPayment = $15.00 - $10.00 = $5.00

// ===== Sobra dinero =====
$remainingPaymentBs = $5.00 * 45.50 = 227.50 Bs
$user->credit_balance = $0.00 + $227.50 = 227.50 Bs
```

**DESPUÉS:**
```
✅ Pago APLICADO + CRÉDITO GENERADO

Facturas:
  ├─ Factura #001: $10.00 debido, $10.00 pagado → Estado: paid ✅
  └─ Factura #002: $10.00 debido, $10.00 pagado → Estado: paid ✅

Pagos:
  ├─ Pago #124: $25.00 USD (original)
  ├─ Pago #127: $10.00 USD (aplicado a Factura #001)
  └─ Pago #128: $10.00 USD (aplicado a Factura #002)

Crédito Usuario: 227.50 Bs ($5.00 USD) 💰
```

---

### 📊 **EJEMPLO 3: Pago Parcial - $7 para factura de $15**

**ANTES:**
```
Facturas:
  └─ Factura #001: $15.00 debido, $0.00 pagado → Estado: pending

Pago Verificado:
  └─ Pago #125: $7.00 USD

Crédito Usuario: $0.00
```

**PROCESO:**
```php
// Paso 1: remainingPayment = $7.00

// Iteración Factura #001:
$remaining = $15.00 - $0.00 = $15.00
$paymentToApply = min($15.00, $7.00) = $7.00

Payment::create(['invoice_id' => 1, 'amount' => 7.00]);
$invoice->amount_paid = $0.00 + $7.00 = $7.00
$invoice->status = 'partial' ⏳  // No está completa
$remainingPayment = $7.00 - $7.00 = $0.00

// No sobra dinero
```

**DESPUÉS:**
```
✅ Pago APLICADO PARCIALMENTE

Facturas:
  └─ Factura #001: $15.00 debido, $7.00 pagado → Estado: partial ⏳
                   (Falta: $8.00)

Pagos:
  ├─ Pago #125: $7.00 USD (original)
  └─ Pago #129: $7.00 USD (aplicado a Factura #001)

Crédito Usuario: $0.00

⚠️ Usuario aún debe $8.00 para completar la factura
```

---

### 📊 **EJEMPLO 4: Múltiples Facturas y Pago Parcial**

**ANTES:**
```
Facturas:
  ├─ Factura #001: $10.00 debido, $0.00 pagado → Estado: pending (Oct 2025)
  ├─ Factura #002: $10.00 debido, $0.00 pagado → Estado: pending (Nov 2025)
  └─ Factura #003: $10.00 debido, $0.00 pagado → Estado: pending (Dic 2025)

Pago Verificado:
  └─ Pago #126: $23.00 USD

Crédito Usuario: $0.00
Tasa BCV: 45.50 Bs/$
```

**PROCESO:**
```php
// Paso 1: remainingPayment = $23.00

// ===== Factura #001 (Octubre) =====
$paymentToApply = min($10.00, $23.00) = $10.00
$invoice->amount_paid = $10.00
$invoice->status = 'paid' ✅
$remainingPayment = $23.00 - $10.00 = $13.00

// ===== Factura #002 (Noviembre) =====
$paymentToApply = min($10.00, $13.00) = $10.00
$invoice->amount_paid = $10.00
$invoice->status = 'paid' ✅
$remainingPayment = $13.00 - $10.00 = $3.00

// ===== Factura #003 (Diciembre) =====
$remaining = $10.00
$paymentToApply = min($10.00, $3.00) = $3.00
$invoice->amount_paid = $3.00
$invoice->status = 'partial' ⏳
$remainingPayment = $3.00 - $3.00 = $0.00

// No sobra dinero
```

**DESPUÉS:**
```
✅ Pago APLICADO A 3 FACTURAS

Facturas:
  ├─ Factura #001: $10.00 debido, $10.00 pagado → paid ✅
  ├─ Factura #002: $10.00 debido, $10.00 pagado → paid ✅
  └─ Factura #003: $10.00 debido, $3.00 pagado → partial ⏳
                   (Falta: $7.00)

Pagos:
  ├─ Pago #126: $23.00 USD (original)
  ├─ Pago #130: $10.00 USD → Factura #001
  ├─ Pago #131: $10.00 USD → Factura #002
  └─ Pago #132: $3.00 USD → Factura #003

Crédito Usuario: $0.00

💡 Se pagaron las 2 facturas más antiguas completamente
   La tercera factura quedó parcialmente pagada
```

---

## 🎯 RESUMEN: Diferencias Clave entre Métodos

| Característica | `store()` | `toggleVerification()` + `applyPaymentToInvoices()` |
|---------------|-----------|-----------------------------------------------------|
| **Verificación** | ❌ `verify_payments = false` | ✅ `verify_payments = true` |
| **Aplicar a Facturas** | ❌ NO aplica | ✅ SÍ aplica automáticamente |
| **invoice_id** | `NULL` | Asignado a cada factura |
| **Crédito** | ❌ No genera | ✅ Genera si sobra dinero |
| **Estado Facturas** | Sin cambios | Se actualiza a `paid` o `partial` |
| **Uso** | Usuario registra pago | Operador verifica y aprueba |

---

## 🔑 PUNTOS CRÍTICOS DEL SISTEMA

### ✅ **LO QUE SÍ HACE `store()`:**
1. ✅ Valida datos del pago
2. ✅ Guarda imagen de comprobante
3. ✅ Convierte Bs → USD con tasa BCV
4. ✅ Crea registro de pago con `verify_payments = false`
5. ✅ Retorna mensaje de éxito

### ❌ **LO QUE NO HACE `store()`:**
1. ❌ NO aplica pago a facturas
2. ❌ NO actualiza `amount_paid` de facturas
3. ❌ NO cambia estado de facturas
4. ❌ NO genera crédito al usuario
5. ❌ NO verifica el pago

### ⚠️ **IMPORTANTE:**
> El pago queda en "limbo" hasta que el operador lo verifique manualmente usando `toggleVerification()`. Solo después de la verificación se aplica a las facturas y se generan créditos si sobra dinero.

---

## 🚀 FLUJO COMPLETO EN PRODUCCIÓN

```
Usuario Paga
     ↓
store() → Registra pago (verify_payments = false)
     ↓
⏳ ESPERA verificación del operador
     ↓
Operador verifica el comprobante
     ↓
toggleVerification() → Cambia verify_payments = true
     ↓
applyPaymentToInvoices() → Aplica pago a facturas
     ↓
✅ Facturas actualizadas
✅ Crédito generado (si sobra)
✅ Usuario recibe servicio
```

---

## 📌 CONCLUSIÓN

El sistema de pagos de CHNET tiene **dos etapas principales**:

1. **Registro** (`store`) - El usuario reporta su pago
2. **Verificación y Aplicación** (`toggleVerification` + `applyPaymentToInvoices`) - El operador valida y aplica

Esto permite:
- ✅ Control de calidad (operador valida pagos)
- ✅ Evitar fraudes
- ✅ Aplicación automática a facturas más antiguas
- ✅ Gestión de créditos cuando se paga de más
- ✅ Pagos parciales cuando se paga de menos

