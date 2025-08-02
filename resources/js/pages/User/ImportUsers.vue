<template>
    <AppLayout>
        <Head title="Importar Usuarios" />
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <Heading>Importar Usuarios desde Excel</Heading>
                        <p class="text-gray-600 mt-2">
                            Sube un archivo Excel (.xlsx, .xls) con los datos de los usuarios a importar.
                        </p>
                    </div>

                    <div class="p-6">
                        <form @submit.prevent="submitForm" class="space-y-6">
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                                <div class="space-y-4">
                                    <div class="flex justify-center">
                                        <Icon name="Upload" class="h-12 w-12 text-gray-400" />
                                    </div>
                                    <div>
                                        <Label for="file" class="cursor-pointer">
                                            <span class="text-lg font-medium text-gray-900">
                                                Seleccionar archivo Excel
                                            </span>
                                            <input
                                                type="file"
                                                id="file"
                                                ref="fileInput"
                                                @change="handleFileChange"
                                                accept=".xlsx,.xls"
                                                class="hidden"
                                                required
                                            />
                                        </Label>
                                        <p class="text-gray-500 text-sm mt-1">
                                            Formatos permitidos: .xlsx, .xls
                                        </p>
                                    </div>

                                    <div v-if="selectedFile" class="bg-blue-50 p-4 rounded-md">
                                        <div class="flex items-center space-x-2">
                                            <Icon name="FileText" class="h-5 w-5 text-blue-600" />
                                            <span class="text-blue-900 font-medium">{{ selectedFile.name }}</span>
                                            <span class="text-blue-600 text-sm">({{ formatFileSize(selectedFile.size) }})</span>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="removeFile"
                                            class="mt-2 text-red-600 hover:text-red-800"
                                        >
                                            Quitar archivo
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Información del formato esperado -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-2">Formato esperado del archivo:</h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p><strong>Columna C (2):</strong> Código de abonado</p>
                                    <p><strong>Columna D (3):</strong> Número de cédula</p>
                                    <p><strong>Columna E (4):</strong> Nombre completo</p>
                                    <p><strong>Columna F (5):</strong> Dirección</p>
                                    <p><strong>Columna G (6):</strong> Nombre del plan</p>
                                    <p><strong>Columna H (7):</strong> Velocidad en Mbps</p>
                                    <p><strong>Columna K (10):</strong> Estado (Activo/Inactivo)</p>
                                    <p><strong>Columna N (13):</strong> Nombre de la zona</p>
                                    <p><strong>Columna O (14):</strong> Email</p>
                                    <p><strong>Columna P (15):</strong> Teléfono celular</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    * La primera fila debe contener los encabezados y será omitida
                                </p>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    @click="$inertia.visit(route('users.index'))"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    variant="default"
                                    :disabled="form.processing || !selectedFile"
                                >
                                    <Icon v-if="form.processing" name="Loader2" class="h-4 w-4 mr-2 animate-spin" />
                                    {{ form.processing ? 'Importando...' : 'Importar Usuarios' }}
                                </Button>
                            </div>
                        </form>

                        <!-- Resultados de importación -->
                        <div v-if="importResult" class="mt-6 p-4 rounded-lg" :class="importResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                            <div class="flex items-start space-x-2">
                                <Icon
                                    :name="importResult.success ? 'CheckCircle' : 'XCircle'"
                                    :class="importResult.success ? 'text-green-600' : 'text-red-600'"
                                    class="h-5 w-5 mt-0.5"
                                />
                                <div>
                                    <h4 :class="importResult.success ? 'text-green-900' : 'text-red-900'" class="font-medium">
                                        {{ importResult.success ? 'Importación exitosa' : 'Error en la importación' }}
                                    </h4>
                                    <p :class="importResult.success ? 'text-green-700' : 'text-red-700'" class="text-sm mt-1">
                                        {{ importResult.message }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import Heading from '@/components/Heading.vue'
import Icon from '@/components/Icon.vue'
import { Head } from '@inertiajs/vue3'

const page = usePage()
const fileInput = ref()
const selectedFile = ref(null)

const form = useForm({
    file: null,
})

const importResult = ref(null)

// Observar cambios en el flash message
const flashMessage = computed(() => page.props.flash)

watch(flashMessage, (newFlash) => {
    if (newFlash?.success) {
        importResult.value = {
            success: true,
            message: newFlash.success
        }
    }
    if (newFlash?.error) {
        importResult.value = {
            success: false,
            message: newFlash.error
        }
    }
}, { immediate: true })

const handleFileChange = (event) => {
    const target = event.target
    const file = target.files?.[0]

    if (file) {
        selectedFile.value = file
        form.file = file
        importResult.value = null
    }
}

const removeFile = () => {
    selectedFile.value = null
    form.file = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const submitForm = () => {
    if (!selectedFile.value) {
        return
    }

    form.post(route('import-clients'), {
        onSuccess: () => {
            removeFile()
        },
        onError: (errors) => {
            importResult.value = {
                success: false,
                message: Object.values(errors)[0] || 'Error al importar los usuarios'
            }
        }
    })
}
</script>
