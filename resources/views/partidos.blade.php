<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partidos | Lolo Basket</title>

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

        .volver {
            display: inline-block;
            margin-bottom: 16px;
            color: #fb923c;
            text-decoration: none;
        }

        .resumen {
            color: #cbd5e1;
            line-height: 1.6;
        }

        .lista {
            display: grid;
            gap: 16px;
            margin-top: 24px;
        }

        .partido {
            padding: 24px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
        }

        .fecha {
            margin-top: 0;
            color: #cbd5e1;
        }

        .enfrentamiento {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 16px;
            text-align: center;
        }

        .equipo {
            font-size: 18px;
            font-weight: bold;
        }

        .etiqueta {
            display: block;
            margin-bottom: 8px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: normal;
        }

        .marcador {
            color: #fb923c;
            font-size: 24px;
            font-weight: bold;
        }

        .estado {
            margin-bottom: 0;
            color: #cbd5e1;
            text-align: center;
        }

        @media (max-width: 500px) {
            .enfrentamiento {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main>
        <a class="volver" href="{{ url('/equipos') }}">
            ← Volver a equipos
        </a>

        <h1>Lolo Basket — Partidos del equipo</h1>

        <p class="resumen">
            Periodo consultado: {{ $desde }} al {{ $hasta }}.<br>
            Partidos mostrados: {{ count($partidos) }}.
        </p>

        <section class="lista">
            @forelse ($partidos as $partido)
                <article class="partido">
                    <p class="fecha">
                        Fecha:
                        {{ substr($partido['date'] ?? '', 0, 10) }}
                    </p>

                    <div class="enfrentamiento">
                        <div class="equipo">
                            <span class="etiqueta">LOCAL</span>
                            {{ $partido['home_team']['full_name'] ?? 'Sin datos' }}
                        </div>

                        <div class="marcador">
                            {{ $partido['home_team_score'] ?? '—' }}
                            :
                            {{ $partido['visitor_team_score'] ?? '—' }}
                        </div>

                        <div class="equipo">
                            <span class="etiqueta">VISITANTE</span>
                            {{ $partido['visitor_team']['full_name'] ?? 'Sin datos' }}
                        </div>
                    </div>

                    <p class="estado">
                        Estado API: {{ $partido['status'] ?? 'Sin datos' }}
                    </p>
                </article>
            @empty
                <article class="partido">
                    <p>
                        No se encontraron partidos para este equipo
                        en el periodo consultado.
                    </p>
                    <p>
                        Puedes volver y consultar otro equipo.
                    </p>
                </article>
            @endforelse
        </section>
    </main>
</body>
</html>