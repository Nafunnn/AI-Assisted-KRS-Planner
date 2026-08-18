<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>KRS - {{ $plan->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        .summary { margin-top: 16px; font-weight: bold; }
        .watermark { margin-top: 24px; padding-top: 12px; border-top: 1px dashed #999; color: #666; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $plan->name }}</h1>
    <div class="meta">
        <div>Mahasiswa: {{ $plan->user->name }}</div>
        <div>Penawaran: {{ $plan->courseOffering->title }}</div>
        <div>Diekspor: {{ now()->format('d M Y H:i') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode MK</th>
                <th>Mata Kuliah</th>
                <th>SKS</th>
                <th>Kelompok</th>
                <th>Jadwal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->courseSection->course->code }}</td>
                    <td>{{ $item->courseSection->course->name }}</td>
                    <td>{{ $item->courseSection->course->sks }}</td>
                    <td>{{ $item->courseSection->group_code }}</td>
                    <td>
                        @foreach ($item->courseSection->schedules as $schedule)
                            {{ $schedule->day->label() }}, {{ substr($schedule->starts_at, 0, 5) }} - {{ substr($schedule->ends_at, 0, 5) }}@if (!$loop->last)<br>@endif
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Belum ada mata kuliah yang dipilih.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary">Total SKS: {{ $totalSks }}</div>
    <div class="watermark">{{ $generatedStamp }}</div>
</body>
</html>
