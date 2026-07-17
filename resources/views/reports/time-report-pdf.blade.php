<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report ore</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 6px; }
        .total { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .subtotal { font-size: 12px; margin-bottom: 16px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f3f4f6; }
        td.hours, th.hours { text-align: right; }
    </style>
</head>
<body>
    <h1>Report ore</h1>
    <p class="total">Totale: {{ number_format($totalHours, 2) }} h</p>
    <p class="subtotal">Tariffa media applicata: {{ $averageRate !== null ? '€ '.$averageRate.'/h' : '—' }}</p>

    <h2>Per progetto</h2>
    <table>
        <thead>
            <tr><th>Progetto</th><th class="hours">Ore</th><th class="hours">Importo</th></tr>
        </thead>
        <tbody>
            @forelse ($byProject as $row)
                <tr><td>{{ $row['project_name'] }}</td><td class="hours">{{ number_format($row['hours'], 2) }}</td><td class="hours">€ {{ $row['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3">Nessun dato</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Per cliente</h2>
    <table>
        <thead>
            <tr><th>Cliente</th><th class="hours">Ore</th><th class="hours">Importo</th></tr>
        </thead>
        <tbody>
            @forelse ($byClient as $row)
                <tr><td>{{ $row['client_name'] }}</td><td class="hours">{{ number_format($row['hours'], 2) }}</td><td class="hours">€ {{ $row['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3">Nessun dato</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Per utente</h2>
    <table>
        <thead>
            <tr><th>Utente</th><th class="hours">Ore</th><th class="hours">Importo</th></tr>
        </thead>
        <tbody>
            @forelse ($byUser as $row)
                <tr><td>{{ $row['user_name'] }}</td><td class="hours">{{ number_format($row['hours'], 2) }}</td><td class="hours">€ {{ $row['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3">Nessun dato</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Per Work Package</h2>
    <table>
        <thead>
            <tr><th>Work Package</th><th class="hours">Ore</th><th class="hours">Importo</th></tr>
        </thead>
        <tbody>
            @forelse ($byWorkPackage as $row)
                <tr><td>{{ $row['work_package_name'] }}</td><td class="hours">{{ number_format($row['hours'], 2) }}</td><td class="hours">€ {{ $row['amount'] }}</td></tr>
            @empty
                <tr><td colspan="3">Nessun dato</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Budget previsto vs consumato</h2>
    <table>
        <thead>
            <tr><th>Progetto</th><th class="hours">Consumate</th><th class="hours">Budget</th><th class="hours">Utilizzo</th></tr>
        </thead>
        <tbody>
            @forelse ($budgetComparison as $row)
                <tr>
                    <td>{{ $row->projectName }}</td>
                    <td class="hours">{{ number_format($row->consumedHours, 2) }}</td>
                    <td class="hours">{{ $row->budgetHours !== null ? number_format($row->budgetHours, 2) : '—' }}</td>
                    <td class="hours">{{ $row->utilizationPercentage !== null ? number_format($row->utilizationPercentage, 2).'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nessun dato</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
