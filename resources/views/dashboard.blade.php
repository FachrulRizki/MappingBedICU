<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Bed ICU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-size: .875rem; }
        .card-stage  { border-top: 4px solid; }
        .stage-daftar  { border-color: #6c757d; }
        .stage-igd     { border-color: #ffc107; }
        .stage-spri    { border-color: #0dcaf0; }
        .stage-waiting { border-color: #dc3545; }
        .stage-icu     { border-color: #212529; }
        .patient-card  { background:#fff; border:1px solid #dee2e6; border-radius:8px; padding:10px 12px; margin-bottom:8px; }
        .patient-card:last-child { margin-bottom:0; }
        .bed-tile      { border-radius:8px; padding:8px 12px; margin-bottom:6px; border:1px solid #dee2e6; background:#fff; }
        .bed-KOSONG    { border-left:5px solid #198754; }
        .bed-BOOKING   { border-left:5px solid #ffc107; }
        .bed-ISI       { border-left:5px solid #dc3545; }
        .empty-state   { color:#adb5bd; font-size:.8rem; text-align:center; padding:14px 0; }
        .lbl           { font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:20px; }
        .ph-hdr        { font-size:.7rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .kelas-group   { font-size:.7rem; font-weight:700; color:#6c757d; text-transform:uppercase; margin: 6px 0 4px; }
    </style>
</head>
<body class="p-3">
<div class="container-fluid">

    {{-- ── Header ── --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bi bi-hospital me-2 text-primary"></i>Monitoring Alur Pasien ICU</h5>
            <small class="text-muted">Simulasi</small>
        </div>
        <span class="badge bg-secondary">{{ now()->format('d M Y, H:i') }}</span>
    </div>

    {{-- ── Alert ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">

        <div class="col-lg-8">

            {{-- Step 1: Pendaftaran --}}
            <div class="card shadow-sm mb-3 card-stage stage-daftar">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="ph-hdr text-secondary"><i class="bi bi-person-plus me-1"></i>Step 1 — Pendaftaran</span>
                    <span class="badge bg-secondary rounded-pill">{{ $tahapDaftar->count() }}</span>
                </div>
                <div class="card-body pb-2">
                    <form action="{{ route('icu.tambah') }}" method="POST" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-4">
                            <input type="text" name="Nama_Pasien" class="form-control form-control-sm" placeholder="Nama Lengkap Pasien" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="No_Identitas" class="form-control form-control-sm" placeholder="No. KTP / Identitas" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="KartuBPJS" class="form-control form-control-sm" placeholder="No. BPJS (opsional)">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-plus-lg me-1"></i>Daftarkan
                            </button>
                        </div>
                    </form>
                    @forelse($tahapDaftar as $a)
                        <div class="patient-card d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $a->pasien->Nama_Pasien }}</strong>
                                <div class="text-muted" style="font-size:.75rem">
                                    No.MR: {{ $a->No_MR }} &bull; No.Reg: {{ $a->No_Reg }}
                                </div>
                            </div>
                            <form action="{{ route('icu.kirim_igd', $a->id) }}" method="POST">
                                @csrf
                                <button class="btn btn-warning btn-sm text-dark">
                                    <i class="bi bi-arrow-right-circle me-1"></i>Kirim ke IGD
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="empty-state"><i class="bi bi-inbox me-1"></i>Tidak ada pasien baru</div>
                    @endforelse
                </div>
            </div>

            {{-- Step 2 & 3: IGD — Triase & Buat SPRI --}}
            <div class="card shadow-sm mb-3 card-stage stage-igd">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="ph-hdr text-warning"><i class="bi bi-activity me-1"></i>Step 2 & 3 — IGD: Triase & Buat SPRI</span>
                    <span class="badge bg-warning text-dark rounded-pill">{{ $tahapIgd->count() }}</span>
                </div>
                <div class="card-body pb-2">
                    @forelse($tahapIgd as $a)
                        <div class="patient-card">
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <strong>{{ $a->pasien->Nama_Pasien }}</strong>
                                    <span class="badge bg-warning text-dark ms-2 lbl">Sedang Diperiksa</span>
                                    <div class="text-muted" style="font-size:.75rem">No.Reg: {{ $a->No_Reg }}</div>
                                </div>
                            </div>
                            <form action="{{ route('icu.buat_spri', $a->id) }}" method="POST" class="row g-2">
                                @csrf
                                <div class="col-md-5">
                                    <input type="text" name="Diagnosis" class="form-control form-control-sm"
                                           placeholder="Diagnosis Dokter IGD" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="IndikasiRI" class="form-control form-control-sm"
                                           placeholder="Indikasi Rawat Inap" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="Keterangan" class="form-control form-control-sm"
                                           placeholder="Keterangan (opsional)">
                                </div>
                                {{-- Dropdown dari M_KELAS — Kode_Kelas sebagai value --}}
                                <div class="col-md-6">
                                    <select name="required_bed_type" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>-- Jenis Bed ICU --</option>
                                        @foreach($masterKelas as $kelas)
                                            <option value="{{ $kelas->Kode_Kelas }}">
                                                {{ $kelas->Kode_Kelas }} — {{ $kelas->Nama_Kelas }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-danger btn-sm w-100">
                                        <i class="bi bi-file-medical me-1"></i>Buat SPRI
                                    </button>
                                </div>
                            </form>
                        </div>
                    @empty
                        <div class="empty-state"><i class="bi bi-inbox me-1"></i>Tidak ada pasien di IGD</div>
                    @endforelse
                </div>
            </div>

            {{-- Step 4: Approve SPRI --}}
            <div class="card shadow-sm mb-3 card-stage stage-spri">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="ph-hdr text-info"><i class="bi bi-clipboard2-check me-1"></i>Step 4 — Verifikasi Jaminan & Approve SPRI</span>
                    <span class="badge bg-info text-dark rounded-pill">{{ $tahapSpri->count() }}</span>
                </div>
                <div class="card-body pb-2">
                    @forelse($tahapSpri as $a)
                        @php $spri = $a->pendaftaran->spriAktif ?? null; @endphp
                        <div class="patient-card d-flex justify-content-between align-items-start">
                            <div>
                                <strong>{{ $a->pasien->Nama_Pasien }}</strong>
                                <span class="badge bg-danger ms-2 lbl">Butuh: {{ $a->required_bed_type }}</span>
                                <div class="text-muted mt-1" style="font-size:.75rem">
                                    No.Reg: {{ $a->No_Reg }}
                                    @if($spri)
                                        &bull; {{ $spri->Diagnosis }}
                                        &bull; {{ $spri->IndikasiRI }}
                                    @endif
                                </div>
                            </div>
                            <form action="{{ route('icu.approve_spri', $a->id) }}" method="POST" class="ms-3 flex-shrink-0">
                                @csrf
                                <button class="btn btn-primary btn-sm">
                                    <i class="bi bi-check2-circle me-1"></i>Approve SPRI
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="empty-state"><i class="bi bi-inbox me-1"></i>Tidak ada SPRI menunggu persetujuan</div>
                    @endforelse
                </div>
            </div>

            {{-- Step 5: Pencocokan Bed --}}
            <div class="card shadow-sm mb-3 card-stage stage-waiting">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span class="ph-hdr text-danger"><i class="bi bi-hourglass-split me-1"></i>Step 5 — Pencocokan Bed & Alokasi Kamar</span>
                    <span class="badge bg-danger rounded-pill">{{ $tahapNungguKamar->count() + $tahapBooking->count() }}</span>
                </div>
                <div class="card-body pb-2">
                    <small class="text-muted d-block mb-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Hanya bed <strong>KOSONG</strong> dengan <strong>Kode_Kelas</strong> yang cocok yang bisa dipilih.
                        Kode_Kelas diambil dari <code>M_KELAS</code> via <code>M_RUANG_MASTER</code>.
                    </small>

                    {{-- Waiting ICU --}}
                    @forelse($tahapNungguKamar as $a)
                        <div class="patient-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $a->pasien->Nama_Pasien }}</strong>
                                    <span class="badge bg-danger ms-2 lbl">Butuh: {{ $a->required_bed_type }}</span>
                                    <div class="text-muted" style="font-size:.75rem">No.Reg: {{ $a->No_Reg }}</div>
                                </div>

                                @php
                                    // Filter bed kosong yang Kode_Kelas-nya cocok
                                    $bedCocok = $kamarKosong->filter(
                                        fn($b) => $b->ruang?->Kode_Kelas === $a->required_bed_type
                                    );
                                @endphp

                                <form action="{{ route('icu.alokasi_bed', $a->id) }}" method="POST" class="d-flex gap-2 ms-3 flex-shrink-0">
                                    @csrf
                                    <select name="Kode_Ruang" class="form-select form-select-sm"
                                            style="min-width:180px"
                                            {{ $bedCocok->isEmpty() ? 'disabled' : 'required' }}>
                                        <option value="" disabled selected>-- Pilih Bed --</option>
                                        @foreach($bedCocok as $bed)
                                            <option value="{{ $bed->Kode_Ruang }}">
                                                {{ $bed->ruang?->Nama_RuangM ?? $bed->Kode_Ruang }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @if($bedCocok->isNotEmpty())
                                        <button type="submit" class="btn btn-primary btn-sm text-nowrap">
                                            <i class="bi bi-house-check me-1"></i>Dapat Kamar
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm text-nowrap" disabled>
                                            <i class="bi bi-clock me-1"></i>Menunggu...
                                        </button>
                                    @endif
                                </form>
                            </div>
                        </div>
                    @empty
                    @endforelse

                    {{-- Booking ICU — sudah dapat kamar, menunggu transfer --}}
                    @forelse($tahapBooking as $a)
                        <div class="patient-card" style="border-left:4px solid #0d6efd;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $a->pasien->Nama_Pasien }}</strong>
                                    <span class="badge bg-primary ms-2 lbl">
                                        <i class="bi bi-house me-1"></i>{{ $a->bed?->ruang?->Nama_RuangM ?? $a->allocated_bed_id }}
                                    </span>
                                    <div class="text-muted" style="font-size:.75rem">
                                        No.Reg: {{ $a->No_Reg }}
                                        &bull; Jenis: {{ $a->required_bed_type }}
                                        &bull; Status: Kamar Dipesan
                                    </div>
                                </div>
                                <form action="{{ route('icu.masuk_ruangan', $a->id) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-dark btn-sm">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Antar ke Ruangan
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                    @endforelse

                    @if($tahapNungguKamar->isEmpty() && $tahapBooking->isEmpty())
                        <div class="empty-state"><i class="bi bi-inbox me-1"></i>Tidak ada pasien menunggu kamar</div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ════════════════════════════════════════
             KOLOM KANAN — Status Bed + Pasien di ICU
        ════════════════════════════════════════ --}}
        <div class="col-lg-4">

            {{-- Denah Bed Real-time --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 bg-white border-bottom">
                    <span class="ph-hdr text-dark"><i class="bi bi-grid-3x2 me-1"></i>Denah Bed ICU — Real-time</span>
                </div>
                <div class="card-body py-2 px-3">

                    {{-- Kelompokkan per Kode_Kelas (= bed_type) --}}
                    @php
                        $bedGroups = $semuaKamar->groupBy(fn($b) => $b->ruang?->Kode_Kelas ?? $b->KelasBPJS ?? 'Lainnya');
                    @endphp

                    @foreach($bedGroups as $kodeKelas => $beds)
                        @php
                            $namaKelas = $beds->first()?->ruang?->kelas?->Nama_Kelas ?? $kodeKelas;
                        @endphp
                        <div class="kelas-group">
                            <i class="bi bi-tag me-1"></i>{{ $kodeKelas }} — {{ $namaKelas }}
                        </div>
                        @foreach($beds as $bed)
                            @php
                                $st = strtoupper($bed->Status);
                                $badge = match($st) {
                                    'KOSONG'  => ['success', 'Kosong'],
                                    'BOOKING' => ['warning', 'Booking'],
                                    'ISI'     => ['danger',  'Terisi'],
                                    default   => ['secondary', $bed->Status],
                                };
                            @endphp
                            <div class="bed-tile bed-{{ $st }} d-flex justify-content-between align-items-center">
                                <div>
                                    <strong style="font-size:.8rem">{{ $bed->ruang?->Nama_RuangM ?? $bed->Kode_Ruang }}</strong>
                                    <small class="text-muted ms-1">({{ $bed->Kode_Ruang }})</small>
                                    @if($st === 'ISI' && $bed->No_MR)
                                        <div style="font-size:.7rem" class="text-muted">No.MR: {{ $bed->No_MR }}</div>
                                    @endif
                                </div>
                                <span class="badge bg-{{ $badge[0] }} lbl">{{ $badge[1] }}</span>
                            </div>
                        @endforeach
                    @endforeach

                    {{-- Summary --}}
                    <div class="row text-center border-top pt-2 mt-2 g-0">
                        <div class="col">
                            <div class="text-success fw-bold fs-6">{{ $semuaKamar->where('Status','KOSONG')->count() }}</div>
                            <div style="font-size:.68rem" class="text-muted">Kosong</div>
                        </div>
                        <div class="col">
                            <div class="text-warning fw-bold fs-6">{{ $semuaKamar->where('Status','BOOKING')->count() }}</div>
                            <div style="font-size:.68rem" class="text-muted">Booking</div>
                        </div>
                        <div class="col">
                            <div class="text-danger fw-bold fs-6">{{ $semuaKamar->where('Status','ISI')->count() }}</div>
                            <div style="font-size:.68rem" class="text-muted">Terisi</div>
                        </div>
                        <div class="col">
                            <div class="text-dark fw-bold fs-6">{{ $semuaKamar->count() }}</div>
                            <div style="font-size:.68rem" class="text-muted">Total</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pasien Di ICU --}}
            <div class="card shadow-sm card-stage stage-icu">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="ph-hdr text-dark"><i class="bi bi-heart-pulse me-1"></i>Pasien di ICU</span>
                    <span class="badge bg-dark rounded-pill">{{ $tahapDiIcu->count() }}</span>
                </div>
                <div class="card-body py-2 px-3">
                    @forelse($tahapDiIcu as $a)
                        <div class="patient-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="text-success me-1">●</span>
                                    <strong>{{ $a->pasien->Nama_Pasien }}</strong>
                                    <div class="text-muted" style="font-size:.75rem">
                                        Bed: <strong>{{ $a->bed?->ruang?->Nama_RuangM ?? $a->allocated_bed_id }}</strong><br>
                                        Kelas: {{ $a->required_bed_type }}
                                        &bull; No.Reg: {{ $a->No_Reg }}
                                    </div>
                                </div>
                                <form action="{{ route('icu.pulangkan', $a->id) }}" method="POST"
                                      onsubmit="return confirm('Pulangkan pasien ini? Bed akan dikosongkan kembali.')">
                                    @csrf
                                    <button class="btn btn-outline-danger btn-sm ms-2">
                                        <i class="bi bi-box-arrow-right"></i> Pulang
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state"><i class="bi bi-inbox me-1"></i>Belum ada pasien di ICU</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
