<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partidos del día | Lolo Basket</title>

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

        h1, a, summary {
            color: #fb923c;
        }

        a {
            text-decoration: none;
        }

        .filtro, .partido {
            padding: 24px;
            margin: 20px 0;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
        }

        .controles {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        input, button, .hoy {
            padding: 12px;
            border: 0;
            border-radius: 8px;
            font: inherit;
        }

        input {
            background: #f1f5f9;
            color: #101827;
        }

        button, .hoy {
            background: #fb923c;
            color: #101827;
            font-weight: bold;
            cursor: pointer;
        }

        .encuentro {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 16px;
            text-align: center;
        }

        .equipo {
            font-size: 19px;
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

        .nota {
            color: #cbd5e1;
            line-height: 1.6;
        }

        .estado {
            text-align: center;
        }

        summary {
            cursor: pointer;
            margin-top: 24px;
        }

        .formulario {
            max-width: 440px;
            margin: 20px auto;
        }

        .campo {
            display: block;
            margin-top: 18px;
        }

        .campo input {
            display: block;
            width: 100%;
            box-sizing: border-box;
            margin-top: 8px;
        }

        .enviar {
            margin-top: 24px;
        }

        .error {
            color: #fca5a5;
        }

        @media (max-width: 550px) {
            .encuentro {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main>
        <a href="{{ url('/equipos') }}">← Volver a equipos</a>

        <h1>Lolo Basket — Partidos del día</h1>

        <form class="filtro" method="GET" action="{{ url('/partidos-dia') }}">
            <div class="controles">
                <label for="fecha">Elige una fecha:</label>

                <input
                    type="date"
                    id="fecha"
                    name="fecha"
                    value="{{ $fecha }}"
                    required
                >

                <button type="submit">Buscar partidos</button>

                <a class="hoy" href="{{ url('/partidos-dia') }}">Hoy</a>
            </div>

            @error('fecha')
                <p class="error">{{ $message }}</p>
            @enderror
        </form>

        <p class="nota">
            Fecha consultada: {{ $fecha }}.<br>
            Partidos encontrados: {{ count($partidos) }}.
        </p>

        @forelse ($partidos as $partido)
            @php
                $estado = (string) ($partido['status'] ?? '');

                $programado = preg_match(
                    '/^\d{4}-\d{2}-\d{2}T/',
                    $estado
                ) === 1;

                $finalizado = str_starts_with(
                    strtolower($estado),
                    'final'
                );
            @endphp

            <article class="partido">
                <div class="encuentro">
                    <div class="equipo">
                        <span class="etiqueta">LOCAL</span>
                        {{ $partido['home_team']['full_name'] ?? 'Sin datos' }}
                    </div>

                    <div class="marcador">
                        @if ($programado)
                            VS
                        @else
                            {{ $partido['home_team_score'] ?? '—' }}
                            :
                            {{ $partido['visitor_team_score'] ?? '—' }}
                        @endif
                    </div>

                    <div class="equipo">
                        <span class="etiqueta">VISITANTE</span>
                        {{ $partido['visitor_team']['full_name'] ?? 'Sin datos' }}
                    </div>
                </div>

                <p class="nota estado">
                    @if ($programado)
                        Programado.<br>
                        Inicio según API (UTC): {{ $estado }}
                    @elseif ($finalizado)
                        Finalizado.
                    @else
                        Estado API: {{ $estado ?: 'Sin datos' }}
                    @endif
                </p>

                @if ($programado)
                    <details>
                        <summary>Introducir líneas de Ecuabet</summary>

                        <form
                            class="formulario"
                            method="POST"
                            action="{{ url('/analizar/' . $partido['id']) }}"
                        >
                            @csrf

                            <p class="nota">
                                Usa líneas de partido completo con la misma
                                regla sobre prórroga. Los valores de ejemplo
                                no son líneas reales.
                            </p>

                            <label class="campo">
                                Total de puntos de Ecuabet:
                                <input
                                    type="number"
                                    name="total"
                                    step="0.5"
                                    min="1"
                                    placeholder="Ejemplo: 225.5"
                                    required
                                >
                            </label>

                            <label class="campo">
                                Hándicap del LOCAL:
                                {{ $partido['home_team']['full_name'] ?? '' }}
                                <input
                                    type="number"
                                    name="handicap_local"
                                    step="0.5"
                                    placeholder="Ejemplo: -4.5 o +4.5"
                                    required
                                >
                            </label>

                            <p class="nota">
                                Conserva el signo del hándicap del equipo local.
                            </p>

                            <label class="campo">
                                Cuota decimal del LOCAL (opcional):
                                <input
                                    type="number"
                                    name="cuota_local"
                                    step="0.01"
                                    min="1.01"
                                    placeholder="Ejemplo: 1.80"
                                >
                            </label>

                            <label class="campo">
                                Cuota decimal del VISITANTE (opcional):
                                <input
                                    type="number"
                                    name="cuota_visitante"
                                    step="0.01"
                                    min="1.01"
                                    placeholder="Ejemplo: 2.10"
                                >
                            </label>

                            <button class="enviar" type="submit">
                                Analizar partido
                            </button>

                          
                        </form>
                    </details>
                @else
                    <p class="nota">
                        El formulario previo al partido no está disponible
                        para este estado.
                    </p>
                @endif
            </article>
        @empty
            <article class="partido">
                <p>No se encontraron partidos para esta fecha.</p>
                <p>Elige otro día y pulsa “Buscar partidos”.</p>
            </article>
        @endforelse

        <p class="nota">
            “Hoy” utiliza la fecha configurada en el servidor.
            La búsqueda utiliza la fecha de calendario de la API.
        </p>
    </main>
</body>
</html>
