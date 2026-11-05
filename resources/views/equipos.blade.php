<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipos | Lolo Basket</title>

    <style>
        body {
            margin: 0;
            padding: 32px 16px;
            background: #101827;
            color: #f1f5f9;
            font-family: Arial, sans-serif;
        }

        main {
            max-width: 1000px;
            margin: auto;
        }

        h1 {
            color: #fb923c;
        }

        .resumen {
            color: #cbd5e1;
            margin-bottom: 24px;
        }

        .equipos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .equipo {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
        }

        .equipo h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .equipo p {
            color: #cbd5e1;
            line-height: 1.5;
        }

        .abreviatura {
            color: #fb923c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <main>
        <h1>Lolo Basket — Equipos</h1>
<a
    href="{{ url('/partidos-dia') }}"
    style="display: inline-block; padding: 12px 20px;
           margin: 12px 0 20px;
           background: #fb923c; color: #101827;
           border-radius: 8px; text-decoration: none;
           font-weight: bold;"
>
    Partidos del día
</a>

        <p class="resumen">
            Registros recibidos de la API: {{ count($equipos) }}
        </p>

        <section class="equipos">
            @forelse ($equipos as $equipo)
                <article class="equipo">
                    <h2>{{ $equipo['full_name'] ?? 'Sin nombre' }}</h2>

                    <span class="abreviatura">
                        {{ $equipo['abbreviation'] ?? '' }}
                    </span>

                    <p>
                        Ciudad: {{ $equipo['city'] ?? 'Sin datos' }}<br>
                        Conferencia: {{ $equipo['conference'] ?? 'Sin datos' }}<br>
                        División: {{ $equipo['division'] ?? 'Sin datos' }}
                    </p>
                                    <a
                        href="{{ url('/equipos/' . $equipo['id'] . '/partidos') }}"
                        style="display: inline-block; padding: 10px 16px;
                               background: #fb923c; color: #101827;
                               border-radius: 8px; text-decoration: none;
                               font-weight: bold;"
                    >
                        Ver partidos
                    </a>
                </article>
            @empty
                <p>No se recibieron equipos.</p>
            @endforelse
        </section>
    </main>
</body>
</html>
Route::get('/partidos-dia', function (\Illuminate\Http\Request $request) {
    $datos = $request->validate([
        'fecha' => ['nullable', 'date_format:Y-m-d'],
    ]);

    $fecha = $datos['fecha'] ?? now()->toDateString();

    try {
        $respuesta = Http::acceptJson()
            ->withHeaders([
                'Authorization' => config('basket.api_key'),
            ])
            ->connectTimeout(10)
            ->timeout(20)
            ->get(config('basket.base_url') . '/games', [
                'dates' => [$fecha],
                'per_page' => 100,
            ]);

        if (! $respuesta->successful()) {
            return response(
                'No se pudieron consultar los partidos. Código API: '
                . $respuesta->status(),
                502
            );
        }

        $partidos = $respuesta->json('data');

        if (! is_array($partidos)) {
            return response('La API devolvió un formato inesperado.', 502);
        }

        return view('partidos-dia', [
            'fecha' => $fecha,
            'partidos' => $partidos,
        ]);

    } catch (ConnectionException $error) {
        return response(
            'No se pudo conectar con la API. Intenta de nuevo más tarde.',
            502
        );
    }
});