<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta content="SIAKAD pembelajaran blok Fakultas Kedokteran UIN Sulthan Thaha Saifuddin Jambi" name="description" />
    <meta content="noindex, nofollow" name="robots" />

    <title>Sistem Blok — SIAKAD Fakultas Kedokteran UIN Jambi</title>

    <link data-navigate-once rel="shortcut icon" href="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>">

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
            --danger: #b42318;

            --font-display: 'Literata', Georgia, 'Times New Roman', serif;
            --font-body: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, 'Cascadia Mono', Consolas, monospace;

            /* retint Bootstrap's primary utilities (Velzon build uses the vz- prefix) */
            --vz-primary: #047857;
            --vz-primary-rgb: 4, 120, 87;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
        h3,
        h4 {
            font-family: var(--font-display);
            font-weight: 600;
            letter-spacing: -.02em;
            line-height: 1.2;
            color: var(--ink);
            margin: 0;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 3px;
            border-radius: 3px;
        }

        .wrap {
            width: 100%;
            max-width: 1140px;
            margin-inline: auto;
            padding-inline: 22px;
        }

        .eyebrow {
            font-family: var(--font-mono);
            font-size: .69rem;
            font-weight: 500;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--green-mid);
            margin: 0;
        }

        /* ── institutional masthead (shared with the landing page) ── */
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
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }

        .bar__inner {
            display: flex;
            align-items: center;
            gap: 1.25rem;
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

        .bar__back {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-family: var(--font-mono);
            font-size: .69rem;
            letter-spacing: .11em;
            text-transform: uppercase;
            color: var(--ink-soft);
            padding-block: .35rem;
            border-bottom: 1px solid transparent;
            transition: color .18s ease, border-color .18s ease;
        }

        .bar__back:hover {
            color: var(--green);
            border-bottom-color: var(--green);
        }

        /* ── the page body ── */
        .auth__main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-block: 52px;
        }

        /* Livewire page roots are plain divs — let them span the centring track */
        .auth__main>* {
            width: 100%;
        }

        .auth__foot {
            border-top: 1px solid var(--line);
            background: var(--surface);
            padding-block: 18px;
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .06em;
            color: var(--muted);
            text-align: center;
        }

        /* ── the sign-in sheet ── */
        .sheet-wrap {
            width: 100%;
            max-width: 476px;
            margin-inline: auto;
            padding-inline: 22px;
        }

        .sheet {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 6px;
        }

        .sheet__band {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .7rem 26px;
            border-bottom: 1px solid var(--line);
            font-family: var(--font-mono);
            font-size: .66rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .sheet__band b {
            font-weight: 500;
            color: var(--green);
        }

        .sheet__body {
            padding: 28px 26px 30px;
        }

        .sheet__title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .sheet__note {
            margin: .5rem 0 0;
            font-size: .93rem;
            color: var(--muted);
        }

        .sheet__form {
            margin-top: 1.6rem;
        }

        .sheet__foot {
            margin: 1.1rem 0 0;
            font-size: .85rem;
            line-height: 1.6;
            color: var(--muted);
            text-align: center;
        }

        /* ── forms ── */
        .form-label {
            display: block;
            font-family: var(--font-mono);
            font-size: .68rem;
            font-weight: 500;
            letter-spacing: .13em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: .4rem;
        }

        .label-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .4rem;
        }

        .label-row .form-label {
            margin-bottom: 0;
        }

        .link-quiet {
            font-size: .82rem;
            color: var(--muted);
            border-bottom: 1px solid transparent;
            transition: color .18s ease, border-color .18s ease;
        }

        .link-quiet:hover {
            color: var(--green);
            border-bottom-color: var(--green);
        }

        .form-control {
            display: block;
            width: 100%;
            font-family: var(--font-body);
            font-size: .95rem;
            line-height: 1.5;
            color: var(--ink);
            background-color: var(--surface);
            border: 1px solid var(--line-firm);
            border-radius: 4px;
            padding: .62rem .8rem;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .form-control::placeholder {
            color: #9aa8a1;
        }

        .form-control:focus {
            outline: 0;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, .13);
        }

        .form-control.is-invalid,
        .form-control:invalid {
            border-color: var(--danger);
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(180, 35, 24, .13);
        }

        .field-error {
            margin: .45rem 0 0;
            padding: 0;
            list-style: none;
            font-size: .84rem;
            line-height: 1.5;
            color: var(--danger);
        }

        /* password reveal */
        .pw {
            position: relative;
        }

        .pw .form-control {
            padding-right: 2.9rem;
        }

        .pw__toggle {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            width: 2.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: none;
            border: 0;
            padding: 0;
            color: var(--muted);
            cursor: pointer;
            transition: color .18s ease;
        }

        .pw__toggle:hover {
            color: var(--green);
        }

        /* ── buttons ── */
        .btn {
            --vz-btn-border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            font-family: var(--font-body);
            font-size: .93rem;
            font-weight: 500;
            line-height: 1.5;
            padding: .62rem 1.1rem;
            border-radius: 4px;
            transition: background-color .18s ease, border-color .18s ease, color .18s ease;
        }

        .btn-primary {
            --vz-btn-bg: #047857;
            --vz-btn-border-color: #047857;
            --vz-btn-hover-bg: #05392c;
            --vz-btn-hover-border-color: #05392c;
            --vz-btn-active-bg: #05392c;
            --vz-btn-active-border-color: #05392c;
            --vz-btn-disabled-bg: #6f9a8b;
            --vz-btn-disabled-border-color: #6f9a8b;
            --vz-btn-focus-shadow-rgb: 4, 120, 87;
        }

        .btn-lg {
            font-size: .98rem;
            padding: .78rem 1.35rem;
        }

        /* ── compatibility shims for auth pages not yet reworked ── */
        .card {
            border-radius: 6px;
            background: var(--surface);
            box-shadow: 0 0 0 1px var(--line) !important;
        }

        .avatar-xs {
            height: 2rem;
            width: 2rem;
        }

        .avatar-sm {
            height: 3rem;
            width: 3rem;
        }

        .avatar-title {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            color: var(--green);
            background-color: var(--paper);
        }

        .fs-13 {
            font-size: .8125rem !important;
        }

        .fs-18 {
            font-size: 1.125rem !important;
        }

        .alert {
            border: 1px solid var(--line) !important;
            border-radius: 4px;
            font-size: .9rem;
        }

        .text-primary {
            color: var(--green) !important;
        }

        .bg-primary-subtle {
            background-color: var(--paper) !important;
        }

        @media (max-width: 575.98px) {
            .sheet__band {
                padding-inline: 20px;
            }

            .sheet__body {
                padding: 24px 20px 26px;
            }

            .bar__back span {
                display: none;
            }
        }
    </style>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body>
    <div class="strip">
        <div class="wrap strip__inner">
            <span>Universitas Islam Negeri Sulthan Thaha Saifuddin Jambi</span>
            <span>Fakultas Kedokteran &middot; Pendidikan Dokter</span>
        </div>
    </div>

    <div class="bar">
        <div class="wrap bar__inner">
            <a class="mark" href="<?php echo e(url('/')); ?>">
                <img src="<?php echo e(asset('assets/images/favicon/favicon-uinjambi.svg')); ?>" alt="">
                <span>
                    <strong>Sistem Blok</strong>
                    <span>SIAKAD Fakultas Kedokteran</span>
                </span>
            </a>

            <a class="bar__back" href="<?php echo e(url('/')); ?>">
                <i class="ri-arrow-left-line"></i> <span>Halaman awal</span>
            </a>
        </div>
    </div>

    <main class="auth__main">
        <?php echo e($slot); ?>

    </main>

    <footer class="auth__foot">
        <?php echo e(date('Y')); ?> &copy; Fakultas Kedokteran UIN Jambi
    </footer>

    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-toggle-password]');
            if (!button) {
                return;
            }

            const input = button.closest('.pw')?.querySelector('input');
            if (!input) {
                return;
            }

            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? 'Sembunyikan password' : 'Tampilkan password');

            const icon = button.querySelector('i');
            if (icon) {
                icon.className = reveal ? 'ri-eye-off-line' : 'ri-eye-line';
            }
        });
    </script>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>

</html>
<?php /**PATH D:\laragon\www\sistem-blok\resources\views/layouts/guest.blade.php ENDPATH**/ ?>