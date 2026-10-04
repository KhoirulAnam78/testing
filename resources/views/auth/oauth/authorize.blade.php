<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Otorisasi Aplikasi — SSO PLASMA &amp; CBT</title>

    <link rel="shortcut icon" href="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: #f6faf8;
            color: #12251d;
        }

        .authorization-card {
            max-width: 560px;
            border: 1px solid #dde7e2;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(5, 57, 44, .08);
        }

        .app-mark {
            display: grid;
            width: 3rem;
            height: 3rem;
            place-items: center;
            border-radius: .75rem;
            background: #fff;
            border: 1px solid #dde7e2;
        }

        .app-mark img {
            width: 2.25rem;
            height: 2.25rem;
        }

        .scope-list {
            background: #f6faf8;
            border: 1px solid #dde7e2;
            border-radius: .75rem;
        }

        .btn-approve {
            background: #047857;
            border-color: #047857;
            color: #fff;
        }

        .btn-approve:hover,
        .btn-approve:focus {
            background: #065f46;
            border-color: #065f46;
            color: #fff;
        }
    </style>
</head>
<body class="d-flex align-items-center py-5">
    <main class="container">
        <section class="authorization-card card mx-auto">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="app-mark">
                        <img src="{{ asset('assets/images/favicon/favicon-uinjambi.svg') }}" alt="">
                    </div>
                    <div>
                        <div class="text-secondary small">SSO PLASMA &amp; CBT · FK UIN Jambi</div>
                        <h1 class="h4 mb-0">Permintaan otorisasi</h1>
                    </div>
                </div>

                <p class="mb-2">
                    <strong>{{ $client->name }}</strong> ingin menggunakan akun SSO Anda.
                </p>
                <p class="text-secondary small mb-4">
                    Masuk sebagai <strong>{{ $user->name }}</strong>
                    @if ($user->email)
                        ({{ $user->email }})
                    @endif
                </p>

                @if (count($scopes) > 0)
                    <div class="scope-list p-3 mb-4">
                        <p class="fw-semibold mb-2">Aplikasi dapat:</p>
                        <ul class="mb-0 ps-4">
                            @foreach ($scopes as $scope)
                                <li>{{ $scope->description }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-secondary small">
                    Lanjutkan hanya jika Anda mengenali dan mempercayai aplikasi ini.
                </p>

                <div class="d-flex flex-column-reverse flex-sm-row justify-content-end gap-2 mt-4">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn btn-outline-secondary w-100">Tolak</button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                        @csrf
                        <input type="hidden" name="state" value="{{ $request->state }}">
                        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="btn btn-approve w-100">Izinkan</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>
</html>