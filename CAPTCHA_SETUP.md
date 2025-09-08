# Configuración de reCAPTCHA para el Login

Esta implementación agrega Google reCAPTCHA v2 al formulario de login para mejorar la seguridad contra bots y ataques automatizados.

## Configuración Requerida

### 1. Obtener las claves de reCAPTCHA

1. Ve a [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
2. Crea un nuevo sitio con las siguientes configuraciones:
   - **Tipo**: reCAPTCHA v2 → "No soy un robot" Checkbox
   - **Dominios**: Agrega tu dominio (ej: `localhost`, `tudominio.com`)
3. Copia las claves generadas:
   - **Site Key** (Clave del sitio)
   - **Secret Key** (Clave secreta)

### 2. Configurar Variables de Entorno

Agrega las siguientes líneas a tu archivo `.env`:

```env
RECAPTCHA_SITE_KEY=tu_site_key_aqui
RECAPTCHA_SECRET_KEY=tu_secret_key_aqui
```

**Importante**: 
- Reemplaza `tu_site_key_aqui` y `tu_secret_key_aqui` con las claves reales de reCAPTCHA
- La **Secret Key** debe mantenerse PRIVADA y nunca exponerse en el frontend
- La **Site Key** es pública y se puede mostrar en el frontend

### 3. Restart de la Aplicación

Después de agregar las variables de entorno:

```bash
php artisan config:cache
php artisan cache:clear
```

## Funcionamiento

### Frontend (Vue.js)
- El captcha se muestra solo si las claves están configuradas
- Se carga dinámicamente el script de Google reCAPTCHA
- Valida que el captcha esté completo antes de enviar el formulario
- Se resetea automáticamente después de cada intento de login

### Backend (Laravel)
- Valida la respuesta del captcha contra la API de Google
- Solo aplica validación si las claves están configuradas
- Incluye verificación de IP para mayor seguridad

## Características de Seguridad

1. **Validación Doble**: Frontend y backend validan el captcha
2. **Configuración Opcional**: Si no se configuran las claves, el login funciona normalmente
3. **Verificación de IP**: Se envía la IP del usuario a Google para validación
4. **Reset Automático**: El captcha se resetea después de cada intento
5. **Manejo de Errores**: Mensajes claros cuando el captcha falla

## Desactivar el Captcha

Para desactivar temporalmente el captcha, simplemente:

1. Elimina o comenta las variables `RECAPTCHA_SITE_KEY` y `RECAPTCHA_SECRET_KEY` del archivo `.env`
2. Ejecuta `php artisan config:cache`

El sistema detectará automáticamente que el captcha no está configurado y funcionará sin él.

## Solución de Problemas

### El captcha no aparece
- Verifica que `RECAPTCHA_SITE_KEY` esté configurada correctamente
- Revisa la consola del navegador para errores de JavaScript
- Confirma que el dominio esté agregado en la configuración de reCAPTCHA

### Error "Por favor, complete el captcha correctamente"
- Verifica que `RECAPTCHA_SECRET_KEY` esté configurada correctamente
- Confirma que las claves correspondan al mismo sitio en Google reCAPTCHA
- Revisa los logs de Laravel para más detalles del error

### El captcha funciona pero el login falla
- El captcha solo valida que no seas un bot, no las credenciales de usuario
- Verifica que el usuario y contraseña sean correctos
- Revisa los logs de autenticación de Laravel
