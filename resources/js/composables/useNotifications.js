import Swal from 'sweetalert2'

const titleMap = {
  success: '¡Hecho!',
  error: 'Error',
  warning: 'Atención',
  info: 'Información',
}

/**
 * Notificación global (SweetAlert).
 * - success / info: se cierran solas (no bloquean).
 * - error / warning: se QUEDAN hasta que la persona toque "Entendido",
 *   así alcanza a leer qué pasó (antes se cerraban en 2-4 s).
 * Nota: en el flujo de pago se usan alertas dentro de la hoja (InlineAlert).
 */
export function useNotifications() {
  const notify = ({ message, type = 'success', duration = 2300, title = null }) => {
    const persistent = type === 'error' || type === 'warning'

    Swal.fire({
      title: title ?? titleMap[type] ?? 'Aviso',
      text: message,
      icon: type,
      timer: persistent ? undefined : duration,
      timerProgressBar: !persistent,
      showConfirmButton: persistent,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#0a0a0a',
      allowOutsideClick: true,
      position: 'center',
      // Evita que el foco vuelva a un modal que ya se cerró
      returnFocus: false,
    })
  }

  return { notify }
}
