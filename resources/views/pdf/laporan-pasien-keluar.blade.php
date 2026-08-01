<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<style>
@page { margin: 26px 30px; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #232333; background: #fff; padding: 4px 6px; }

/* ── Header ── */
.header-table { width: 100%; border-collapse: collapse; }
.header-table td { padding: 0; vertical-align: middle; }
.header-table .logo-cell { width: 56px; }
.header-table .logo-cell img { width: 50px; height: 50px; object-fit: contain; }
.header-table .title-cell { text-align: center; padding-left: 8px; }
.header-table .spacer-cell { width: 56px; }
.header h1 { font-size: 17px; font-weight: bold; color: #0A7A5E; letter-spacing: 0.4px; }
.rs-name { font-size: 11.5px; font-weight: 700; color: #1a1a2e; margin-top: 3px; }
.sub { font-size: 9.5px; color: #6b7280; margin-top: 1px; }

.header-rule { height: 4px; background: linear-gradient(90deg, #0A7A5E 0%, #0A7A5E 100%); background-color: #0A7A5E; margin: 12px 0 16px; border-radius: 2px; }

/* ── Filter strip ── */
.filter-row {
    background: #f6f8f7;
    border-left: 3px solid #0A7A5E;
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 16px;
    font-size: 9.5px;
    color: #4b5563;
}
.filter-row span { margin-right: 22px; }
.filter-row strong { color: #1a1a2e; }

/* ── Summary cards ── */
.summary-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 18px; }
.summary-table td {
    background: #fbfcfc;
    border: 1px solid #e6e9e8;
    border-top: 3px solid #0A7A5E;
    border-radius: 6px;
    text-align: center;
    padding: 12px 4px 10px;
    width: 20%;
}
.summary-table .val { font-size: 20px; font-weight: bold; line-height: 1; }
.summary-table .lbl { font-size: 8.5px; color: #6b7280; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600; }

/* ── Section label ── */
.section-label { font-size: 10.5px; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; padding-left: 2px; border-left: 3px solid #0A7A5E; padding-left: 8px; }

/* ── Table ── */
table.data { width: 100%; border-collapse: collapse; font-size: 9.3px; margin-bottom: 4px; }
table.data thead tr { background: #0A7A5E; }
table.data thead th {
    padding: 8px 7px;
    text-align: left;
    font-weight: 700;
    font-size: 8.7px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #ffffff;
}
table.data tbody tr { border-bottom: 1px solid #ececec; }
table.data tbody tr:nth-child(even) { background: #f7faf9; }
table.data tbody td { padding: 7px 7px; vertical-align: top; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 9px; font-size: 8.2px; font-weight: 700; }
.badge-ext { background: #d6f0e6; color: #0A7A5E; }
.badge-int { background: #e9edf1; color: #4b5867; }
.badge-l   { background: #dbeaf9; color: #1f5f9c; }
.badge-p   { background: #f1e3fa; color: #7a2fb0; }

.no-col    { width: 22px; text-align: center; color: #b0b0b0; }
.nama-col  { width: 15%; }
.mr-col    { width: 8%; font-family: 'Courier New', monospace; color: #555; }
.jk-col    { width: 4%; text-align: center; }
.jenis-col { width: 8%; }
.dx-col    { width: 18%; color: #333; }
.bed-col   { width: 8%; color: #0A7A5E; font-weight: 700; }
.masuk-col { width: 9%; font-family: 'Courier New', monospace; font-size: 8.5px; }
.keluar-col{ width: 9%; font-family: 'Courier New', monospace; font-size: 8.5px; }
.lama-col  { width: 7%; color: #4b5867; }
.asal-col  { width: 9%; }

.empty-state { text-align: center; padding: 40px 0; color: #9ca3af; font-style: italic; font-size: 10.5px; border: 1px dashed #dcdfe0; border-radius: 6px; }

/* ── Footer ── */
.footer-rule { height: 1px; background: #e5e7eb; margin: 22px 0 12px; }
.footer-table { width: 100%; border-collapse: collapse; }
.footer-table td { vertical-align: top; font-size: 8.7px; color: #9ca3af; }
.footer-table .sign-cell { text-align: right; }
.footer-table .sign-cell .role { font-size: 8.8px; color: #232333; margin-bottom: 30px; }
.footer-table .sign-cell .line { color: #232333; font-size: 9px; }
.footer-table .sign-cell .title { font-size: 8px; color: #9ca3af; margin-top: 3px; }

.page-break { page-break-after: always; }
</style>
</head>
<body>

<!-- ── Header ── -->
<table class="header-table">
    <tr>
        <td class="logo-cell"><img src="{{ public_path('images/logo-urip.png') }}" alt="Logo RS"/></td>
        <td class="title-cell">
            <h1>LAPORAN PASIEN KELUAR ICU</h1>
            <p class="rs-name">RS Urip Sumoharjo</p>
            <p class="sub">Instalasi Perawatan Intensif (ICU)</p>
        </td>
        <td class="spacer-cell"></td>
    </tr>
</table>
<div class="header-rule"></div>

<!-- ── Filter info ── -->
<div class="filter-row">
    <span><strong>Periode:</strong>
        {{ \Carbon\Carbon::parse($filters['tgl_dari'])->format('d/m/Y') }}
        &ndash;
        {{ \Carbon\Carbon::parse($filters['tgl_sampai'])->format('d/m/Y') }}
    </span>
    @if($filters['jenis'])
        <span><strong>Jenis:</strong> {{ $filters['jenis'] === 'external' ? 'Booking Eksternal' : 'Booking Internal' }}</span>
    @endif
    @if($filters['nama'])
        <span><strong>Nama:</strong> {{ $filters['nama'] }}</span>
    @endif
    <span><strong>Dicetak:</strong> {{ now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }}</span>
    <span><strong>Total Data:</strong> {{ $summary['total'] }}</span>
</div>

<!-- ── Summary ── -->
<table class="summary-table">
    <tr>
        <td>
            <div class="val" style="color:#0A7A5E">{{ $summary['total'] }}</div>
            <div class="lbl">Total Keluar</div>
        </td>
        <td>
            <div class="val" style="color:#0A7A5E">{{ $summary['external'] }}</div>
            <div class="lbl">Eksternal</div>
        </td>
        <td>
            <div class="val" style="color:#4b5867">{{ $summary['internal'] }}</div>
            <div class="lbl">Internal</div>
        </td>
        <td>
            <div class="val" style="color:#1f5f9c">{{ $summary['laki'] }}</div>
            <div class="lbl">Laki-Laki</div>
        </td>
        <td>
            <div class="val" style="color:#7a2fb0">{{ $summary['perempuan'] }}</div>
            <div class="lbl">Perempuan</div>
        </td>
    </tr>
</table>

<!-- ── Table ── -->
<div class="section-label">Daftar Pasien</div>
@if(count($data) === 0)
    <div class="empty-state">Tidak ada data pasien keluar ICU pada periode ini.</div>
@else
<table class="data">
    <thead>
        <tr>
            <th class="no-col">#</th>
            <th class="nama-col">Nama Pasien</th>
            <th class="mr-col">No. MR</th>
            <th class="jk-col">JK</th>
            <th class="jenis-col">Jenis</th>
            <th class="dx-col">Diagnosa</th>
            <th class="bed-col">Bed ICU</th>
            <th class="asal-col">Asal</th>
            <th class="masuk-col">Masuk ICU</th>
            <th class="keluar-col">Keluar ICU</th>
            <th class="lama-col">Lama Rawat</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data as $i => $row)
        <tr>
            <td class="no-col">{{ $i + 1 }}</td>
            <td class="nama-col" style="font-weight:700">{{ $row['nama_pasien'] }}</td>
            <td class="mr-col">{{ $row['No_MR'] }}</td>
            <td class="jk-col">
                @if($row['jenis_kelamin'] === 'L')
                    <span class="badge badge-l">L</span>
                @elseif($row['jenis_kelamin'] === 'P')
                    <span class="badge badge-p">P</span>
                @else
                    <span style="color:#c0c0c0">—</span>
                @endif
            </td>
            <td class="jenis-col">
                <span class="badge {{ $row['sumber'] === 'external' ? 'badge-ext' : 'badge-int' }}">
                    {{ $row['sumber'] === 'external' ? 'Ext' : 'Int' }}
                </span>
            </td>
            <td class="dx-col">{{ $row['diagnosa'] }}</td>
            <td class="bed-col">{{ $row['nama_bed'] }}</td>
            <td class="asal-col" style="color:#6b7280;font-size:8.5px">{{ $row['asal'] }}</td>
            <td class="masuk-col">{{ $row['masuk_at'] }}</td>
            <td class="keluar-col">{{ $row['keluar_at'] }}</td>
            <td class="lama-col">{{ $row['lama_rawat'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- ── Footer ── -->
<div class="footer-rule"></div>
<table class="footer-table">
    <tr>
        <td>
            <div>Laporan ini dibuat secara otomatis oleh Sistem Monitoring Bed ICU.</div>
            <div>Dicetak: {{ now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s') }}</div>
        </td>
        <td class="sign-cell">
            <div class="role">Mengetahui,</div>
            <div class="line">____________________________</div>
            <div class="title">Kepala Instalasi ICU</div>
        </td>
    </tr>
</table>

</body>
</html>