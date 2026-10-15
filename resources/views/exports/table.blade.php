<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 28px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #171717; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #525252; margin-bottom: 12px; }
        .meta span { margin-right: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; text-align: left; font-weight: bold; text-transform: uppercase; font-size: 8px; color: #404040; }
        th, td { border-bottom: 1px solid #e5e5e5; padding: 5px 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
        .note { margin-top: 10px; color: #b45309; }
        .empty { text-align: center; color: #737373; padding: 20px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #a3a3a3; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        <span>Generado: {{ $generatedAt }}</span>
        <span>Registros: {{ $total }}</span>
        @foreach ($filters as $filter)
            <span>{{ $filter }}</span>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ count($headings) }}">No hay registros con los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($total > $limit)
        <p class="note">El PDF muestra los primeros {{ $limit }} de {{ $total }} registros. Para el listado completo, exporta a Excel.</p>
    @endif

    <div class="footer">{{ config('app.name') }} · {{ $title }}</div>
</body>
</html>
