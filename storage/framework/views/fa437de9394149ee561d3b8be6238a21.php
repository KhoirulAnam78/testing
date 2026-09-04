<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="description"
        content="SIAKAD pembelajaran blok Fakultas Kedokteran UIN Sulthan Thaha Saifuddin Jambi: semester, blok, kelas, kelompok belajar, jadwal dosen, presensi, logbook, dan rekap nilai akhir." />

    <title>Sistem Blok — SIAKAD Fakultas Kedokteran UIN Jambi</title>

    <link rel="shortcut icon" href="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,500;7..72,600;7..72,700&family=Archivo:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">

    <link href="<?php echo e(asset('assets/css/bootstrap.min.css')); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo e(asset('assets/css/icons.min.css')); ?>" rel="stylesheet" type="text/css" />

    <style>
        :root {
            --ink: #12251d;
            --ink-soft: #3d5049;
            --muted: #6c7c74;
            --green: #047857;
            --green-mid: #0f766e;
            --green-deep: #05392c;
            --line: #dde7e2;
            --line-firm: #c3d4cc;
            --paper: #f6faf8;
            --surface: #ffffff;

            --font-display: 'Literata', Georgia, 'Times New Roman', serif;
            --font-body: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, 'Cascadia Mono', Consolas, monospace;

            --wrap: 1140px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font-body);
            font-size: 1rem;
            line-height: 1.7;
            color: var(--ink);
            background: var(--paper);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1,
        h2,
        h3 {
            font-family: var(--font-display);
            font-weight: 600;
            letter-spacing: -.015em;
            line-height: 1.18;
            color: var(--ink);
            margin: 0;
        }

        p {
            margin: 0;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            max-width: 100%;
        }

        a:focus-visible,
        .act:focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 3px;
            border-radius: 3px;
        }

        .wrap {
            width: 100%;
            max-width: var(--wrap);
            margin-inline: auto;
            padding-inline: 22px;
        }

        .skip {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 60;
            background: var(--green-deep);
            color: #fff;
            padding: .6rem 1rem;
            font-size: .85rem;
        }

        .skip:focus {
            left: 12px;
            top: 12px;
        }

        /* ── shared type devices ─────────────────────────────── */
        .eyebrow {
            font-family: var(--font-mono);
            font-size: .69rem;
            font-weight: 500;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--green-mid);
        }

        .lede {
            font-size: 1.06rem;
            line-height: 1.75;
            color: var(--ink-soft);
            max-width: 54ch;
        }

        /* ── masthead ────────────────────────────────────────── */
        .strip {
            background: var(--green-deep);
            color: rgba(255, 255, 255, .82);
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .strip__inner {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem 1.25rem;
            justify-content: space-between;
            padding-block: .5rem;
        }

        .strip__inner span:last-child {
            color: rgba(255, 255, 255, .58);
        }

        .bar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, .94);
            backdrop-filter: saturate(150%) blur(8px);
            -webkit-backdrop-filter: saturate(150%) blur(8px);
            border-bottom: 1px solid var(--line);
            transition: border-color .2s ease;
        }

        .bar.is-scrolled {
            border-bottom-color: var(--line-firm);
        }

        .bar__inner {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding-block: .85rem;
        }

        .mark {
            display: flex;
            align-items: center;
            gap: .7rem;
        }

        .mark img {
            width: 38px;
            height: 38px;
        }

        .mark strong {
            display: block;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.02rem;
            letter-spacing: -.02em;
            line-height: 1.1;
            color: var(--ink);
        }

        .mark span {
            display: block;
            font-family: var(--font-mono);
            font-size: .63rem;
            letter-spacing: .11em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .bar__nav {
            display: flex;
            gap: 1.5rem;
            margin-left: auto;
            font-family: var(--font-mono);
            font-size: .71rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .bar__nav a {
            padding-block: .35rem;
            border-bottom: 1px solid transparent;
            transition: border-color .18s ease, color .18s ease;
        }

        .bar__nav a:hover {
            color: var(--green);
            border-bottom-color: var(--green);
        }

        .bar__act {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-left: auto;
        }

        /* ── actions ─────────────────────────────────────────── */
        .act {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-family: var(--font-body);
            font-size: .9rem;
            font-weight: 500;
            padding: .58rem 1.05rem;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color .18s ease, color .18s ease, border-color .18s ease;
        }

        .act i {
            font-size: 1.05em;
            line-height: 1;
        }

        .act--solid {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .act--solid:hover {
            background: var(--green-deep);
            border-color: var(--green-deep);
        }

        .act--line {
            border-color: var(--line-firm);
            color: var(--ink);
        }

        .act--line:hover {
            border-color: var(--green);
            color: var(--green);
        }

        .act--light {
            background: #fff;
            border-color: #fff;
            color: var(--green-deep);
        }

        .act--light:hover {
            background: rgba(255, 255, 255, .86);
            border-color: rgba(255, 255, 255, .86);
        }

        .act--lg {
            font-size: .96rem;
            padding: .75rem 1.35rem;
        }

        /* ── hero ────────────────────────────────────────────── */
        .hero {
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }

        .hero__grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
            align-items: center;
            gap: 4rem;
            padding-block: 84px 76px;
        }

        .hero__title {
            margin-top: 1.1rem;
            font-size: clamp(2.1rem, 4.1vw, 3.35rem);
            font-weight: 700;
            line-height: 1.07;
            letter-spacing: -.028em;
            max-width: 20ch;
        }

        .hero__lede {
            margin-top: 1.4rem;
        }

        .hero__act {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            margin-top: 2rem;
        }

        .facts {
            display: grid;
            gap: 0;
            margin: 2.6rem 0 0;
            border-top: 1px solid var(--line);
            max-width: 46rem;
        }

        .facts>div {
            display: grid;
            grid-template-columns: 10.5rem minmax(0, 1fr);
            gap: 1rem;
            padding-block: .8rem;
            border-bottom: 1px solid var(--line);
        }

        .facts dt {
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .13em;
            text-transform: uppercase;
            color: var(--muted);
            padding-top: .2rem;
        }

        .facts dd {
            margin: 0;
            font-size: .93rem;
            color: var(--ink-soft);
        }

        .crest {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 1;
        }

        .crest::before {
            content: "";
            position: absolute;
            inset: 4%;
            border: 1px solid var(--line);
            border-radius: 50%;
        }

        .crest img {
            position: relative;
            width: 68%;
            height: auto;
        }

        /* ── sections ────────────────────────────────────────── */
        .section {
            padding-block: 76px;
        }

        .section--surface {
            background: var(--surface);
            border-block: 1px solid var(--line);
        }

        .head {
            max-width: 44rem;
            margin-bottom: 2.6rem;
        }

        .head h2 {
            margin-top: .7rem;
            font-size: clamp(1.55rem, 2.5vw, 2.05rem);
        }

        .head p {
            margin-top: .7rem;
            color: var(--muted);
            font-size: .97rem;
            max-width: 52ch;
        }

        /* ── modul: shared-hairline matrix ───────────────────── */
        .matrix {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1px;
            background: var(--line);
            border: 1px solid var(--line);
            border-radius: 6px;
            overflow: hidden;
        }

        .cell {
            background: var(--surface);
            padding: 30px 28px;
            transition: background-color .18s ease;
        }

        .cell:hover {
            background: #fbfdfc;
        }

        .cell h3 {
            display: flex;
            align-items: center;
            gap: .55rem;
            font-size: 1.08rem;
            font-weight: 600;
        }

        .cell h3 i {
            font-size: 1.15rem;
            color: var(--green-mid);
        }

        .cell p {
            margin-top: .6rem;
            font-size: .93rem;
            line-height: 1.7;
            color: var(--muted);
        }

        /* ── alur: entity rail (signature) ───────────────────── */
        .rail {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .rail__step {
            position: relative;
            padding: 30px 24px 0 0;
            border-top: 1px solid var(--line-firm);
        }

        .rail__step::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 9px;
            height: 9px;
            background: var(--surface);
            border: 1px solid var(--green);
            transform: translate(-50%, -50%) rotate(45deg);
        }

        .rail__step:first-child::after {
            background: var(--green);
        }

        .rail__code {
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: var(--green);
        }

        .rail__step h3 {
            margin-top: .55rem;
            font-size: 1.02rem;
        }

        .rail__step p {
            margin-top: .5rem;
            font-size: .9rem;
            line-height: 1.65;
            color: var(--muted);
        }

        /* ── peran: hairline-divided columns ─────────────────── */
        .roles {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .role {
            padding: 4px 30px;
        }

        .role:first-child {
            padding-left: 0;
        }

        .role:last-child {
            padding-right: 0;
        }

        .role+.role {
            border-left: 1px solid var(--line);
        }

        .role__slug {
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .12em;
            color: var(--muted);
        }

        .role__name {
            margin-top: .4rem;
            font-size: 1.22rem;
            font-weight: 600;
        }

        .role p {
            margin-top: .55rem;
            font-size: .93rem;
            color: var(--muted);
        }

        .role ul {
            list-style: none;
            margin: 1.3rem 0 0;
            padding: 0;
        }

        .role li {
            padding-block: .6rem;
            border-top: 1px dashed var(--line-firm);
            font-size: .89rem;
            color: var(--ink-soft);
        }

        /* ── closing band ────────────────────────────────────── */
        .band {
            background: var(--green-deep);
            color: #fff;
        }

        .band__inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1.75rem;
            padding-block: 56px;
        }

        .band h2 {
            color: #fff;
            font-size: clamp(1.4rem, 2.3vw, 1.85rem);
            max-width: 26ch;
        }

        .band p {
            margin-top: .6rem;
            color: rgba(255, 255, 255, .72);
            font-size: .95rem;
            max-width: 46ch;
        }

        /* ── footer ──────────────────────────────────────────── */
        .foot {
            background: var(--surface);
            border-top: 1px solid var(--line);
            padding-block: 48px 30px;
            font-size: .9rem;
            color: var(--muted);
        }

        .foot__grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) repeat(3, minmax(0, 1fr));
            gap: 2.5rem;
        }

        .foot h3 {
            font-family: var(--font-mono);
            font-size: .68rem;
            font-weight: 500;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--ink);
            margin-bottom: .9rem;
        }

        .foot ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .foot li {
            padding-block: .28rem;
        }

        .foot a:hover {
            color: var(--green);
        }

        .foot__org {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1rem;
            color: var(--ink);
            line-height: 1.4;
        }

        .foot__note {
            margin-top: .8rem;
            max-width: 40ch;
        }

        .foot__base {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: .5rem;
            margin-top: 38px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .06em;
            color: var(--muted);
        }

        /* ── page-load sequence ──────────────────────────────── */
        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .rise {
            animation: rise .65s cubic-bezier(.22, .61, .36, 1) both;
        }

        .rise:nth-child(2) {
            animation-delay: .07s;
        }

        .rise:nth-child(3) {
            animation-delay: .14s;
        }

        .rise:nth-child(4) {
            animation-delay: .21s;
        }

        .rise:nth-child(5) {
            animation-delay: .28s;
        }

        .crest {
            animation: rise .8s cubic-bezier(.22, .61, .36, 1) both .18s;
        }

        @media (prefers-reduced-motion: reduce) {

            html {
                scroll-behavior: auto;
            }

            .rise,
            .crest {
                animation: none;
            }
        }

        /* ── responsive ──────────────────────────────────────── */
        @media (max-width: 991.98px) {
            .hero__grid {
                grid-template-columns: 1fr;
                gap: 2.25rem;
                padding-block: 48px 56px;
            }

            .crest {
                order: -1;
                aspect-ratio: auto;
                justify-content: flex-start;
                animation-delay: 0s;
            }

            .crest::before {
                display: none;
            }

            .crest img {
                width: 96px;
            }

            .hero__title {
                max-width: none;
            }

            .bar__nav {
                display: none;
            }

            .roles {
                grid-template-columns: 1fr;
            }

            .role {
                padding: 0;
            }

            .role+.role {
                border-left: 0;
                border-top: 1px solid var(--line-firm);
                margin-top: 2rem;
                padding-top: 2rem;
            }

            .foot__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 899.98px) {
            .rail {
                grid-template-columns: 1fr;
            }

            .rail__step {
                border-top: 0;
                border-left: 1px solid var(--line-firm);
                padding: 0 0 26px 26px;
            }

            .rail__step::after {
                top: 10px;
            }

            .rail__step:last-child {
                border-left-color: transparent;
                padding-bottom: 0;
            }
        }

        @media (max-width: 767.98px) {
            .section {
                padding-block: 52px;
            }

            .matrix {
                grid-template-columns: 1fr;
            }

            .facts>div {
                grid-template-columns: 1fr;
                gap: .1rem;
            }

            .foot__grid {
                grid-template-columns: 1fr;
                gap: 1.75rem;
            }

            .band__inner {
                padding-block: 42px;
            }
        }
    </style>
</head>

<body>
    <a class="skip" href="#utama">Lewati ke konten</a>

    
    <div class="strip">
        <div class="wrap strip__inner">
            <span>Universitas Islam Negeri Sulthan Thaha Saifuddin Jambi</span>
            <span>Fakultas Kedokteran &middot; Pendidikan Dokter</span>
        </div>
    </div>

    <div class="bar" id="bar">
        <div class="wrap bar__inner">
            <a class="mark" href="<?php echo e(url('/')); ?>">
                <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>" alt="">
                <span>
                    <strong>Sistem Blok</strong>
                    <span>SIAKAD Fakultas Kedokteran</span>
                </span>
            </a>

            <nav class="bar__nav" aria-label="Bagian halaman">
                <a href="#modul">Modul</a>
                <a href="#alur">Alur</a>
                <a href="#peran">Peran</a>
            </nav>

            <div class="bar__act">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('login')): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(url('/dashboard')); ?>" class="act act--solid">
                            <i class="ri-dashboard-line"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" class="act act--line">
                            <i class="ri-login-circle-line"></i> Login
                        </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('register')): ?>
                            <a href="<?php echo e(route('register')); ?>" class="act act--solid">Daftar</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    <main id="utama">
        
        <section class="hero">
            <div class="wrap hero__grid">
                <div class="hero__body">
                    <p class="eyebrow rise">SIAKAD &middot; Pembelajaran berbasis blok</p>

                    <h1 class="hero__title rise">
                        Menjalankan pembelajaran blok, dari susunan materi sampai nilai akhir.
                    </h1>

                    <p class="lede hero__lede rise">
                        Satu sistem untuk menyusun blok, membagi kelas dan kelompok belajar, menjadwalkan dosen,
                        mencatat presensi serta logbook, lalu merekap nilai akhir.
                    </p>

                    <div class="hero__act rise">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                            <a href="<?php echo e(url('/dashboard')); ?>" class="act act--solid act--lg">
                                <i class="ri-dashboard-2-line"></i> Buka dashboard
                            </a>
                        <?php else: ?>
                            <a href="<?php echo e(route('login')); ?>" class="act act--solid act--lg">
                                <i class="ri-login-box-line"></i> Login ke sistem
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <a href="#modul" class="act act--line act--lg">Lihat modul</a>
                    </div>
                </div>

                <div class="crest">
                    <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>"
                        alt="Lambang Universitas Islam Negeri Sulthan Thaha Saifuddin Jambi">
                </div>
            </div>
        </section>

        
        <section class="section" id="modul">
            <div class="wrap">
                <div class="head">
                    <p class="eyebrow">Modul</p>
                    <h2>Yang dikelola di dalam sistem</h2>
                    <p>Empat kelompok modul yang saling memakai data satu sama lain, dari data dasar akademik
                        sampai rekap nilai.</p>
                </div>

                <div class="matrix">
                    <article class="cell">
                        <h3><i class="ri-database-2-line"></i> Master akademik</h3>
                        <p>Prodi, dosen, mahasiswa, semester, dan mata kuliah — data dasar yang dipakai seluruh
                            modul lain.</p>
                    </article>
                    <article class="cell">
                        <h3><i class="ri-mind-map"></i> Penyusunan blok</h3>
                        <p>Aturan jenis kegiatan per blok, materi blok, dan materi rinci sebagai kerangka
                            pembelajaran.</p>
                    </article>
                    <article class="cell">
                        <h3><i class="ri-team-line"></i> Kelas dan kelompok</h3>
                        <p>Peserta kelas, kelompok belajar per kegiatan, dan dosen pengampu tiap pertemuan.</p>
                    </article>
                    <article class="cell">
                        <h3><i class="ri-file-list-3-line"></i> Presensi dan nilai</h3>
                        <p>Catatan kehadiran, logbook kegiatan, dan rekap nilai akhir blok per mahasiswa.</p>
                    </article>
                </div>
            </div>
        </section>

        
        <section class="section section--surface" id="alur">
            <div class="wrap">
                <div class="head">
                    <p class="eyebrow">Alur</p>
                    <h2>Urutan kerja satu blok</h2>
                    <p>Tiap tahap memakai data tahap sebelumnya, jadi urutan pengerjaannya menentukan.</p>
                </div>

                <ol class="rail">
                    <li class="rail__step">
                        <span class="rail__code">Semester</span>
                        <h3>Tetapkan semester aktif</h3>
                        <p>Aktifkan semester berjalan dan lengkapi prodi, dosen, mahasiswa, serta mata kuliah.</p>
                    </li>
                    <li class="rail__step">
                        <span class="rail__code">Blok</span>
                        <h3>Susun struktur blok</h3>
                        <p>Tentukan aturan jenis kegiatan, jumlah pertemuan, lalu rinci materi tiap kegiatan.</p>
                    </li>
                    <li class="rail__step">
                        <span class="rail__code">Kelas</span>
                        <h3>Bentuk kelas dan kelompok</h3>
                        <p>Masukkan peserta kelas, lalu bagi mereka ke kelompok belajar tiap kegiatan blok.</p>
                    </li>
                    <li class="rail__step">
                        <span class="rail__code">Pertemuan</span>
                        <h3>Petakan jadwal dan dosen</h3>
                        <p>Pasangkan materi ke pertemuan, tetapkan dosen pengampu, jam, dan ruang per kelompok.</p>
                    </li>
                    <li class="rail__step">
                        <span class="rail__code">Nilai</span>
                        <h3>Catat dan rekap hasil</h3>
                        <p>Isi presensi dan logbook tiap pertemuan, lalu tutup blok dengan rekap nilai akhir.</p>
                    </li>
                </ol>
            </div>
        </section>

        
        <section class="section" id="peran">
            <div class="wrap">
                <div class="head">
                    <p class="eyebrow">Peran</p>
                    <h2>Siapa mengerjakan apa</h2>
                    <p>Menu dan tindakan yang muncul mengikuti peran akun, bukan sekadar disembunyikan dari
                        tampilan.</p>
                </div>

                <div class="roles">
                    <article class="role">
                        <span class="role__slug">pengelola</span>
                        <h3 class="role__name">Pengelola akademik</h3>
                        <p>Menyiapkan data dan struktur yang nantinya dipakai dosen dan mahasiswa.</p>
                        <ul>
                            <li>Kelola prodi, dosen, mahasiswa, dan mata kuliah</li>
                            <li>Tetapkan semester aktif, blok, dan kelas</li>
                            <li>Atur pengguna, peran, dan menu sistem</li>
                        </ul>
                    </article>
                    <article class="role">
                        <span class="role__slug">dosen</span>
                        <h3 class="role__name">Dosen pengampu</h3>
                        <p>Menjalankan pertemuan sesuai jadwal dan mencatat hasilnya di hari yang sama.</p>
                        <ul>
                            <li>Lihat jadwal mengajar per kelompok</li>
                            <li>Isi presensi dan logbook pertemuan</li>
                            <li>Masukkan nilai kegiatan yang diampu</li>
                        </ul>
                    </article>
                    <article class="role">
                        <span class="role__slug">mahasiswa</span>
                        <h3 class="role__name">Mahasiswa</h3>
                        <p>Melihat posisinya di dalam blok yang sedang berjalan tanpa bertanya ke bagian akademik.</p>
                        <ul>
                            <li>Lihat blok, jadwal, dan kelompoknya</li>
                            <li>Pantau rekap kehadiran sendiri</li>
                            <li>Lihat nilai akhir setelah blok ditutup</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        
        <section class="band">
            <div class="wrap band__inner">
                <div>
                    <h2>Masuk untuk mulai mengelola blok.</h2>
                    <p>Gunakan akun yang diberikan pengelola akademik. Hak akses menyesuaikan peran akun Anda.</p>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(url('/dashboard')); ?>" class="act act--light act--lg">
                        <i class="ri-dashboard-2-line"></i> Buka dashboard
                    </a>
                <?php else: ?>
                    <a href="<?php echo e(route('login')); ?>" class="act act--light act--lg">
                        <i class="ri-login-box-line"></i> Login ke sistem
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>
    </main>

    
    <footer class="foot">
        <div class="wrap">
            <div class="foot__grid">
                <div>
                    <p class="foot__org">Fakultas Kedokteran<br>UIN Sulthan Thaha Saifuddin Jambi</p>
                    <p class="foot__note">Sistem informasi akademik untuk pembelajaran kedokteran berbasis blok.</p>
                </div>
                <div>
                    <h3>Halaman</h3>
                    <ul>
                        <li><a href="#modul">Modul</a></li>
                        <li><a href="#alur">Alur</a></li>
                        <li><a href="#peran">Peran</a></li>
                    </ul>
                </div>
                <div>
                    <h3>Akses</h3>
                    <ul>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                            <li><a href="<?php echo e(url('/dashboard')); ?>">Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo e(route('login')); ?>">Login</a></li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('register')): ?>
                                <li><a href="<?php echo e(route('register')); ?>">Daftar</a></li>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3>Kontak</h3>
                    <ul>
                        <li>Jambi, Indonesia</li>
                        <li><a href="https://www.uinjambi.ac.id" rel="noopener">uinjambi.ac.id</a></li>
                    </ul>
                </div>
            </div>

            <div class="foot__base">
                <span><?php echo e(date('Y')); ?> &copy; Fakultas Kedokteran UIN Jambi</span>
                <span>Laravel <?php echo e(Illuminate\Foundation\Application::VERSION); ?> &middot; PHP <?php echo e(PHP_VERSION); ?></span>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.getElementById('bar');
            if (!bar) {
                return;
            }
            const onScroll = () => bar.classList.toggle('is-scrolled', window.scrollY > 8);
            onScroll();
            window.addEventListener('scroll', onScroll, {
                passive: true
            });
        });
    </script>
</body>

</html>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views\welcome.blade.php ENDPATH**/ ?>