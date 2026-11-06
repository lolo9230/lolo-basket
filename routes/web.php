<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/comprobar-clave', function () {
    return response()->json([
        'clave_configurada' => filled(config('basket.api_key')),
    ]);
});

Route::get('/probar-conexion', function () {
    try {
        $respuesta = Http::acceptJson()
            ->withHeaders(['Authorization' => config('basket.api_key')])
            ->connectTimeout(10)
            ->timeout(20)
            ->get(config('basket.base_url') . '/teams');

        if (! $respuesta->successful()) {
            return response()->json([
                'conexion_correcta' => false,
                'codigo_api' => $respuesta->status(),
            ]);
        }

        $equipos = $respuesta->json('data');

        if (! is_array($equipos)) {
            return response('Formato inesperado de la API.', 502);
        }

        return response()->json([
            'conexion_correcta' => true,
            'cantidad_equipos' => count($equipos),
            'primer_equipo' => $equipos[0]['full_name'] ?? null,
        ]);
    } catch (ConnectionException $error) {
        return response('No se pudo conectar con la API.', 502);
    }
});

Route::get('/equipos', function () {
    try {
        $respuesta = Http::acceptJson()
            ->withHeaders(['Authorization' => config('basket.api_key')])
            ->connectTimeout(10)
            ->timeout(20)
            ->get(config('basket.base_url') . '/teams');

        if (! $respuesta->successful()) {
            return response(
                'Error al consultar equipos. Código API: '
                . $respuesta->status(),
                502
            );
        }

        $equipos = $respuesta->json('data');

        if (! is_array($equipos)) {
            return response('Formato inesperado de la API.', 502);
        }

        return view('equipos', ['equipos' => $equipos]);
    } catch (ConnectionException $error) {
        return response('No se pudo conectar con la API.', 502);
    }
});

Route::get('/equipos/{id}/partidos', function ($id) {
    $desde = now()->subDays(7)->toDateString();
    $hasta = now()->addDays(30)->toDateString();

    try {
        $respuesta = Http::acceptJson()
            ->withHeaders(['Authorization' => config('basket.api_key')])
            ->connectTimeout(10)
            ->timeout(20)
            ->get(config('basket.base_url') . '/games', [
                'team_ids' => [(int) $id],
                'start_date' => $desde,
                'end_date' => $hasta,
                'per_page' => 100,
            ]);

        if (! $respuesta->successful()) {
            return response(
                'Error al consultar partidos. Código API: '
                . $respuesta->status(),
                502
            );
        }

        $partidos = $respuesta->json('data');

        if (! is_array($partidos)) {
            return response('Formato inesperado de la API.', 502);
        }

        $partidos = collect($partidos)
            ->sortBy('date')
            ->values()
            ->all();

        return view('partidos', compact('partidos', 'desde', 'hasta'));
    } catch (ConnectionException $error) {
        return response('No se pudo conectar con la API.', 502);
    }
})->whereNumber('id');

Route::get('/partidos-dia', function (Request $request) {
    $datos = $request->validate([
        'fecha' => ['nullable', 'date_format:Y-m-d'],
    ]);

    $fecha = $datos['fecha'] ?? now()->toDateString();

    try {
        $respuesta = Http::acceptJson()
            ->withHeaders(['Authorization' => config('basket.api_key')])
            ->connectTimeout(10)
            ->timeout(20)
            ->get(config('basket.base_url') . '/games', [
                'dates' => [$fecha],
                'per_page' => 100,
            ]);

        if (! $respuesta->successful()) {
            return response(
                'Error al consultar partidos. Código API: '
                . $respuesta->status(),
                502
            );
        }

        $partidos = $respuesta->json('data');

        if (! is_array($partidos)) {
            return response('Formato inesperado de la API.', 502);
        }

        return view('partidos-dia', compact('fecha', 'partidos'));
    } catch (ConnectionException $error) {
        return response('No se pudo conectar con la API.', 502);
    }
});

Route::post('/analizar/{id}', function (Request $request, $id) {
    $datos = $request->validate([
        'total' => ['required', 'numeric', 'min:1', 'max:500'],
        'handicap_local' => ['required', 'numeric', 'between:-100,100'],
        'cuota_local' => ['nullable', 'numeric', 'gt:1'],
        'cuota_visitante' => ['nullable', 'numeric', 'gt:1'],
    ]);

    $consultar = function ($ruta, $parametros = []) {
        return Http::acceptJson()
            ->withHeaders(['Authorization' => config('basket.api_key')])
            ->connectTimeout(10)
            ->timeout(20)
            ->get(
                rtrim(config('basket.base_url'), '/') . $ruta,
                $parametros
            );
    };

    try {
        $respuesta = $consultar('/games/' . $id);

        if (! $respuesta->successful()) {
            return response(
                'No se pudo consultar el encuentro. Código API: '
                . $respuesta->status(),
                502
            );
        }

        $partido = $respuesta->json('data');

        if (
            ! is_array($partido)
            || ! isset(
                $partido['home_team']['id'],
                $partido['visitor_team']['id'],
                $partido['date']
            )
        ) {
            return response('El encuentro llegó incompleto.', 502);
        }

        $estado = (string) ($partido['status'] ?? '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}T/', $estado)) {
            return response(
                'Este análisis previo solo está habilitado para '
                . 'partidos que la API identifica como programados.',
                422
            );
        }

        if (\Carbon\Carbon::parse($estado)->isPast()) {
            return response(
                'La hora de inicio ya pasó. Actualiza la lista de partidos.',
                422
            );
        }

        $fechaPartido = \Carbon\Carbon::parse($partido['date']);
        $corte = $fechaPartido->copy()->startOfDay();

        if (now()->lt($corte)) {
            $corte = now();
        }

        $hasta = $corte->copy()->subDay()->toDateString();
        $desde = $corte->copy()->subDays(60)->toDateString();

        $resumenes = [];

        foreach (['home_team', 'visitor_team'] as $lado) {
            $equipo = $partido[$lado];

            $claveHistorial = 'balldontlie_historial_'
                . $equipo['id'] . '_'
                . $desde . '_'
                . $hasta;

            $historialDatos = Cache::remember(
                $claveHistorial,
                now()->addMinutes(10),
                function () use ($consultar, $equipo, $desde, $hasta) {
                    $respuesta = $consultar('/games', [
                        'team_ids' => [(int) $equipo['id']],
                        'start_date' => $desde,
                        'end_date' => $hasta,
                        'per_page' => 100,
                    ]);

                    return $respuesta->successful()
                        ? $respuesta->json()
                        : null;
                }
            );

            if (! is_array($historialDatos)) {
                return response(
                    'No se pudo consultar el historial de '
                    . $equipo['full_name']
                    . '. La API no respondió correctamente.',
                    502
                );
            }

            $registros = $historialDatos['data'] ?? null;

            if (! is_array($registros)) {
                return response('Historial con formato inesperado.', 502);
            }

            if (($historialDatos['meta']['next_cursor'] ?? null) !== null) {
                return response(
                    'El historial tiene más páginas. Se detuvo el análisis '
                    . 'para no usar una muestra incompleta.',
                    422
                );
            }

            $anteriores = collect($registros)
                ->filter(function ($juego) use ($equipo, $id, $hasta) {
                    $esDelEquipo =
                        ($juego['home_team']['id'] ?? null) == $equipo['id']
                        || ($juego['visitor_team']['id'] ?? null)
                            == $equipo['id'];

                    return $esDelEquipo
                        && ($juego['id'] ?? null) != $id
                        && strtolower((string) ($juego['status'] ?? ''))
                            === 'final'
                        && substr($juego['date'] ?? '', 0, 10) <= $hasta
                        && is_numeric($juego['home_team_score'] ?? null)
                        && is_numeric($juego['visitor_team_score'] ?? null);
                })
                ->sortByDesc('date')
                ->take(10)
                ->values();

            if ($anteriores->count() < 5) {
                return response(
                    'Datos insuficientes para ' . $equipo['full_name']
                    . ': se encontraron ' . $anteriores->count()
                    . ' partidos finalizados en el periodo. '
                    . 'Esta versión exige al menos 5 por equipo.',
                    422
                );
            }

            $anotados = [];
            $recibidos = [];

            foreach ($anteriores as $juego) {
                $esLocal = $juego['home_team']['id'] == $equipo['id'];

                $anotados[] = $esLocal
                    ? $juego['home_team_score']
                    : $juego['visitor_team_score'];

                $recibidos[] = $esLocal
                    ? $juego['visitor_team_score']
                    : $juego['home_team_score'];
            }

            $resumenes[$lado] = [
                'nombre' => $equipo['full_name'],
                'cantidad' => count($anotados),
                'anotados' => array_sum($anotados) / count($anotados),
                'recibidos' => array_sum($recibidos) / count($recibidos),
            ];
        }

        $local = $resumenes['home_team'];
        $visitante = $resumenes['visitor_team'];

        $puntosLocal = ($local['anotados'] + $visitante['recibidos']) / 2;
        $puntosVisitante = ($visitante['anotados'] + $local['recibidos']) / 2;

        $totalEstimado = $puntosLocal + $puntosVisitante;
        $margenLocal = $puntosLocal - $puntosVisitante;

        $lineaTotal = (float) $datos['total'];
        $handicapLocal = (float) $datos['handicap_local'];

        $diferenciaTotal = $totalEstimado - $lineaTotal;
        $margenAjustado = $margenLocal + $handicapLocal;

        $ganador = abs($margenLocal) < 0.01
            ? 'Sin diferencia en la estimación'
            : ($margenLocal > 0 ? $local['nombre'] : $visitante['nombre']);

        $lecturaTotal = abs($diferenciaTotal) < 0.01
            ? 'Coincide con la línea'
            : ($diferenciaTotal > 0 ? 'Por encima: Más' : 'Por debajo: Menos');

        $lecturaHandicap = abs($margenAjustado) < 0.01
            ? 'Sin diferencia frente al hándicap'
            : ($margenAjustado > 0
                ? 'La estimación favorece al LOCAL con ese hándicap'
                : 'La estimación favorece al VISITANTE con el hándicap opuesto');

        $numero = fn ($valor) => number_format($valor, 2, '.', '');

        $nombreLocal = e($local['nombre']);
        $nombreVisitante = e($visitante['nombre']);
        $ganadorSeguro = e($ganador);

        $cuotaLocal = isset($datos['cuota_local'])
            ? e($datos['cuota_local']) : 'No ingresada';

        $cuotaVisitante = isset($datos['cuota_visitante'])
            ? e($datos['cuota_visitante']) : 'No ingresada';

        $volver = e(url('/partidos-dia') . '?fecha='
            . substr($partido['date'], 0, 10));

        $html = '<!DOCTYPE html><html lang="es"><head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Análisis | Lolo Basket</title>'
            . '<style>'
            . 'body{background:#101827;color:#f1f5f9;font-family:Arial;'
            . 'padding:24px;line-height:1.6}'
            . 'main{max-width:900px;margin:auto}'
            . 'section{background:#1e293b;padding:20px;border-radius:12px;'
            . 'margin:18px 0}h1,h2,a{color:#fb923c}'
            . '</style></head><body><main>'
            . '<a href="' . $volver . '">← Volver a partidos</a>'
            . '<h1>Análisis básico — Lolo Basket</h1>'
            . '<h2>' . $nombreLocal . ' vs ' . $nombreVisitante . '</h2>'
            . '<p>Estimación experimental basada en promedios. '
            . 'No es una recomendación de apuesta ni una garantía.</p>'

            . '<section><h2>Datos utilizados</h2>'
            . '<p>Periodo buscado: ' . e($desde) . ' al ' . e($hasta) . '.</p>'
            . '<p>' . $nombreLocal . ': últimos '
            . $local['cantidad'] . ' partidos finalizados. Anota '
            . $numero($local['anotados']) . ' y recibe '
            . $numero($local['recibidos']) . ' puntos de promedio.</p>'
            . '<p>' . $nombreVisitante . ': últimos '
            . $visitante['cantidad'] . ' partidos finalizados. Anota '
            . $numero($visitante['anotados']) . ' y recibe '
            . $numero($visitante['recibidos']) . ' puntos de promedio.</p>'
            . '</section>'

            . '<section><h2>Ganador estimado</h2><p>'
            . $ganadorSeguro . '</p><p>Proyección local: '
            . $numero($puntosLocal) . ' puntos. Proyección visitante: '
            . $numero($puntosVisitante) . ' puntos.</p>'
            . '<p>No se calcula probabilidad de victoria con este método.</p>'
            . '</section>'

            . '<section><h2>Más / Menos</h2><p>Total estimado: '
            . $numero($totalEstimado) . '. Línea ingresada: '
            . $numero($lineaTotal) . '.</p><p>' . $lecturaTotal
            . '.</p><p>Diferencia estimación menos línea: '
            . $numero($diferenciaTotal) . ' puntos.</p></section>'

            . '<section><h2>Hándicap</h2><p>Margen estimado del local: '
            . $numero($margenLocal) . '. Hándicap local ingresado: '
            . $numero($handicapLocal) . '.</p><p>'
            . $lecturaHandicap . '.</p><p>Margen más hándicap: '
            . $numero($margenAjustado) . ' puntos.</p></section>'

            . '<section><h2>Cuotas ingresadas</h2><p>Local: '
            . $cuotaLocal . '. Visitante: ' . $cuotaVisitante . '.</p>'
            . '<p>Se muestran como referencia. Este método no determina '
            . 'si las cuotas ofrecen valor ni calcula rentabilidad.</p>'
            . '</section>'

            . '<section><h2>Método y limitaciones</h2>'
            . '<p>Los puntos de cada equipo se estiman promediando su '
            . 'anotación reciente y los puntos recibidos por su rival.</p>'
            . '<p>No ajusta por lesiones, alineaciones, fuerza de los rivales,