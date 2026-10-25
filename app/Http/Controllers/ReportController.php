<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function generateReport(Request $request)
    {
        // Evitar timeout de PHP
        set_time_limit(120);

        $request->validate([
            'prompt' => 'required|string',
        ]);

        $userPrompt = $request->input('prompt');
        $apiKey = env('GEMINI_API_KEY', '');

        $dbSchema = "
        Esquema de la base de datos (MySQL):
        - categories (id, name)
        - products (id, category_id, name, price, stock)
        - customers (id, name, email, city)
        - orders (id, customer_id, status ['pending', 'completed', 'cancelled'], total, created_at)
        - order_items (id, order_id, product_id, quantity, unit_price)
        
        Relaciones:
        - products.category_id -> categories.id
        - orders.customer_id -> customers.id
        - order_items.order_id -> orders.id
        - order_items.product_id -> products.id
        ";

        $systemInstructions = "
        Eres un asistente de datos útil, claro y amable.

        Tienes tres modos de respuesta:

        1. CONSULTA DE DATOS (SQL):
           - Si la pregunta se puede responder con el esquema, responde ÚNICAMENTE con una consulta SQL SELECT pura (sin markdown ni explicaciones).

        2. GRÁFICO:
           - Si el usuario pide un gráfico, gráfica, chart, barras, torta, líneas, etc., responde ÚNICAMENTE con una consulta SQL SELECT que obtenga los datos necesarios para el gráfico.

        3. CONVERSACIÓN:
           - Si es un saludo o pregunta general, responde de forma natural en español.

        Reglas estrictas:
        - Nunca generes INSERT, UPDATE, DELETE, DROP, ALTER, etc.
        - Si no puedes ayudar, responde exactamente: NO_VALIDO
        - Cuando generes SQL, devuelve SOLO la consulta, nada más.
        - Usa siempre los nombres exactos de las tablas y columnas del esquema.
        ";

        // Modelo más generoso en el free tier
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . $apiKey;

        // ========== PRIMERA LLAMADA ==========
        $promptTexto = "Instrucciones del sistema:\n{$systemInstructions}\n\nEsquema de la Base de Datos:\n{$dbSchema}\n\nPregunta del usuario:\n{$userPrompt}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptTexto]
                    ]
                ]
            ]
        ];

        $responseRaw = $this->callGemini($endpoint, $payload);

        if (!empty($responseRaw['error'])) {
            return response()->json($responseRaw, 500);
        }

        $generatedText = $responseRaw['text'] ?? null;

        if (empty($generatedText)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gemini no devolvió respuesta.'
            ], 400);
        }

        $generatedText = trim(preg_replace('/^```sql\s*|```$/i', '', trim($generatedText)));

        if ($generatedText === 'NO_VALIDO') {
            return response()->json([
                'status'  => 'rejected',
                'message' => 'No pude entender tu solicitud. Puedes pedirme reportes, gráficos, datos de productos, órdenes, etc.'
            ], 400);
        }

        // ========== CASO SQL (reporte o gráfico) ==========
        if (preg_match('/^\s*SELECT/i', $generatedText)) {

            if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE)\b/i', $generatedText)) {
                return response()->json([
                    'status'  => 'rejected',
                    'message' => 'Solo se permiten consultas de lectura (SELECT).'
                ], 403);
            }

            try {
                $results = DB::select(DB::raw($generatedText));
                $resultsArray = json_decode(json_encode($results), true);

                if (empty($resultsArray)) {
                    return response()->json([
                        'status' => 'success',
                        'type'   => 'chat',
                        'answer' => 'No se encontraron datos para esa consulta.'
                    ]);
                }

                // Detectar si el usuario pidió un gráfico
                $wantsChart = preg_match('/\b(gr[aá]fico|grafica|chart|barras|torta|pastel|l[ií]neas|histograma)\b/i', $userPrompt);

                if ($wantsChart) {
                    // Pedir a Gemini que arme la configuración del gráfico
                    $chartPrompt = "
                    El usuario pidió un gráfico con esta pregunta: \"{$userPrompt}\"

                    Se ejecutó esta consulta:
                    {$generatedText}

                    Resultados:
                    " . json_encode($resultsArray, JSON_UNESCAPED_UNICODE) . "

                    Responde ÚNICAMENTE con un JSON válido (sin markdown) con esta estructura exacta:
                    {
                      \"chartType\": \"bar\",
                      \"title\": \"Título del gráfico\",
                      \"labels\": [\"etiqueta1\", \"etiqueta2\"],
                      \"data\": [10, 20],
                      \"answer\": \"Texto explicativo corto en español\"
                    }

                    Reglas:
                    - chartType puede ser: bar, pie, line, doughnut
                    - labels y data deben tener la misma cantidad de elementos
                    - Elige el chartType más adecuado según la pregunta
                    - answer debe ser una explicación amable y corta
                    ";

                    $chartPayload = [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $chartPrompt]
                                ]
                            ]
                        ]
                    ];

                    $chartResponse = $this->callGemini($endpoint, $chartPayload);

                    if (!empty($chartResponse['error'])) {
                        // Fallback a reporte normal
                        return $this->formatAsReport($endpoint, $userPrompt, $generatedText, $resultsArray);
                    }

                    $chartJson = $this->extractJson($chartResponse['text'] ?? '');

                    if ($chartJson && isset($chartJson['chartType'], $chartJson['labels'], $chartJson['data'])) {
                        return response()->json([
                            'status'    => 'success',
                            'type'      => 'chart',
                            'question'  => $userPrompt,
                            'chartType' => $chartJson['chartType'],
                            'title'     => $chartJson['title'] ?? 'Gráfico',
                            'labels'    => $chartJson['labels'],
                            'data'      => $chartJson['data'],
                            'answer'    => $chartJson['answer'] ?? 'Aquí tienes el gráfico solicitado.'
                        ]);
                    }
                }

                // Si no es gráfico → formatear como reporte normal
                return $this->formatAsReport($endpoint, $userPrompt, $generatedText, $resultsArray);

            } catch (\Exception $e) {
                return response()->json([
                    'status'       => 'error',
                    'message'      => 'Error al ejecutar el SQL: ' . $e->getMessage(),
                    'sql'          => $generatedText,
                    'error_detail' => $e->getMessage()
                ], 500);
            }
        }

        // ========== CASO CONVERSACIÓN ==========
        return response()->json([
            'status'   => 'success',
            'type'     => 'chat',
            'question' => $userPrompt,
            'answer'   => $generatedText
        ]);
    }

    private function formatAsReport(string $endpoint, string $userPrompt, string $sql, array $resultsArray)
    {
        $formatPrompt = "
        El usuario preguntó: \"{$userPrompt}\"

        Consulta ejecutada:
        {$sql}

        Resultados:
        " . json_encode($resultsArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

        Presenta estos datos de forma clara, amable y legible en español.
        Usa formato bonito (listas, viñetas, tablas markdown si es útil).
        No muestres el JSON crudo.
        ";

        $formatPayload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $formatPrompt]
                    ]
                ]
            ]
        ];

        $formatResponse = $this->callGemini($endpoint, $formatPayload);

        $niceAnswer = $formatResponse['text'] ?? 'Aquí tienes los datos.';

        return response()->json([
            'status'   => 'success',
            'type'     => 'report',
            'question' => $userPrompt,
            'answer'   => $niceAnswer
        ]);
    }

    private function extractJson(?string $text): ?array
    {
        if (empty($text)) return null;

        // Intentar extraer JSON aunque venga con markdown
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return null;
    }

    private function callGemini(string $endpoint, array $payload): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        $responseRaw = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($responseRaw === false || !empty($curlError)) {
            return [
                'error'     => true,
                'status'    => 'error',
                'message'   => 'Error cURL: ' . ($curlError ?: 'Respuesta vacía'),
                'http_code' => $httpCode
            ];
        }

        $responseData = json_decode($responseRaw, true);

        if (isset($responseData['error'])) {
            return [
                'error'   => true,
                'status'  => 'error_gemini_api',
                'message' => $responseData['error']['message'] ?? 'Error de Gemini',
                'raw'     => $responseData
            ];
        }

        $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return [
            'error' => false,
            'text'  => $text,
            'raw'   => $responseData
        ];
    }
}