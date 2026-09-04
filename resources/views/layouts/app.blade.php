<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable">

<head>
    <meta charset="utf-8" />
    <title>Sistem Blok — SIAKAD Fakultas Kedokteran UIN Jambi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Sistem manajemen pembelajaran blok Fakultas Kedokteran UIN Jambi" name="description" />
    <meta content="Fakultas Kedokteran UIN Jambi" name="author" />

    <link data-navigate-once rel="shortcut icon" href="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}">

    <link data-navigate-once rel="preconnect" href="https://fonts.googleapis.com">
    <link data-navigate-once rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link data-navigate-once rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,500;7..72,600;7..72,700&family=Archivo:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap">

    @vite(['resources/js/app.js'])
    <script src="{{ asset('assets/js/layout.js') }}"></script>
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/custom.min.css') }}" rel="stylesheet" type="text/css" />

    @livewireStyles

    <style>
        /* ═══ tokens ═══ */
        :root {
            --ink: #12251d;
            --ink-soft: #3d5049;
            --muted: #63726a;
            --green: #047857;
            --green-mid: #0f766e;
            --green-deep: #05392c;
            --green-wash: #eaf5f0;
            --line: #dde7e2;
            --line-firm: #c3d4cc;
            --paper: #f6faf8;
            --surface: #ffffff;
            --info: #0e6d8a;
            --warning: #96650a;
            --danger: #b42318;

            --font-display: 'Literata', Georgia, 'Times New Roman', serif;
            --font-body: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, 'Cascadia Mono', Consolas, monospace;

            /* Velzon's Bootstrap build uses the vz- prefix. Overriding the two font
               roots also reaches select2, swal2, chart and editor widgets. */
            --vz-font-sans-serif: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --vz-font-monospace: 'IBM Plex Mono', ui-monospace, 'Cascadia Mono', Consolas, monospace;
            --vz-body-font-family: 'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --vz-body-color: #12251d;
            --vz-body-bg: #f6faf8;
            --vz-heading-color: #12251d;
            --vz-border-color: #dde7e2;
            --vz-primary: #047857;
            --vz-primary-rgb: 4, 120, 87;
            --vz-link-color: #047857;
            --vz-link-hover-color: #05392c;
            --vz-table-border-color: #dde7e2;

            /* kept as aliases so any page-level style still resolves */
            --siakad-primary: #047857;
            --siakad-primary-dark: #05392c;
            --siakad-primary-soft: #eaf5f0;
            --siakad-success: #0f766e;
            --siakad-info: #0e6d8a;
            --siakad-warning: #96650a;
            --siakad-danger: #b42318;
            --siakad-surface: #ffffff;
            --siakad-soft: #f6faf8;
            --siakad-border: #dde7e2;
            --siakad-muted: #63726a;
            --siakad-ink: #12251d;

            /* sidebar: the institutional band from the welcome and login pages */
            --sb-bg: #05392c;
            --sb-deep: #03291f;
            --sb-line: rgba(255, 255, 255, .09);
            --sb-item: rgba(255, 255, 255, .72);
            --sb-icon: rgba(255, 255, 255, .55);
            --sb-title: rgba(255, 255, 255, .42);
            --sb-hover-bg: rgba(255, 255, 255, .06);
            --sb-active-bg: rgba(255, 255, 255, .1);
            --sb-marker: #4fb3bd;
        }

        /* Velzon themes the shell through these variables; setting them here also
           covers the collapsed (sm) sidebar and hover-menu states. Needs to outrank
           Velzon's own :root[data-sidebar=dark] block, hence html:root. */
        html:root[data-sidebar] {
            --vz-vertical-menu-bg: #05392c;
            --vz-vertical-menu-border: #05392c;
            --vz-vertical-menu-item-color: rgba(255, 255, 255, .72);
            --vz-vertical-menu-item-bg: rgba(255, 255, 255, .1);
            --vz-vertical-menu-item-hover-color: #ffffff;
            --vz-vertical-menu-item-active-color: #ffffff;
            --vz-vertical-menu-item-active-bg: rgba(255, 255, 255, .1);
            --vz-vertical-menu-sub-item-color: rgba(255, 255, 255, .58);
            --vz-vertical-menu-sub-item-hover-color: #ffffff;
            --vz-vertical-menu-sub-item-active-color: #ffffff;
            --vz-vertical-menu-title-color: rgba(255, 255, 255, .42);
            --vz-vertical-menu-box-shadow: none;
            --vz-vertical-menu-dropdown-box-shadow: none;

            --vz-header-bg: #ffffff;
            --vz-header-border: #dde7e2;
            --vz-header-item-color: #3d5049;
            --vz-header-item-sub-color: #63726a;
            --vz-topbar-user-bg: #ffffff;
        }

        /* ═══ base ═══ */
        body {
            font-family: var(--font-body);
            color: var(--ink);
            background: var(--paper);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Velzon sets heading fonts via :is(.h1,…,h6), so these must match that
           specificity (0,1,0) to win rather than use bare element selectors. */
        :is(.h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6),
        .card-title {
            font-family: var(--font-body);
            font-weight: 600;
            letter-spacing: -.015em;
            color: var(--ink);
        }

        .ff-secondary,
        .ff-base {
            font-family: var(--font-body);
        }

        /* the serif is reserved for page-level titles only */
        :is(h1, h2, .h1, .h2),
        .page-title-box h4 {
            font-family: var(--font-display);
            letter-spacing: -.022em;
        }

        a:focus-visible,
        button:focus-visible,
        .btn:focus-visible,
        .nav-link:focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 2px;
        }

        /* ═══ topbar ═══ */
        #page-topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            box-shadow: none;
        }

        #page-topbar .navbar-header {
            background: transparent;
        }

        .topbar-org {
            font-family: var(--font-mono);
            font-size: .64rem;
            font-weight: 500;
            line-height: 1.5;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .topbar-org b {
            display: block;
            font-weight: 500;
            color: var(--ink-soft);
        }

        #page-topbar .topbar-user .btn {
            padding: .3rem .5rem;
        }

        .user-name-text {
            font-family: var(--font-body);
            font-weight: 500;
            font-size: .9rem;
            color: var(--ink);
        }

        .user-name-sub-text {
            font-family: var(--font-mono);
            font-size: .62rem;
            letter-spacing: .06em;
            color: var(--muted);
        }

        .topnav-hamburger .hamburger-icon span {
            background-color: var(--ink-soft);
        }

        /* ═══ brand mark ═══ */
        .siakad-brand-mark {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 0;
            box-shadow: none;
            border-radius: 0;
        }

        .siakad-brand-mark img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .siakad-brand-text {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: -.02em;
            line-height: 1.1;
            color: var(--ink);
        }

        .siakad-brand-subtitle {
            font-family: var(--font-mono);
            font-size: .6rem;
            font-weight: 500;
            letter-spacing: .11em;
            text-transform: uppercase;
            color: var(--muted);
        }

        /* ═══ sidebar (deep green) ═══ */
        .app-menu.navbar-menu {
            background: var(--sb-bg);
            border-right: 0;
        }

        html[data-sidebar] .navbar-menu .navbar-brand-box {
            background: var(--sb-deep);
            border-right: 0;
            border-bottom: 1px solid var(--sb-line);
        }

        /* pin the logo variants per surface — Velzon's own rules are all (0,2,0),
           so which one wins would otherwise depend on minified source order */
        html[data-sidebar] .app-menu .navbar-brand-box .logo-light {
            display: block;
        }

        html[data-sidebar] .app-menu .navbar-brand-box .logo-dark {
            display: none;
        }

        html[data-sidebar] #page-topbar .horizontal-logo .logo-dark {
            display: block;
        }

        html[data-sidebar] #page-topbar .horizontal-logo .logo-light {
            display: none;
        }

        .navbar-menu .siakad-brand-subtitle {
            color: rgba(255, 255, 255, .55);
        }

        /* the crest is navy + teal, so it needs a light ground on the green sidebar */
        .navbar-menu .siakad-brand-mark {
            width: 34px;
            height: 34px;
            background: #ffffff;
            border-radius: 6px;
        }

        .navbar-menu .siakad-brand-mark img {
            width: 26px;
            height: 26px;
        }

        #vertical-hover,
        .btn-vertical-sm-hover {
            color: rgba(255, 255, 255, .55);
        }

        #navbar-nav .menu-title {
            font-family: var(--font-mono);
            font-size: .62rem;
            font-weight: 500;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: var(--sb-title);
            padding: 1.35rem 1.4rem .5rem;
        }

        #navbar-nav .menu-title span {
            color: inherit;
        }

        #navbar-nav .nav-link {
            font-family: var(--font-body);
            font-size: .9rem;
            font-weight: 500;
            letter-spacing: 0;
            color: var(--sb-item);
            border-radius: 4px;
            transition: background-color .16s ease, color .16s ease;
        }

        /* geometry only while expanded — the collapsed rail keeps Velzon's icon layout */
        html:not([data-sidebar-size="sm"]) #navbar-nav .nav-link {
            margin: 1px .55rem;
            padding: .58rem .7rem;
        }

        #navbar-nav .nav-link i,
        #navbar-nav .nav-link svg {
            color: var(--sb-icon);
        }

        #navbar-nav .nav-link:hover,
        #navbar-nav .nav-link:hover i {
            color: #fff;
        }

        #navbar-nav .nav-link:hover {
            background: var(--sb-hover-bg);
        }

        #navbar-nav .nav-link.active,
        #navbar-nav .nav-link.active i {
            color: #fff;
        }

        #navbar-nav .nav-link.active {
            background: var(--sb-active-bg);
            font-weight: 600;
            box-shadow: inset 2px 0 0 var(--sb-marker);
        }

        #navbar-nav .menu-dropdown {
            background: rgba(0, 0, 0, .16);
        }

        #navbar-nav .nav-sm .nav-link {
            position: relative;
            font-size: .855rem;
            font-weight: 400;
            color: rgba(255, 255, 255, .58);
            box-shadow: none;
        }

        html:not([data-sidebar-size="sm"]) #navbar-nav .nav-sm .nav-link {
            padding: .42rem .7rem .42rem 1.85rem;
            margin-inline: .55rem;
        }

        #navbar-nav .nav-sm .nav-link::before {
            background-color: rgba(255, 255, 255, .4);
        }

        #navbar-nav .nav-sm .nav-link:hover {
            color: #fff;
            background: var(--sb-hover-bg);
        }

        #navbar-nav .nav-sm .nav-link.active {
            color: #fff;
            font-weight: 600;
            background: transparent;
            box-shadow: none;
        }

        #navbar-nav .nav-sm .nav-link.active::before {
            background-color: var(--sb-marker);
        }

        /* ═══ work area ═══ */
        .main-content {
            background: var(--paper);
        }

        .page-content {
            padding-top: calc(70px + 1.5rem);
        }

        .footer {
            background: var(--surface);
            border-top: 1px solid var(--line);
            font-family: var(--font-mono);
            font-size: .66rem;
            letter-spacing: .07em;
            color: var(--muted);
        }

        #back-to-top {
            border-radius: 4px;
        }

        /* ═══ page title ═══ */
        .page-title-box {
            padding-bottom: 1.15rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--line);
        }

        .page-title-box h4 {
            font-size: 1.45rem;
            font-weight: 600;
        }

        .page-title-box .breadcrumb {
            font-family: var(--font-mono);
            font-size: .64rem;
            font-weight: 500;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .breadcrumb-item a {
            color: var(--muted);
        }

        .breadcrumb-item a:hover {
            color: var(--green);
        }

        .breadcrumb-item.active {
            color: var(--green);
        }

        .breadcrumb-item + .breadcrumb-item::before {
            color: var(--line-firm);
        }

        /* ═══ cards ═══ */
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 6px;
            box-shadow: none !important;
        }

        .card-header {
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            padding: .95rem 1.1rem;
        }

        .card-title {
            font-size: 1rem;
        }

        .card-footer {
            background: var(--surface);
            border-top: 1px solid var(--line);
        }

        /* ═══ buttons ═══ */
        .btn {
            font-family: var(--font-body);
            font-weight: 500;
            border-radius: 4px;
        }

        .btn-primary {
            --vz-btn-bg: #047857;
            --vz-btn-border-color: #047857;
            --vz-btn-hover-bg: #05392c;
            --vz-btn-hover-border-color: #05392c;
            --vz-btn-active-bg: #05392c;
            --vz-btn-active-border-color: #05392c;
            --vz-btn-disabled-bg: #7ba699;
            --vz-btn-disabled-border-color: #7ba699;
            --vz-btn-focus-shadow-rgb: 4, 120, 87;
        }

        .btn-success {
            --vz-btn-bg: #0f766e;
            --vz-btn-border-color: #0f766e;
            --vz-btn-hover-bg: #0b5a54;
            --vz-btn-hover-border-color: #0b5a54;
            --vz-btn-active-bg: #0b5a54;
            --vz-btn-focus-shadow-rgb: 15, 118, 110;
        }

        .btn-danger {
            --vz-btn-bg: #b42318;
            --vz-btn-border-color: #b42318;
            --vz-btn-hover-bg: #8a1c14;
            --vz-btn-hover-border-color: #8a1c14;
            --vz-btn-active-bg: #8a1c14;
            --vz-btn-focus-shadow-rgb: 180, 35, 24;
        }

        .btn-info {
            --vz-btn-bg: #0e6d8a;
            --vz-btn-border-color: #0e6d8a;
            --vz-btn-hover-bg: #0b566d;
            --vz-btn-hover-border-color: #0b566d;
            --vz-btn-active-bg: #0b566d;
            --vz-btn-focus-shadow-rgb: 14, 109, 138;
        }

        .btn-light {
            --vz-btn-bg: #f6faf8;
            --vz-btn-border-color: #dde7e2;
            --vz-btn-color: #3d5049;
            --vz-btn-hover-bg: #eaf5f0;
            --vz-btn-hover-border-color: #c3d4cc;
            --vz-btn-hover-color: #12251d;
            --vz-btn-active-bg: #eaf5f0;
        }

        .btn-soft-primary {
            --vz-btn-bg: #eaf5f0;
            --vz-btn-color: #047857;
            --vz-btn-border-color: transparent;
            --vz-btn-hover-bg: #047857;
            --vz-btn-hover-color: #fff;
        }

        .btn-soft-info {
            --vz-btn-bg: #e8f2f6;
            --vz-btn-color: #0e6d8a;
            --vz-btn-border-color: transparent;
            --vz-btn-hover-bg: #0e6d8a;
            --vz-btn-hover-color: #fff;
        }

        .btn-soft-secondary {
            --vz-btn-bg: #f0f4f2;
            --vz-btn-color: #3d5049;
            --vz-btn-border-color: transparent;
            --vz-btn-hover-bg: #3d5049;
            --vz-btn-hover-color: #fff;
        }

        /* ═══ forms ═══ */
        .form-label {
            font-family: var(--font-mono);
            font-size: .67rem;
            font-weight: 500;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: .4rem;
        }

        .form-control,
        .form-select {
            font-family: var(--font-body);
            font-size: .93rem;
            color: var(--ink);
            background-color: var(--surface);
            border: 1px solid var(--line-firm);
            border-radius: 4px;
            padding: .55rem .75rem;
        }

        .form-control::placeholder {
            color: #9aa8a1;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, .13);
        }

        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: var(--danger);
        }

        .form-control.is-invalid:focus,
        .form-select.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(180, 35, 24, .13);
        }

        .form-control:disabled,
        .form-select:disabled {
            background-color: var(--paper);
            color: var(--muted);
        }

        .input-group-text {
            font-family: var(--font-mono);
            font-size: .68rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            background-color: var(--paper);
            border-color: var(--line-firm);
            border-radius: 4px;
        }

        .form-check-input {
            border-color: var(--line-firm);
            border-radius: 3px;
        }

        .form-check-input[type=radio] {
            border-radius: 50%;
        }

        .form-check-input:checked {
            background-color: var(--green);
            border-color: var(--green);
        }

        .form-check-input:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(4, 120, 87, .13);
        }

        .invalid-feedback,
        .text-danger {
            color: var(--danger) !important;
        }

        /* ═══ tables ═══ */
        table {
            border: 1px solid var(--line);
            border-collapse: collapse;
        }

        table th,
        table td {
            border: 1px solid var(--line);
        }

        thead th {
            font-family: var(--font-mono);
            font-size: .655rem;
            font-weight: 500;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
            background-color: var(--surface);
            border-bottom: 1px solid var(--line-firm);
            padding: .7rem .75rem;
            white-space: nowrap;
        }

        /* neutralise Bootstrap's zebra shading and restore full grid lines
           (Bootstrap only draws bottom borders), then re-add a quiet row hover */
        .table>:not(caption)>*>* {
            padding: .62rem .75rem;
            background-color: transparent;
            box-shadow: none;
            border-width: 1px;
            border-style: solid;
            border-color: var(--line);
            vertical-align: middle;
        }

        .table-hover>tbody>tr:hover>* {
            background-color: #f3f9f6;
        }

        /* Bootstrap's .table>:not(caption)>*>* would otherwise win on the divider colour */
        .table>thead>tr>th {
            border-bottom-color: var(--line-firm);
        }

        .table-group-divider {
            border-top: 1px solid var(--line-firm) !important;
        }

        .page-link {
            color: var(--ink-soft);
            border-color: var(--line);
            font-size: .87rem;
        }

        .page-link:hover {
            color: var(--green);
            background-color: var(--paper);
            border-color: var(--line-firm);
        }

        .active>.page-link,
        .page-link.active {
            background-color: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        /* ═══ badges, alerts, semantic tints ═══ */
        .badge {
            font-family: var(--font-mono);
            font-size: .66rem;
            font-weight: 500;
            letter-spacing: .06em;
            padding: .34rem .5rem;
            border-radius: 3px;
        }

        .text-primary {
            color: var(--green) !important;
        }

        .text-success {
            color: var(--green-mid) !important;
        }

        .text-info {
            color: var(--info) !important;
        }

        .text-warning {
            color: var(--warning) !important;
        }

        .text-muted {
            color: var(--muted) !important;
        }

        .bg-primary-subtle {
            background-color: var(--green-wash) !important;
        }

        .bg-success-subtle {
            background-color: #e7f3ef !important;
        }

        .bg-info-subtle {
            background-color: #e8f2f6 !important;
        }

        .bg-warning-subtle {
            background-color: #f7f2e6 !important;
        }

        .bg-danger-subtle {
            background-color: #fbeceb !important;
        }

        .bg-light {
            background-color: var(--paper) !important;
        }

        .alert {
            border: 1px solid var(--line);
            border-radius: 4px;
            font-size: .9rem;
        }

        .alert-success {
            background-color: #eaf5f0;
            border-color: #cfe6dc;
            color: #065f46;
        }

        .alert-info {
            background-color: #eef5f8;
            border-color: #d3e5ec;
            color: #0e5468;
        }

        .alert-warning {
            background-color: #f8f3e7;
            border-color: #ead9b8;
            color: #7a5208;
        }

        .alert-danger {
            background-color: #fbeceb;
            border-color: #f2cfcc;
            color: #8a1c14;
        }

        /* ═══ tabs, dropdown, modal, list ═══ */
        .nav-tabs {
            border-bottom: 1px solid var(--line);
        }

        .nav-tabs .nav-link {
            border: 0;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            color: var(--muted);
            font-weight: 500;
            font-size: .92rem;
            padding: .6rem .9rem;
        }

        .nav-tabs .nav-link:hover {
            background: transparent;
            color: var(--ink);
            border-bottom-color: var(--line-firm);
        }

        .nav-tabs .nav-link.active {
            background: transparent;
            color: var(--green);
            border-bottom-color: var(--green);
        }

        .dropdown-menu {
            border: 1px solid var(--line);
            border-radius: 6px;
            box-shadow: 0 12px 28px rgba(18, 37, 29, .09);
            padding: .35rem;
            font-size: .91rem;
        }

        .dropdown-item {
            border-radius: 4px;
            padding: .45rem .6rem;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            background-color: var(--paper);
            color: var(--green);
        }

        .dropdown-header {
            font-family: var(--font-mono);
            font-size: .63rem;
            letter-spacing: .13em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .dropdown-divider {
            border-top-color: var(--line);
        }

        .modal-content {
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .modal-header,
        .modal-footer {
            border-color: var(--line);
        }

        .modal-title {
            font-family: var(--font-body);
            font-size: 1.05rem;
            font-weight: 600;
        }

        .list-group-item {
            border-color: var(--line);
        }

        .swal2-popup {
            font-family: var(--font-body);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .swal2-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--ink);
        }

        .swal2-html-container {
            font-size: .93rem;
            color: var(--ink-soft);
        }

        /* ═══ save button used on the form pages ═══ */
        .fab-save {
            border-radius: 4px;
            padding: .7rem 1.25rem;
            font-family: var(--font-mono);
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .14em;
        }

        /* ═══ menu permission tree ═══ */
        .tree-container {
            list-style: none;
            padding-left: 0;
        }

        .tree-item {
            position: relative;
            padding-left: 25px;
            margin-bottom: 10px;
        }

        .tree-item::before {
            content: "";
            position: absolute;
            left: 0;
            top: -10px;
            bottom: 50%;
            width: 1px;
            background: var(--line-firm);
        }

        .tree-item::after {
            content: "";
            position: absolute;
            left: 0;
            top: 15px;
            width: 20px;
            height: 1px;
            background: var(--line-firm);
        }

        .tree-item:last-child::before {
            height: 25px;
        }

        .indent-child {
            margin-left: 30px;
        }

        .indent-permission {
            margin-left: 60px;
        }

    </style>

    {{-- after the layout styles so page-specific pushes win --}}
    @stack('styles')
</head>

<body>
    <div id="layout-wrapper">
        <livewire:layouts::header />
        <livewire:layouts::navbar />

        <div class="vertical-overlay"></div>

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    {{ $slot }}
                </div>
            </div>

            <footer class="footer">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            {{ date('Y') }} &copy; Sistem Blok FK UIN Jambi
                        </div>
                        <div class="col-sm-6">
                            <div class="text-sm-end d-none d-sm-block">
                                Fakultas Kedokteran UIN Jambi
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <button onclick="topFunction()" class="btn btn-primary btn-icon" id="back-to-top">
        <i class="ri-arrow-up-line"></i>
    </button>

    <div id="preloader">
        <div id="status">
            <div class="spinner-border text-primary avatar-sm" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
        </div>
    </div>

    <script data-navigate-once src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script data-navigate-once src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
    <script data-navigate-once src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
    <script data-navigate-once src="{{ asset('assets/libs/feather-icons/feather.min.js') }}"></script>
    <script data-navigate-once src="{{ asset('assets/libs/sweetalert2/sweetalert2.all.min.js') }}"></script>
    @livewireScripts
    <script data-navigate-once>
        (() => {
            if (window.__siakadLayoutBridgeBound) {
                return;
            }

            window.__siakadLayoutBridgeBound = true;

            const closeMobileSidebar = () => {
                document.body.classList.remove('vertical-sidebar-enable');
                document.querySelector('.hamburger-icon')?.classList.remove('open');
            };

            const toggleSidebar = () => {
                const html = document.documentElement;
                const width = html.clientWidth;
                const hamburger = document.querySelector('.hamburger-icon');

                if (html.getAttribute('data-layout') !== 'vertical') {
                    return;
                }

                if (width <= 767) {
                    document.body.classList.toggle('vertical-sidebar-enable');
                    html.setAttribute('data-sidebar-size', 'lg');
                    hamburger?.classList.toggle('open', document.body.classList.contains('vertical-sidebar-enable'));

                    return;
                }

                document.body.classList.remove('vertical-sidebar-enable');
                html.setAttribute(
                    'data-sidebar-size',
                    html.getAttribute('data-sidebar-size') === 'sm' ? 'lg' : 'sm'
                );
                hamburger?.classList.toggle('open', html.getAttribute('data-sidebar-size') === 'sm');
            };

            window.topFunction = () => {
                document.body.scrollTop = 0;
                document.documentElement.scrollTop = 0;
            };

            window.addEventListener('scroll', () => {
                const backToTop = document.getElementById('back-to-top');

                if (!backToTop) {
                    return;
                }

                backToTop.style.display = (
                    document.body.scrollTop > 100 ||
                    document.documentElement.scrollTop > 100
                ) ? 'block' : 'none';
            });

            document.addEventListener('click', (event) => {
                if (event.target.closest('#topnav-hamburger-icon')) {
                    event.preventDefault();
                    toggleSidebar();
                }

                if (event.target.closest('.vertical-overlay')) {
                    closeMobileSidebar();
                }
            });

            document.addEventListener('livewire:navigated', () => {
                if (document.documentElement.clientWidth <= 767) {
                    closeMobileSidebar();
                    document.documentElement.setAttribute('data-sidebar-size', 'lg');
                }
            });

            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2600,
                timerProgressBar: true,
                customClass: {
                    popup: 'rounded shadow-sm'
                }
            });

            document.addEventListener('notify', (event) => {
                const payload = event.detail?.message || event.detail?.[0]?.message || event.detail || {};
                const status = payload.status === 'failed' ? 'error' : (payload.status || 'success');

                toast.fire({
                    icon: status,
                    title: payload.message || 'Proses berhasil'
                });
            });

            document.addEventListener('siakad-confirm', (event) => {
                const payload = event.detail || {};

                Swal.fire({
                    title: payload.title || 'Hapus data?',
                    text: payload.text || 'Data yang dihapus tidak dapat dikembalikan.',
                    icon: payload.icon || 'warning',
                    showCancelButton: true,
                    confirmButtonText: payload.confirmButtonText || 'Ya, hapus',
                    cancelButtonText: payload.cancelButtonText || 'Batal',
                    reverseButtons: true,
                    buttonsStyling: false,
                    customClass: {
                        popup: 'rounded shadow-sm',
                        confirmButton: 'btn btn-danger ms-2',
                        cancelButton: 'btn btn-light'
                    }
                }).then((result) => {
                    if (result.isConfirmed && payload.confirmEvent && payload.id) {
                        Livewire.dispatch(payload.confirmEvent, { id: payload.id });
                    }
                });
            });
        })();
    </script>

    @stack('scripts')
</body>

</html>
