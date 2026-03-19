<template>
  <span />
</template>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { watch } from 'vue'
import Swal from 'sweetalert2'

const page = usePage()

const titleMap: Record<string, string> = {
  success: '¡Hecho!',
  error: 'Error',
  warning: 'Atención',
  info: 'Información',
}

watch(
  () => page.props.flash as any,
  (flash: any) => {
    if (flash?.type && flash?.message) {
      Swal.fire({
        title: titleMap[flash.type] ?? 'Aviso',
        text: flash.message,
        icon: flash.type,
        timer: 2400,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: true,
        position: 'center',
      })
    }
  },
  { immediate: true, deep: true }
)
</script>
