<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use App\Http\Requests\StoreDominioRequest;
use App\Http\Requests\UpdateDominioRequest;
use App\Http\Requests\VerificarDominioRequest;
use App\Http\Resources\DominioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class DominioController extends Controller
{
    public function index(): JsonResponse
    {
        $dominios = Dominio::with('suscripcion.cliente')->orderBy('nombre')->paginate(15);

        return response()->json([
            'data' => DominioResource::collection($dominios->items()),
            'pagination' => [
                'total' => $dominios->total(),
                'per_page' => $dominios->perPage(),
                'current_page' => $dominios->currentPage(),
                'last_page' => $dominios->lastPage(),
            ],
        ]);
    }

    public function show(Dominio $dominio): JsonResponse
    {
        return response()->json([
            'dominio' => new DominioResource($dominio->load('suscripcion.cliente')),
        ]);
    }

    public function store(StoreDominioRequest $request): JsonResponse
    {
        $dominio = Dominio::create($request->validated());

        return response()->json([
            'message' => 'Dominio creado exitosamente',
            'dominio' => new DominioResource($dominio->load('suscripcion.cliente')),
        ], 201);
    }

    public function update(UpdateDominioRequest $request, Dominio $dominio): JsonResponse
    {
        $dominio->update($request->validated());

        return response()->json([
            'message' => 'Dominio actualizado exitosamente',
            'dominio' => new DominioResource($dominio->load('suscripcion.cliente')),
        ]);
    }

    public function destroy(Dominio $dominio): JsonResponse
    {
        // Aqui no hace falta el corte previo de 409 que si llevan Cliente y Plan:
        // ninguna tabla apunta a dominios, y su propia FK hacia suscripciones es
        // CASCADE, no RESTRICT. El borrado nunca lo bloquea la BD.
        $dominio->delete();

        return response()->json([
            'message' => 'Dominio eliminado exitosamente',
        ]);
    }

    /**
     * Consulta a Porkbun si un dominio esta libre.
     *
     * El navegador NO puede llamar a Porkbun directamente: la API pide una
     * clave y un secreto que no pueden salir del servidor, y ademas no envia
     * cabeceras CORS. Por eso el frontend llama aqui y es Laravel quien hace
     * la peticion saliente.
     *
     * La conversacion con Porkbun esta en Dominio::consultarDisponibilidad,
     * compartida con el registro de ventas: si estuviera duplicada, una venta
     * podria aceptar un dominio que este endpoint rechaza.
     */
    public function verificar(VerificarDominioRequest $request): JsonResponse
    {
        $nombre = $request->validated()['nombre'];

        $r = Dominio::consultarDisponibilidad($nombre);

        if (! $r['ok']) {
            return response()->json(
                array_filter(
                    ['message' => $r['message'], 'codigo' => $r['codigo']],
                    fn ($v) => $v !== null
                ),
                $r['http']
            );
        }

        return response()->json([
            'dominio' => $nombre,
            'disponible' => $r['disponible'],
            'motivo' => $r['motivo'],
            'precio' => $r['precio'],
            // Solo tiene sentido ofrecer alternativas si el pedido no sirve
            'alternativas' => $r['disponible'] === false
                ? $this->buscarAlternativas($nombre)
                : [],
            // Para que la interfaz pueda rotular "USD x 10.00" y "+ 20" sin
            // guardar esos numeros por su cuenta (ver config/precios.php)
            'parametros_precio' => [
                'tipo_de_cambio' => (float) config('precios.tipo_de_cambio'),
                'margen' => (float) config('precios.margen_sobre_costo'),
            ],
        ]);
    }

    /**
     * Busca extensiones libres para el mismo nombre cuando el pedido no sirve.
     *
     * Usa el endpoint EN LOTE de Porkbun: una sola peticion para las cinco, en
     * lugar de cinco seguidas. Ademas consume un presupuesto distinto (200
     * dominios cada 60 s) al de las consultas sueltas (10 cada 10 s), asi que
     * ofrecer alternativas no agota el limite del boton Verificar.
     *
     * Si algo falla se devuelve una lista vacia en vez de propagar el error: la
     * respuesta principal (el dominio no sirve) ya es util por si sola.
     */
    private function buscarAlternativas(string $nombre): array
    {
        // Se parte por el PRIMER punto: 'mi-sitio.com' -> 'mi-sitio' + 'com'
        [$base, $tld] = array_pad(explode('.', $nombre, 2), 2, '');

        if ($base === '' || $tld === '') {
            return [];
        }

        $candidatos = [];
        $maximo = (int) config('porkbun.max_alternativas', 5);

        foreach ((array) config('porkbun.tlds_alternativas', []) as $alternativa) {
            $alternativa = strtolower(trim($alternativa));

            // Ni la extension ya consultada, ni ninguna que Porkbun no venda
            if ($alternativa === $tld || $alternativa === '' || Dominio::tldNoSoportado('x.' . $alternativa)) {
                continue;
            }

            $candidatos[] = $base . '.' . $alternativa;

            if (count($candidatos) >= $maximo) {
                break;
            }
        }

        if ($candidatos === []) {
            return [];
        }

        $clave = config('services.porkbun.key');
        $secreto = config('services.porkbun.secret');
        $url = rtrim(config('services.porkbun.url'), '/') . '/domain/checkDomain';

        try {
            $respuesta = Http::timeout((int) config('services.porkbun.timeout'))
                ->acceptJson()
                ->post($url, [
                    'apikey' => $clave,
                    'secretapikey' => $secreto,
                    'domains' => $candidatos,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Porkbun: alternativas sin conexion', ['dominio' => $nombre, 'error' => $e->getMessage()]);

            return [];
        }

        $cuerpo = $respuesta->json() ?? [];

        if ($respuesta->failed() || ($cuerpo['status'] ?? null) === 'ERROR') {
            Log::warning('Porkbun: alternativas con error', [
                'dominio' => $nombre,
                'http' => $respuesta->status(),
                'cuerpo' => $cuerpo,
            ]);

            return [];
        }

        $alternativas = [];

        // Se recorren los candidatos, no la respuesta, para conservar el orden
        // de preferencia de config('porkbun.tlds_alternativas')
        foreach ($candidatos as $candidato) {
            $datos = $cuerpo['domains'][$candidato] ?? null;
            $avail = $datos['avail'] ?? null;

            if (! is_array($datos) || ! ($avail === true || $avail === 'yes')) {
                continue;
            }

            $alternativas[] = [
                'dominio' => $candidato,
                'precio' => Dominio::extraerPrecio($datos),
            ];
        }

        return $alternativas;
    }

    public function reporte(): JsonResponse
    {
        return response()->json(['reporte' => $this->armarReporte()]);
    }

    public function reportePdf(): Response
    {
        $pdf = Pdf::loadView('reportes.dominios', ['r' => $this->armarReporte()])
            ->setPaper('a4', 'landscape');

        return $pdf->download('reporte-dominios-' . now()->format('Y-m-d') . '.pdf');
    }

    private function armarReporte(): array
    {
        // Sin paginar: el reporte cubre todos los dominios, no la pagina actual
        $dominios = Dominio::with('suscripcion.cliente')->orderBy('nombre')->get();

        $hoy = now()->startOfDay();
        $limite = now()->startOfDay()->addDays(30);

        return [
            'generado_en' => now()->format('d/m/Y H:i'),
            'totales' => [
                'dominios' => $dominios->count(),
                'vigentes' => $dominios->filter(fn ($d) => $d->fecha_fin >= $hoy)->count(),
                'por_vencer' => $dominios
                    ->filter(fn ($d) => $d->fecha_fin >= $hoy && $d->fecha_fin <= $limite)
                    ->count(),
                'vencidos' => $dominios->filter(fn ($d) => $d->fecha_fin < $hoy)->count(),
                'proveedores' => $dominios->pluck('proveedor')->unique()->count(),
                // Margen = lo que paga el cliente menos lo que cuesta a la empresa
                'margen_total' => number_format(
                    (float) $dominios->sum(fn ($d) => (float) $d->precio - (float) $d->precio_real),
                    2
                ),
            ],
            'dominios' => $dominios->map(fn ($d) => [
                'nombre' => $d->nombre,
                'proveedor' => $d->proveedor,
                'cliente' => $d->suscripcion?->cliente?->nombre,
                'suscripcion' => $d->suscripcion?->tipo_servicio,
                'precio_real' => $d->precio_real,
                'precio' => $d->precio,
                'margen' => number_format((float) $d->precio - (float) $d->precio_real, 2),
                'fecha_inicio' => $d->fecha_inicio?->format('d/m/Y'),
                'fecha_fin' => $d->fecha_fin?->format('d/m/Y'),
                'vigente' => $d->fecha_fin >= $hoy,
                'dias_restantes' => $d->fecha_fin >= $hoy ? $hoy->diffInDays($d->fecha_fin) : 0,
            ])->values()->all(),
        ];
    }
}
