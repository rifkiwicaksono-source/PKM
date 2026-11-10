@extends('layouts.app')
@section('title', 'Dashboard')

@php
    use App\Support\Fmt;
    $me = auth()->user();
    $trend = function ($pct, bool $goodWhenUp = true) {
        if ($pct === null) return ['–', 'chip-muted'];
        $up = $pct >= 0;
        return [($up ? '+' : '−').abs($pct).'%', ($up === $goodWhenUp) ? 'chip-good' : 'chip-bad'];
    };
    // Grafik
    $max = max(1, collect($series)->max(fn ($m) => max($m['income'], $m['expense'])));
    $nice = (int) (ceil($max / 2000000) * 2000000);
    $H = 160; $base = 180;
@endphp

@section('content')
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ Fmt::date(Fmt::today(), 'l, j F Y') }}</div>
            <h1 class="page-title">{{ Fmt::greeting() }}, {{ $me->name }}</h1>
            <p class="page-sub">Ringkasan keuangan dan produksi {{ \App\Models\Setting::get('store_name') }}.</p>
        </div>
        <div class="actions">
            <nav class="seg" aria-label="Periode">
                @foreach (['hari' => 'Hari ini', 'minggu' => 'Minggu ini', 'bulan' => 'Bulan ini'] as $k => $l)
                    <a href="{{ route('dashboard', ['periode' => $k]) }}" class="{{ $period === $k ? 'on' : '' }}" @if ($period === $k) aria-current="page" @endif>{{ $l }}</a>
                @endforeach
            </nav>
            <a href="{{ route('orders.create') }}" class="btn btn-primary">@include('partials.icon', ['name' => 'plus', 'stroke' => 2.2])Pesanan Baru</a>
        </div>
    </header>

    <section class="grid-kpi" aria-label="Ringkasan">
        @php [$t, $c] = $trend($changes['income']); @endphp
        <div class="kpi">
            <div class="k">Pendapatan <span class="chip {{ $c }}">{{ $t }}</span></div>
            <div class="v">{{ Fmt::rp($now['income']) }}</div>
            <div class="d">{{ $now['count_in'] }} transaksi · vs {{ $compareLabel }}</div>
        </div>
        @php [$t, $c] = $trend($changes['expense'], false); @endphp
        <div class="kpi">
            <div class="k">Pengeluaran <span class="chip {{ $c }}">{{ $t }}</span></div>
            <div class="v">{{ Fmt::rp($now['expense']) }}</div>
            <div class="d">{{ $now['count_out'] }} transaksi · vs {{ $compareLabel }}</div>
        </div>
        @php [$t, $c] = $trend($changes['profit']); @endphp
        <div class="kpi">
            <div class="k">Laba bersih <span class="chip {{ $c }}">{{ $t }}</span></div>
            <div class="v {{ $now['profit'] < 0 ? 'text-out' : '' }}">{{ Fmt::rp($now['profit']) }}</div>
            <div class="d">Margin {{ str_replace('.', ',', $now['margin']) }}%</div>
        </div>
        <a class="kpi" href="{{ route('orders.index', ['tampilan' => 'tabel', 'bayar' => 'belum-lunas']) }}" style="text-decoration: none; color: inherit">
            <div class="k">Piutang belum lunas <span class="chip chip-warn">{{ $receivables['count'] }} pesanan</span></div>
            <div class="v">{{ Fmt::rp($receivables['amount']) }}</div>
            <div class="d">Saldo kas saat ini {{ Fmt::rp($balance) }}</div>
        </a>
    </section>

    <div class="split">
        <section class="card chart col-main" aria-labelledby="cf-h" style="gap: 0">
            <div class="card-head" style="margin-bottom: 0">
                <div>
                    <h2 id="cf-h" class="card-title">Pendapatan vs Pengeluaran 6 Bulan</h2>
                    <div class="small muted">Dalam juta rupiah · bulan berjalan s.d. hari ini</div>
                </div>
                <div class="legend"><span><i style="background: var(--navy)"></i>Pendapatan</span><span><i style="background: var(--yellow-bar)"></i>Pengeluaran</span></div>
            </div>
            @php
                $desc = collect($series)->map(fn ($m) => $m['full'].': pendapatan '.Fmt::juta($m['income']).' juta, pengeluaran '.Fmt::juta($m['expense']).' juta')->implode('; ');
            @endphp
            <svg role="img" aria-label="{{ $desc }}" viewBox="0 0 640 214">
                @for ($i = 0; $i <= 4; $i++)
                    @php $y = $base - $H * $i / 4; @endphp
                    <line x1="44" y1="{{ $y }}" x2="640" y2="{{ $y }}" stroke="{{ $i === 0 ? '#C9CEDA' : '#EEF0F5' }}"/>
                    <text x="36" y="{{ $y + 4 }}" font-size="11" fill="#6B7386" text-anchor="end" font-family="Manrope, sans-serif">{{ Fmt::juta($nice * $i / 4) }}</text>
                @endfor
                @foreach ($series as $i => $m)
                    @php
                        $x = 60 + $i * 98;
                        $hi = $m['income'] / $nice * $H;
                        $he = $m['expense'] / $nice * $H;
                    @endphp
                    <rect x="{{ $x }}" y="{{ $base - $hi }}" width="30" height="{{ max(0, $hi) }}" rx="4" fill="#087F8C"><title>{{ $m['full'] }} · pendapatan {{ Fmt::rp($m['income']) }}</title></rect>
                    <rect x="{{ $x + 34 }}" y="{{ $base - $he }}" width="30" height="{{ max(0, $he) }}" rx="4" fill="#F5A742"><title>{{ $m['full'] }} · pengeluaran {{ Fmt::rp($m['expense']) }}</title></rect>
                    <text x="{{ $x + 32 }}" y="{{ $base - $hi - 6 }}" font-size="11" font-weight="700" fill="#087F8C" text-anchor="middle" font-family="Manrope, sans-serif">{{ Fmt::juta($m['income']) }}</text>
                    <text x="{{ $x + 32 }}" y="202" font-size="12" font-weight="600" fill="#3D4559" text-anchor="middle" font-family="Manrope, sans-serif">{{ $m['label'] }}{{ $m['partial'] ? '*' : '' }}</text>
                @endforeach
            </svg>
        </section>

        <section class="card col-side" aria-labelledby="q-h" style="gap: 0">
            <div class="card-head" style="margin-bottom: 8px">
                <h2 id="q-h" class="card-title">Kapasitas Mesin Hari Ini</h2>
                <a href="{{ route('production.index') }}" class="card-link">Antrean</a>
            </div>
            <div style="display: flex; align-items: baseline; gap: 6px">
                <span class="num" style="font-size: 30px; font-weight: 800">{{ Fmt::num($outlook['load']) }}</span>
                <span class="muted small">/ {{ Fmt::num($outlook['capacity']) }} pcs terjadwal</span>
            </div>
            <div class="bar {{ $outlook['percent'] >= 90 ? 'warn' : '' }}" style="margin-top: 10px"><div style="width: {{ $outlook['percent'] }}%"></div></div>
            <div class="xsmall muted" style="margin-top: 6px">
                {{ $outlook['left'] > 0 ? 'Sisa '.Fmt::num($outlook['left']).' pcs. Pesanan di atas itu otomatis dijadwalkan ke hari kerja berikutnya.' : 'Kapasitas hari ini penuh. Pesanan baru dijadwalkan ke hari kerja berikutnya.' }}
            </div>
            @if ($jobs->isEmpty())
                <p class="small muted" style="margin: 16px 0 0">Tidak ada pesanan di antrean.</p>
            @else
                <ol class="qlist" style="margin-top: 16px">
                    @foreach ($jobs as $i => $o)
                        <li class="{{ $o->status === 'produksi' ? 'run' : '' }}">
                            <a href="{{ route('orders.show', $o) }}">
                                <span class="qno num">{{ $i + 1 }}</span>
                                <span style="flex: 1; min-width: 0">
                                    <span class="t">{{ $o->customer->name }}</span>
                                    <span class="m">{{ Fmt::num($o->total_qty) }} pcs · {{ $o->status === 'produksi' ? 'mulai '.Fmt::date($o->started_at, 'H.i') : 'jadwal '.Fmt::date($o->scheduled_date, 'D j M') }}</span>
                                </span>
                                @if ($o->status === 'produksi')<span class="chip chip-dark">Dicetak</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <div class="split">
        <section class="card col-side" aria-labelledby="ap-h" style="gap: 0">
            <div class="card-head" style="margin-bottom: 4px">
                <h2 id="ap-h" class="card-title">{{ $me->isOwner() ? 'Menunggu Persetujuan' : 'Pengajuan Saya' }}</h2>
                @if ($pending->count())<span class="chip chip-bad">{{ $pending->count() }}</span>@endif
            </div>
            <p class="small muted" style="margin: 0 0 14px">Pengeluaran di atas {{ Fmt::rp(\App\Services\CashService::approvalLimit()) }} wajib disetujui Owner sebelum masuk buku kas.</p>
            @forelse ($pending as $p)
                <div class="approve-card">
                    <div style="display: flex; justify-content: space-between; gap: 10px">
                        <span class="strong small" style="font-size: 14px">{{ $p->description }}</span>
                        <span class="num strong text-out">{{ Fmt::rp($p->amount) }}</span>
                    </div>
                    <div class="xsmall muted" style="margin-top: 4px">{{ $p->code }} · {{ $p->category }} · diajukan {{ $p->creator?->name }} · {{ Fmt::date($p->created_at, 'j M, H.i') }}</div>
                    @if ($me->isOwner())
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px">
                            <form method="post" action="{{ route('cash.approve', $p) }}" style="flex: 1">@csrf<button class="btn btn-primary btn-sm btn-block" type="submit">Setujui</button></form>
                            <form method="post" action="{{ route('cash.reject', $p) }}" style="flex: 1" data-prompt="Alasan menolak {{ $p->code }}:" data-prompt-field="reason">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-block" type="submit">Tolak</button></form>
                            @if ($p->receipt_path)<a class="btn btn-ghost btn-sm" href="{{ route('cash.receipt', $p) }}" target="_blank" rel="noopener">Lihat nota</a>@endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="flash" style="font-size: 13px"><span class="ic">@include('partials.icon', ['name' => 'check', 'size' => 14, 'stroke' => 2.6])</span>Tidak ada pengeluaran yang menunggu persetujuan.</div>
            @endforelse
        </section>

        <section class="card flush col-main" aria-labelledby="tx-h">
            <div class="card-head" style="padding: 12px 12px 0">
                <h2 id="tx-h" class="card-title">Transaksi Terakhir</h2>
                <a href="{{ route('cash.index') }}" class="card-link">Buku kas</a>
            </div>
            <div class="table-wrap">
                <table class="table" style="min-width: 560px">
                    <thead><tr><th scope="col">No. Bukti</th><th scope="col">Keterangan</th><th scope="col">Dicatat oleh</th><th scope="col" class="right">Jumlah</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $t)
                            <tr class="{{ $t->status === 'batal' ? 'row-void' : ($t->status === 'menunggu' ? 'row-wait' : '') }}">
                                <td><div class="num t" style="color: var(--ink-3)">{{ $t->code }}</div><div class="sub">{{ Fmt::date($t->date, 'j M') }}</div></td>
                                <td><div class="t">{{ $t->description }}</div><div class="sub">{{ $t->category }} @if ($t->status !== 'tercatat') · {{ $t->statusLabel() }} @endif</div></td>
                                <td class="muted small">{{ $t->creator?->signature() }}</td>
                                <td class="right num strong amt {{ $t->isIn() ? 'text-in' : 'text-out' }}">{{ $t->signedAmount() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
