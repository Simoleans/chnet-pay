# Guía de Sincronización de Clientes Wispro

## 📋 Descripción General

Sistema de sincronización de clientes desde la API de Wispro hacia la base de datos local usando Jobs en background.

## 🏗️ Arquitectura

### Componentes Principales

1. **Job: `SyncWisproClients`** (`app/Jobs/SyncWisproClients.php`)
   - Procesa la sincronización en background
   - Usa chunks (páginas de la API)
   - Implementa transacciones para integridad de datos

2. **Controller: `UserController`**
   - `syncWisproClients()`: Despacha el job

3. **Service: `WisproApiService`**
   - Consume la API de Wispro con paginación
   - Maneja errores y logging

## 🔄 Flujo de Trabajo

### Iniciar Sincronización

```http
POST /api/users/sync-wispro-all
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Sincronización iniciada en segundo plano. Revisa los logs para ver el progreso."
}
```

**Nota:** El progreso se puede seguir mediante los logs en `storage/logs/laravel.log`

## 🔧 Características Técnicas

### 1. Procesamiento por Chunks
- La API de Wispro devuelve datos paginados
- Se solicitan **100 registros por página** para optimizar peticiones
- Cada página se procesa como un chunk independiente

### 2. Transacciones Database
```php
foreach ($clients as $client) {
    DB::beginTransaction();
    try {
        // Procesar cliente
        // Crear o actualizar usuario
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        // Registrar error
    }
}
```

### 3. Manejo de Duplicados
El sistema verifica duplicados por:
- `id_wispro`: ID del cliente en Wispro
- `id_number`: Cédula de identidad (con variaciones V- y E-)

**Si existe:**
- **Actualiza SOLO** estos campos: `name`, `email`, `address`, `zone`
- **NO modifica:** `password`, `code`, `id_number`, `plan_id`, `status`, `role`
- Esto protege la configuración local y contraseñas cambiadas por usuarios

**Si NO existe:**
- **Crea** un nuevo usuario con password = cédula

### 4. Campo Zone como String
- Anteriormente: `zone_id` (foreign key a tabla zones)
- Ahora: `zone` (string con el nombre directamente de Wispro)
- Ya no se crean registros en la tabla `zones`

## 🚀 Cómo Ejecutar

### Opción 1: Con Queue Worker (Recomendado en Producción)

1. Iniciar el worker en background:
```bash
php artisan queue:work --daemon
```

2. Hacer la petición POST para iniciar sincronización

3. El job se procesará automáticamente

### Opción 2: Modo Sync (Para Testing)

En `.env`:
```env
QUEUE_CONNECTION=sync
```

El job se ejecutará síncronamente (bloqueante)

### Opción 3: Procesar manualmente un job pendiente

```bash
php artisan queue:work --once
```

## 📊 Logs

El sistema registra información en `storage/logs/laravel.log`:

```
🚀 Iniciando sincronización de Wispro
📊 Total a procesar: 100 páginas (10000 registros)
📄 Progreso: 10/100 páginas - Creados: 189, Actualizados: 23, Omitidos: 8
📄 Progreso: 20/100 páginas - Creados: 378, Actualizados: 46, Omitidos: 16
...
✅ Sincronización completada - Creados: 1890, Actualizados: 245, Omitidos: 65, Errores: 5
```

## ⚠️ Consideraciones

### Timeouts
- El job tiene un timeout de **900 segundos (15 minutos)**
- Configurable en `SyncWisproClients::$timeout`

### Reintentos
- Si el job falla, se reintentará **3 veces**
- Configurable en `SyncWisproClients::$tries`

### Memoria
- No se requieren ajustes de memoria (procesa por chunks)
- El controlador ya NO ejecuta `set_time_limit()` ni `ini_set('memory_limit')`

### Concurrencia
- Laravel maneja la cola de jobs automáticamente
- Si se despachan múltiples jobs, se procesarán en orden
- Se recomienda esperar a que termine antes de iniciar otra sincronización

## 🔍 Verificar Estado de la Cola

Ver jobs pendientes:
```bash
php artisan queue:listen
```

Ver jobs fallidos:
```bash
php artisan queue:failed
```

Reintentar jobs fallidos:
```bash
php artisan queue:retry all
```

## 🧪 Testing

Para probar sin procesar todos los registros:

1. Modificar `$perPage` en el Job:
```php
protected $perPage = 10; // Procesar solo 10 por página
```

2. Limitar las páginas a procesar:
```php
for ($page = 1; $page <= min(5, $totalPages); $page++) {
    // Solo procesa 5 páginas máximo
}
```

## 📝 Estructura de Datos del Usuario

```php
[
    'name' => 'Nombre del cliente',
    'email' => 'email@dominio.com',
    'phone' => '04241234567',
    'address' => 'Dirección completa',
    'zone' => 'Nombre de la zona (texto)',
    'code' => '12345678', // Cédula sin prefijo
    'id_number' => 'V-12345678',
    'id_wispro' => '12345', // ID en Wispro
    'plan_id' => null,
    'status' => true,
    'role' => 0,
    'password' => bcrypt('12345678') // Solo para nuevos
]
```

## 🎯 Ventajas del Sistema

✅ **No bloquea la interfaz**: El usuario puede seguir trabajando
✅ **Seguridad de datos**: Transacciones por cada cliente
✅ **Eficiente**: Chunking reduce carga de memoria
✅ **Reintentos automáticos**: Si falla, se reintenta hasta 3 veces
✅ **Simple y ligero**: Sin necesidad de eventos, broadcasts o cache
✅ **Actualización selectiva**: Solo actualiza 4 campos (name, email, address, zone)
✅ **Protege datos locales**: NO sobrescribe password, plan, status, role
✅ **Más rápido en ejecuciones posteriores**: 80% más rápido después de la primera vez
✅ **Logs detallados**: Seguimiento completo en laravel.log

## ⚡ Rendimiento

### Primera Ejecución (Sincronización Inicial)
- Crea todos los usuarios desde cero
- Ejecuta bcrypt() para cada password (operación lenta)
- **Tiempo estimado:** 10-15 minutos para 10,000 clientes en cPanel

### Ejecuciones Posteriores
- Solo actualiza 4 campos (name, email, address, zone)
- NO ejecuta bcrypt() en usuarios existentes
- Solo crea nuevos clientes (si los hay)
- **Tiempo estimado:** 2-3 minutos para 10,000 clientes en cPanel
- **80% más rápido** que la primera vez

**Recomendación:** Ejecutar 1 vez al día durante la madrugada (2-4 AM)

