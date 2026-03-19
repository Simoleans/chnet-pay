import Swal from 'sweetalert2'

const titleMap = {
  success: '¡Hecho!',
  error: 'Error',
  warning: 'Atención',
  info: 'Información',
}

export function useNotifications() {
  const notify = ({ message, type = 'success', duration = 2300, title = null }) => {
    Swal.fire({
      title: title ?? titleMap[type] ?? 'Aviso',
      text: message,
      icon: type,
      timer: duration,
      timerProgressBar: true,
      showConfirmButton: false,
      allowOutsideClick: true,
      position: 'center',
    })
  }

  return { notify }
}
