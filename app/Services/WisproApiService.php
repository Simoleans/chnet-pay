<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para consumir la API de Wispro
 *
 * Este servicio maneja la comunicación con la API de Wispro incluyendo:
 * - Autenticación automática con API key
 * - Paginación con parámetros ?page=x&per_page=y
 * - Manejo de errores y logging
 * - Métodos genéricos para CRUD
 *
 * Ejemplos de paginación:
 * - getClients(1, 20) → ?page=1&per_page=20
 * - getClients(3, 50) → ?page=3&per_page=50
 */
class WisproApiService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = 'https://www.cloud.wispro.co/api/v1';
        $this->apiKey = '6ac95e82-de47-4e52-8121-b264d909d8fa';
    }

    /**
     * Configurar headers básicos para las peticiones
     */
    private function getHeaders()
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => $this->apiKey,
        ];
    }

    /**
     * Construir parámetros de paginación validados
     *
     * @param int $page Número de página (mínimo 1)
     * @param int $perPage Registros por página (mínimo 1, máximo 100)
     * @return array Parámetros validados
     */
    private function buildPaginationParams($page = 1, $perPage = 20)
    {
        return [
            'page' => max(1, (int)$page),
            'per_page' => max(1, min(100, (int)$perPage))
        ];
    }

    /**
     * Obtener lista de clientes con paginación
     *
     * @param int $page Número de página (por defecto: 1)
     * @param int $perPage Registros por página (por defecto: 20)
     *
     * Ejemplo de uso:
     * - Página 1 con 20 registros: ?page=1&per_page=20
     * - Página 3 con 50 registros: ?page=3&per_page=50
     */
    public function getClients($page = 1, $perPage = 20)
    {
        try {
            // Construir parámetros de paginación validados
            $paginationParams = $this->buildPaginationParams($page, $perPage);

            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/clients', $paginationParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getClients: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un cliente específico por ID
     */
    public function getClient($clientId)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/clients/' . $clientId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getClient: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener lista de planes con paginación
     *
     * @param int $page Número de página (por defecto: 1)
     * @param int $perPage Registros por página (por defecto: 20)
     *
     * Retorna la estructura completa de la API incluyendo meta y data
     */
    public function getPlans($page = 1, $perPage = 20)
    {
        try {
            // Construir parámetros de paginación validados
            $paginationParams = $this->buildPaginationParams($page, $perPage);

            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/plans', $paginationParams);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getPlans: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener clientes con filtros adicionales y paginación
     *
     * @param array $filters Filtros adicionales (custom_id_eq, search, zone, etc.)
     * @param int $page Número de página
     * @param int $perPage Registros por página
     *
     * Ejemplos de uso:
     * - Buscar por abonado: getClientsWithFilters(['custom_id_eq' => '12345'])
     * - Buscar por zona: getClientsWithFilters(['zone' => 'Centro'])
     * - Múltiples filtros: getClientsWithFilters(['custom_id_eq' => '12345', 'zone' => 'Centro'])
     */
    public function getClientsWithFilters($filters = [], $page = 1, $perPage = 20)
    {
        try {
            // Construir parámetros de paginación validados
            $paginationParams = $this->buildPaginationParams($page, $perPage);

            // Combinar filtros con paginación
            $params = array_merge($filters, $paginationParams);


            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/clients', $params);


            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getClientsWithFilters: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Método genérico para hacer peticiones GET con soporte de paginación automática
     *
     * @param string $endpoint Endpoint a consultar
     * @param array $params Parámetros adicionales
     *
     * Nota: Los parámetros de paginación se deben incluir en $params:
     * - page: número de página
     * - per_page: registros por página
     */
    public function get($endpoint, $params = [])
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . $endpoint, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::get: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Método genérico para hacer peticiones POST
     */
    public function post($endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::post: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar cliente en Wispro
     *
     * @param string $clientId ID del cliente en Wispro
     * @param array $data Datos a actualizar (name, email, street, phone, etc.)
     *
     * Endpoint: PUT /clients/{client_id}?name=valor&email=valor&street=valor
     */
    public function updateClient($clientId, $data = [])
    {
        try {
            // Construir URL con query parameters
            $endpoint = '/clients/' . $clientId;

            $response = Http::withHeaders($this->getHeaders())
                ->put($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::updateClient: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Método genérico para hacer peticiones PUT
     */
    public function put($endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->put($this->baseUrl . $endpoint, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::put: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Método genérico para hacer peticiones DELETE
     */
    public function delete($endpoint)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->delete($this->baseUrl . $endpoint);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status(),
                'message' => $response->json()['message'] ?? 'Error desconocido'
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::delete: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener contratos de un cliente
     *
     * @param string $clientId ID del cliente en Wispro
     * @return array
     */
    public function getClientContracts($clientId)
    {
        try {
            $endpoint = '/contracts';
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . $endpoint, [
                    'client_id_eq' => $clientId
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getClientContracts: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un plan específico por su ID
     *
     * @param string $planId ID del plan en Wispro
     * @return array
     */
    public function getPlanById($planId)
    {
        try {
            $endpoint = '/plans/' . $planId;
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . $endpoint);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getPlanById: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener contratos filtrados por estado
     *
     * @param string $state Estado del contrato (enabled, disabled, degraded, alerted)
     * @param int $page Número de página
     * @param int $perPage Registros por página
     * @return array
     */
    public function getContractsByState($state, $page = 1, $perPage = 20)
    {
        try {
            $params = $this->buildPaginationParams($page, $perPage);
            $params['state_eq'] = $state;

            $endpoint = '/contracts';
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->baseUrl . $endpoint, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => 'Error en la respuesta de la API: ' . $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Error en WisproApiService::getContractsByState: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conexión con la API',
                'message' => $e->getMessage()
            ];
        }
    }
}
